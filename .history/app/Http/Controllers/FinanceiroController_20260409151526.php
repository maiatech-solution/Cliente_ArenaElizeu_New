<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Reserva;
use App\Models\FinancialTransaction;
use App\Models\Cashier;
use App\Models\Arena;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinanceiroController extends Controller
{
    /**
     * Dashboard Principal (Hub de Relatórios) - MULTIQUADRA
     */
    public function index(Request $request)
    {
        $arenaId = $request->get('arena_id');
        $referencia = $request->get('mes_referencia', now()->format('Y-m'));

        $dataFiltro = \Carbon\Carbon::parse($referencia . '-01');
        $mes = $dataFiltro->month;
        $ano = $dataFiltro->year;

        // 💰 1. FATURAMENTO FILTRADO (Apenas o que é financeiro real)
        // Adicionado o filtro where('payment_method', '!=', 'voucher')
        $faturamentoMensal = \App\Models\FinancialTransaction::whereMonth('paid_at', $mes)
            ->whereYear('paid_at', $ano)
            ->where('payment_method', '!=', 'voucher') // 👈 Ignora cortesias no faturamento
            ->when($arenaId, fn($q) => $q->where('arena_id', $arenaId))
            ->sum('amount');

        // 📅 2. OCUPAÇÃO FILTRADA (Permanece igual, pois o Voucher ocupou a quadra)
        $totalReservasMes = \App\Models\Reserva::whereMonth('date', $mes)
            ->whereYear('date', $ano)
            ->where('is_fixed', false)
            ->whereIn('status', [
                \App\Models\Reserva::STATUS_CONFIRMADA,
                \App\Models\Reserva::STATUS_CONCLUIDA,
                'completed',
                'debt'
            ])
            ->when($arenaId, fn($q) => $q->where('arena_id', $arenaId))
            ->count();

        // 🚫 3. FALTAS FILTRADAS
        $canceladasMes = \App\Models\Reserva::whereMonth('date', $mes)
            ->whereYear('date', $ano)
            ->where('status', \App\Models\Reserva::STATUS_NO_SHOW)
            ->when($arenaId, fn($q) => $q->where('arena_id', $arenaId))
            ->count();

        // 💸 4. CÁLCULO DE DÍVIDAS PENDENTES (Versão Otimizada)
        $totalGlobalDividas = \App\Models\Reserva::whereMonth('date', $mes)
            ->whereYear('date', $ano)
            ->whereIn('status', ['completed', 'debt'])
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->when($arenaId, fn($q) => $q->where('arena_id', $arenaId))
            ->with('transactions') // Puxa tudo de uma vez só (Eager Loading)
            ->get()
            ->sum(function ($r) {
                $valorVenda = (float) ($r->final_price ?? $r->price);

                // Aqui o sum() roda na memória (Collection), não faz novas queries no banco
                $saldoPagoLiquido = (float) $r->transactions->sum('amount');

                return max(0, $valorVenda - round($saldoPagoLiquido, 2));
            });

        // 🎁 5. TOTAL DE CORTESIAS (Vouchers) Concedidas no mês
        $totalVouchersMes = \App\Models\FinancialTransaction::whereMonth('paid_at', $mes)
            ->whereYear('paid_at', $ano)
            ->where('payment_method', 'voucher') // 👈 Pega só o que é voucher
            ->when($arenaId, fn($q) => $q->where('arena_id', $arenaId))
            ->sum('amount');

        $arenas = \App\Models\Arena::all();

        return view('admin.financeiro.index', [
            'faturamentoMensal'  => $faturamentoMensal,
            'totalReservasMes'   => $totalReservasMes,
            'canceladasMes'      => $canceladasMes,
            'totalGlobalDividas' => $totalGlobalDividas,
            'totalVouchersMes'   => $totalVouchersMes, // 👈 ADICIONE ESTA LINHA
            'arenas'             => $arenas,
            'dataFiltro'         => $dataFiltro
        ]);
    }

    /**
     * Relatório 01: Faturamento Detalhado (Com Arena, Busca, Paginação e Fluxo)
     */
    public function relatorioFaturamento(Request $request)
    {
        $arenaId = $request->get('arena_id');
        $search = $request->get('search');
        $fluxo = $request->get('fluxo');

        // Definição do período
        $dataInicio = $request->input('data_inicio')
            ? \Carbon\Carbon::parse($request->input('data_inicio'))->startOfDay()
            : now()->startOfMonth();

        $dataFim = $request->input('data_fim')
            ? \Carbon\Carbon::parse($request->input('data_fim'))->endOfDay()
            : now()->endOfDay();

        // Query Base
        $query = \App\Models\FinancialTransaction::whereBetween('paid_at', [$dataInicio, $dataFim])
            ->when($arenaId, fn($q) => $q->where('arena_id', $arenaId))
            ->with(['reserva', 'arena']);

        // Filtro de Fluxo (Entradas/Saídas)
        if ($fluxo === 'entrada') {
            $query->where('amount', '>', 0);
        } elseif ($fluxo === 'saida') {
            $query->where('amount', '<', 0);
        }

        // Filtro de Busca (Cliente ou ID)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('reserva', function ($sub) use ($search) {
                    $sub->where('client_name', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%");
                })->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Clone da query para cálculos de totais sem paginação
        $queryParaTotais = clone $query;
        $transacoesParaTotais = $queryParaTotais->get();

        // 1. Inicializamos os moldes fixos
        $totaisPorMetodo = [
            'dinheiro'      => 0,
            'pix'           => 0,
            'credito'       => 0,
            'debito'        => 0,
            'transferencia' => 0,
            'voucher'       => 0,
            'outro'         => 0,
        ];

        $faturamentoTotal = 0;

        // 2. Loop único para processar somas e evitar duplicidade entre "Voucher" e "Outro"
        foreach ($transacoesParaTotais as $item) {
            $metodoOriginal = strtolower($item->payment_method);

            // Mapeamento robusto: se cair em uma categoria, não cai em "Outro"
            $categoria = match (true) {
                in_array($metodoOriginal, ['dinheiro', 'money', 'cash', 'especie', \App\Models\FinancialTransaction::PAYMENT_MONEY]) => 'dinheiro',
                in_array($metodoOriginal, ['pix', \App\Models\FinancialTransaction::PAYMENT_PIX]) => 'pix',
                in_array($metodoOriginal, ['credito', 'credit_card', 'credit', \App\Models\FinancialTransaction::PAYMENT_CREDIT]) => 'credito',
                in_array($metodoOriginal, ['debito', 'debit_card', 'debit', 'cartao', 'card', \App\Models\FinancialTransaction::PAYMENT_DEBIT]) => 'debito',
                in_array($metodoOriginal, ['transferencia', 'transfer', 'bank', \App\Models\FinancialTransaction::PAYMENT_TRANSFER]) => 'transferencia',
                in_array($metodoOriginal, ['voucher', 'cortesia', \App\Models\FinancialTransaction::PAYMENT_VOUCHER]) => 'voucher',
                default => 'outro',
            };

            // Soma na categoria específica
            $totaisPorMetodo[$categoria] += $item->amount;

            // 💰 3. Soma no Faturamento Real apenas se NÃO for voucher
            if ($categoria !== 'voucher') {
                $faturamentoTotal += $item->amount;
            }
        }

        // Paginação dos resultados para a tabela
        $transacoes = $query->orderBy('paid_at', 'desc')->paginate(30)->withQueryString();

        return view('admin.financeiro.relatorio_faturamento', compact(
            'transacoes',
            'totaisPorMetodo',
            'faturamentoTotal',
            'dataInicio',
            'dataFim',
            'fluxo'
        ));
    }

    /**
     * Relatório 02: Histórico de Caixa (Isolado por Arena)
     */
    public function relatorioCaixa(Request $request)
    {
        $arenaId = $request->get('arena_id');
        $data = $request->input('data', now()->format('Y-m-d'));

        // 1. Busca todas as movimentações do dia
        $movimentacoes = FinancialTransaction::whereDate('paid_at', $data)
            ->when($arenaId, fn($q) => $q->where('arena_id', $arenaId))
            ->with(['reserva', 'manager', 'arena'])
            ->orderBy('paid_at', 'asc')
            ->get();

        // 💰 2. CÁLCULOS FINANCEIROS REAIS (Ignorando Vouchers)
        // Isso garante que o "Saldo Geral" e "Total Líquido" batam com a gaveta
        $totalEntradas = $movimentacoes
            ->where('amount', '>', 0)
            ->where('payment_method', '!=', FinancialTransaction::PAYMENT_VOUCHER)
            ->sum('amount');

        $totalSaidas = $movimentacoes
            ->where('amount', '<', 0)
            ->where('payment_method', '!=', FinancialTransaction::PAYMENT_VOUCHER)
            ->sum('amount');

        $saldoLiquidoReal = $totalEntradas + $totalSaidas;

        // 🎟️ 3. TOTAL DE CORTESIAS (Apenas informativo)
        $totalVouchers = $movimentacoes
            ->where('payment_method', FinancialTransaction::PAYMENT_VOUCHER)
            ->sum('amount');

        // 4. Histórico de Fechamentos
        $cashierHistory = Cashier::with(['user', 'arena'])
            ->when($arenaId, fn($q) => $q->where('arena_id', $arenaId))
            ->orderBy('date', 'desc')
            ->limit(10)
            ->get();

        return view('admin.financeiro.caixa', compact(
            'movimentacoes',
            'data',
            'cashierHistory',
            'arenaId',
            'totalEntradas',   // 👈 Enviando valores limpos para a View
            'totalSaidas',
            'saldoLiquidoReal',
            'totalVouchers'
        ));
    }

    /**
     * Relatório 03: Cancelamentos e No-Show
     */
    public function relatorioCancelamentos(Request $request)
    {
        $arenaId = $request->get('arena_id');
        $mes = (int) $request->input('mes', now()->month);
        $ano = (int) $request->input('ano', now()->year);

        // 1. Busca as Reservas com status de perda (No-Show, Rejeitada, Cancelada)
        // 🎯 AJUSTE: Ignoramos 'maintenance' e reservas com preço zerado
        $query = \App\Models\Reserva::whereIn('status', [
            \App\Models\Reserva::STATUS_CANCELADA,
            \App\Models\Reserva::STATUS_NO_SHOW,
            \App\Models\Reserva::STATUS_REJEITADA
        ])
            ->where('status', '!=', 'maintenance')
            ->where('price', '>', 0)
            ->whereYear('date', $ano)
            ->whereMonth('date', $mes)
            ->when($arenaId, fn($q) => $q->where('arena_id', $arenaId))
            ->with(['user', 'arena', 'transactions']); // 👈 Eager Loading de transactions

        $cancelamentos = $query->orderBy('date', 'desc')->get();

        // 2. BUSCA AS MULTAS NO FINANCEIRO (Ajustado para pegar sinais retidos)
        $multasAvulsas = \App\Models\FinancialTransaction::whereMonth('paid_at', $mes)
            ->whereYear('paid_at', $ano)
            ->where(function ($q) {
                $q->where('description', 'like', '%Multa%')
                    ->orWhere('description', 'like', '%No-Show%')
                    ->orWhere('description', 'like', '%ESTORNO%')
                    ->orWhere('description', 'like', '%RETIDO%');
            })
            ->when($arenaId, fn($q) => $q->where('arena_id', $arenaId))
            ->get();

        // 🎯 3. CONTAGENS PARA OS CARDS
        $countFaltas = $cancelamentos->where('status', \App\Models\Reserva::STATUS_NO_SHOW)->count();
        $countCancelamentos = $cancelamentos->where('status', \App\Models\Reserva::STATUS_CANCELADA)->count();
        $countRejeitadas = $cancelamentos->where('status', \App\Models\Reserva::STATUS_REJEITADA)->count();

        // 💰 4. CÁLCULO DO PREJUÍZO REAL (Líquido de Faltas e Vouchers)
        $prejuizoFaltasReal = $cancelamentos->where('status', \App\Models\Reserva::STATUS_NO_SHOW)
            ->sum(function ($reserva) use ($multasAvulsas) {
                $valorOriginal = (float) $reserva->price;

                // Abate 1: Vouchers vinculados (Cortesia/Crédito)
                $vouchers = (float) $reserva->transactions->where('payment_method', 'voucher')->sum('amount');

                // Abate 2: Multas de caixa (Ajustes manuais #ID)
                $multas = (float) $multasAvulsas
                    ->filter(fn($m) => str_contains($m->description, "#{$reserva->id}"))
                    ->sum('amount');

                // O prejuízo só diminui se o valor for positivo (recuperado)
                return max(0, $valorOriginal - max(0, $vouchers + $multas));
            });

        // 📊 5. IMPACTO TOTAL GERAL (Aquele valor do rodapé preto)
        $impactoTotalGeral = $cancelamentos->sum(function ($reserva) use ($multasAvulsas) {
            $valorOriginal = (float) $reserva->price;

            $vouchers = (float) $reserva->transactions->where('payment_method', 'voucher')->sum('amount');
            $multas = (float) $multasAvulsas
                ->filter(fn($m) => str_contains($m->description, "#{$reserva->id}"))
                ->sum('amount');

            return max(0, $valorOriginal - max(0, $vouchers + $multas));
        });

        $valorMultasFinanceiro = $multasAvulsas->sum('amount');

        return view('admin.financeiro.cancelamentos', compact(
            'cancelamentos',
            'mes',
            'ano',
            'valorMultasFinanceiro',
            'multasAvulsas',
            'countFaltas',
            'countCancelamentos',
            'countRejeitadas',
            'prejuizoFaltasReal',
            'impactoTotalGeral'
        ));
    }

    /**
     * Relatório 05: Ranking de Clientes (Global ou por Unidade)
     */
    public function relatorioRanking(Request $request)
    {
        $arenaId = $request->get('arena_id');
        $mes = $request->input('mes', now()->month);
        $ano = $request->input('ano', now()->year);
        $hoje = now()->format('Y-m-d');

        $query = Reserva::select(
            'client_name',
            'client_contact',
            'user_id',
            // 💰 Soma o que o cliente efetivamente pagou (inclui Vouchers se estiverem no total_paid)
            DB::raw('SUM(total_paid) as total_gasto'),
            // ⚽ Conta apenas partidas que não foram futuras e tiveram pagamento
            DB::raw("COUNT(CASE WHEN total_paid > 0 AND date <= '$hoje' THEN 1 END) as total_reservas")
        )
            // 🎯 Proteção: Apenas status válidos de faturamento, excluindo manutenção e rejeitadas
            ->whereIn('status', [
                Reserva::STATUS_CONFIRMADA,
                Reserva::STATUS_CONCLUIDA,
                Reserva::STATUS_LANCADA_CAIXA
            ])
            ->where('status', '!=', 'maintenance') // 🛡️ Blindagem contra manutenção
            ->where('total_paid', '>', 0)
            ->whereYear('date', $ano)
            ->when($arenaId, fn($q) => $q->where('arena_id', $arenaId));

        // Filtro de mês inteligente (suporta 'all')
        if ($mes !== 'all') {
            $query->whereMonth('date', (int)$mes);
        }

        $ranking = $query->groupBy('client_name', 'client_contact', 'user_id')
            ->orderBy('total_gasto', 'desc')
            ->limit(15)
            ->get();

        return view('admin.financeiro.ranking', compact('ranking'));
    }

    public static function isCashClosed(string $dateString, $arenaId = null): bool
    {
        // Se não passar a Arena, a função verifica se EXISTE QUALQUER caixa fechado no dia.
        // Isso serve como uma trava de segurança global caso o arena_id se perca.
        $query = Cashier::whereDate('date', $dateString)
            ->where('status', 'closed');

        if ($arenaId) {
            $query->where('arena_id', $arenaId);
        }

        return $query->exists();
    }

    public function getStatus(Request $request)
    {
        try {
            $targetDate = $request->query('date', now()->format('Y-m-d'));
            $arenaId = $request->query('arena_id');

            // 🎯 AJUSTE DE SEGURANÇA:
            // O status deve ser estritamente vinculado à Arena selecionada no Dashboard.
            // Se arena_id não for enviado, o sistema pode acabar pegando o status de outra quadra.
            $caixa = Cashier::whereDate('date', $targetDate)
                ->when($arenaId, function ($q) use ($arenaId) {
                    return $q->where('arena_id', $arenaId);
                })
                ->first();

            if ($caixa) {
                return response()->json([
                    // Um caixa só é considerado "fechado" se o status for explicitamente 'closed'
                    'isOpen' => $caixa->status !== 'closed',
                    'date'   => $targetDate,
                    'status' => $caixa->status,
                    'arena'  => $caixa->arena_id
                ]);
            }

            // Caso não exista registro de caixa para essa data e arena,
            // consideramos como aberto (status inicial do dia)
            return response()->json([
                'isOpen' => true,
                'date'   => $targetDate,
                'status' => 'not_created',
                'arena'  => $arenaId
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar status do caixa: ' . $e->getMessage());
            // Em caso de erro técnico, retornamos true para não travar a usabilidade do gestor
            return response()->json(['isOpen' => true, 'error' => $e->getMessage()], 200);
        }
    }

    /**
     * 📊 Relatório de Ocupação e Histórico de Uso
     */
    public function relatorioOcupacao(Request $request)
    {
        // Usa filled() para verificar se as datas realmente vieram no request
        $dataInicio = $request->filled('data_inicio')
            ? Carbon::parse($request->data_inicio)
            : now()->subDays(7);

        $dataFim = $request->filled('data_fim')
            ? Carbon::parse($request->data_fim)
            : now();

        $arenaId = $request->arena_id;

        $reservas = Reserva::with(['arena', 'user'])
            ->whereIn('status', [Reserva::STATUS_CONFIRMADA, 'completed', 'no_show', Reserva::STATUS_CONCLUIDA])
            ->whereBetween('date', [$dataInicio->format('Y-m-d'), $dataFim->format('Y-m-d')])
            ->when($arenaId, fn($q) => $q->where('arena_id', $arenaId))
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'asc')
            ->get();

        return view('admin.financeiro.ocupacao', [
            'reservas' => $reservas,
            'dataInicio' => $dataInicio,
            'dataFim' => $dataFim
        ]);
    }

    /**
     * Relatório 06: Dívidas Pendentes (Inadimplência Geral)
     * Lista todas as reservas 'completed' que não foram totalmente pagas.
     */
    public function relatorioDividas(Request $request)
    {
        $arenaId = $request->get('arena_id');
        $search = $request->get('search');

        // 1. Query Base: Filtramos apenas o que é cobrável.
        // 🎯 AJUSTE: Incluído 'maintenance' para não cobrar horários de manutenção.
        $query = \App\Models\Reserva::with(['arena', 'transactions'])
            ->whereNotIn('status', ['rejected', 'cancelled', 'maintenance'])
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('final_price', '>', 0);

        // 2. Aplicar Filtros Dinâmicos (Unidade e Busca)
        if ($arenaId) {
            $query->where('arena_id', $arenaId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('client_name', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        // 3. Paginação (Conserva os filtros na URL para navegação)
        $dividas = $query->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate(30)
            ->withQueryString();

        // 🎯 4. Cálculo do Total Global (Soma apenas a inadimplência real)
        // Usamos clone() para garantir que o cálculo use os mesmos filtros da listagem acima sem interferir na paginação
        $totalGlobalDividas = (clone $query)->get()->sum(function ($r) {
            $valorVenda = (float) ($r->final_price ?? $r->price);

            // Soma transações vinculadas (incluindo Vouchers que abatem a dívida)
            $diretas = (float) $r->transactions->sum('amount');

            // Busca ajustes manuais (transações órfãs) vinculados pelo #ID na descrição
            $orfas = (float) \App\Models\FinancialTransaction::whereNull('reserva_id')
                ->where('description', 'LIKE', "%#{$r->id}%")
                ->sum('amount');

            $pagoReal = round($diretas + $orfas, 2);

            // Retorna apenas a diferença (o que falta pagar)
            return max(0, $valorVenda - $pagoReal);
        });

        $arenas = \App\Models\Arena::all();

        return view('admin.financeiro.dividas', compact('dividas', 'totalGlobalDividas', 'arenas'));
    }
}
