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
        // Pega a data que está vindo do calendário da tela ou assume HOJE
        $data = $request->get('data', now()->format('Y-m-d'));

        // --- INÍCIO DO DEBUG ---
        $transacoesDoDia = \App\Models\FinancialTransaction::whereDate('paid_at', $data)
            ->when($arenaId, fn($q) => $q->where('arena_id', $arenaId))
            ->get();

        $reservasDoDia = \App\Models\Reserva::whereDate('date', $data)
            ->when($arenaId, fn($q) => $q->where('arena_id', $arenaId))
            ->get();

        dd([
            'INFO' => 'Se voce esta vendo isso, agora estamos no lugar certo!',
            'Data_Consultada' => $data,
            'Arena_ID' => $arenaId,
            'Total_Transacoes_Encontradas' => $transacoesDoDia->count(),
            'Descricoes_Financeiro' => $transacoesDoDia->pluck('description')->toArray(),
            'Status_Reservas_No_Banco' => $reservasDoDia->pluck('status')->unique()->toArray(),
            'Contagem_Faltas_Raw' => $reservasDoDia->where('status', 'no_show')->count(),
        ]);
        // --- FIM DO DEBUG ---
    }

    /**
     * Relatório 01: Faturamento Detalhado (Com Arena, Busca, Paginação e Fluxo)
     */
    public function relatorioFaturamento(Request $request)
    {
        $arenaId = $request->get('arena_id');
        $search = $request->get('search');
        $fluxo = $request->get('fluxo'); // 🟢 Novo: entrada, saida ou null

        $dataInicio = $request->input('data_inicio')
            ? Carbon::parse($request->input('data_inicio'))->startOfDay()
            : now()->startOfMonth();

        $dataFim = $request->input('data_fim')
            ? Carbon::parse($request->input('data_fim'))->endOfDay()
            : now()->endOfDay();

        // Início da Query Base
        $query = FinancialTransaction::whereBetween('paid_at', [$dataInicio, $dataFim])
            ->when($arenaId, fn($q) => $q->where('arena_id', $arenaId))
            ->with(['reserva', 'arena']);

        // 🟢 FILTRO DE FLUXO (Trabalha sobre o valor do amount)
        if ($fluxo === 'entrada') {
            $query->where('amount', '>', 0);
        } elseif ($fluxo === 'saida') {
            $query->where('amount', '<', 0);
        }

        // 🔍 Filtro por Nome do Cliente ou ID da Reserva
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('reserva', function ($sub) use ($search) {
                    $sub->where('client_name', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%");
                });
            });
        }

        // 📊 UNIFICAÇÃO DE CASH E MONEY NOS TOTAIS
        $queryParaTotais = clone $query;
        $transacoesParaTotais = $queryParaTotais->get();

        // Agrupamos transformando 'money' em 'cash' para somar no mesmo card
        $totaisPorMetodo = $transacoesParaTotais->groupBy(function ($item) {
            $metodo = strtolower($item->payment_method);
            return ($metodo === 'money') ? 'cash' : $metodo;
        })->map(fn($row) => $row->sum('amount'));

        $faturamentoTotal = $transacoesParaTotais->sum('amount');

        // 📄 Tabela Paginada
        $transacoes = $query->orderBy('paid_at', 'desc')->paginate(30)->withQueryString();

        return view('admin.financeiro.relatorio_faturamento', compact(
            'transacoes',
            'totaisPorMetodo',
            'faturamentoTotal',
            'dataInicio',
            'dataFim',
            'fluxo' // Enviado para manter o select preenchido na view
        ));
    }

    /**
     * Relatório 02: Histórico de Caixa (Isolado por Arena)
     */
    public function relatorioCaixa(Request $request)
    {
        $arenaId = $request->get('arena_id');
        $data = $request->input('data', now()->format('Y-m-d'));

        $movimentacoes = FinancialTransaction::whereDate('paid_at', $data)
            ->when($arenaId, fn($q) => $q->where('arena_id', $arenaId))
            ->with(['reserva', 'manager', 'arena'])
            ->orderBy('paid_at', 'asc')
            ->get();

        $cashierHistory = Cashier::with(['user', 'arena'])
            ->when($arenaId, fn($q) => $q->where('arena_id', $arenaId))
            ->orderBy('date', 'desc')
            ->limit(10)
            ->get();

        return view('admin.financeiro.caixa', compact('movimentacoes', 'data', 'cashierHistory', 'arenaId'));
    }

    /**
     * Relatório 03: Cancelamentos e No-Show
     */
    public function relatorioCancelamentos(Request $request)
    {
        $arenaId = $request->get('arena_id');
        $mes = (int) $request->input('mes', now()->month);
        $ano = (int) $request->input('ano', now()->year);

        // 1. Busca as Reservas com status de perda (O que já existia)
        $query = Reserva::whereIn('status', [
            Reserva::STATUS_CANCELADA,
            Reserva::STATUS_NO_SHOW,
            Reserva::STATUS_REJEITADA
        ])
            ->whereYear('date', $ano)
            ->whereMonth('date', $mes)
            ->when($arenaId, fn($q) => $q->where('arena_id', $arenaId))
            ->with(['user', 'arena']);

        $cancelamentos = $query->orderBy('date', 'desc')->get();

        // 2. BUSCA AS MULTAS NO FINANCEIRO (Para pegar Josenilson e outros do caixa)
        // Buscamos transações que tenham "Multa" ou "Falta" na descrição
        $multasAvulsas = FinancialTransaction::whereMonth('paid_at', $mes)
            ->whereYear('paid_at', $ano)
            ->where(function ($q) {
                $q->where('description', 'like', '%Multa de Falta%')
                    ->orWhere('description', 'like', '%No-Show%');
            })
            ->when($arenaId, fn($q) => $q->where('arena_id', $arenaId))
            ->get();

        // 3. Calculamos o valor total dessas multas para enviar à View
        // Isso será usado para somar no Card de Faltas (No-Show)
        $valorMultasFinanceiro = $multasAvulsas->sum('amount');

        return view('admin.financeiro.cancelamentos', compact(
            'cancelamentos',
            'mes',
            'ano',
            'valorMultasFinanceiro',
            'multasAvulsas'
        ));
    }

    /**
     * Relatório 05: Ranking de Clientes (Global ou por Unidade)
     */
    public function relatorioRanking(Request $request)
    {
        $arenaId = $request->get('arena_id');
        $mes = $request->input('mes', now()->month); // Pega o mês da URL
        $ano = $request->input('ano', now()->year);
        $hoje = now()->format('Y-m-d');

        $query = Reserva::select(
            'client_name',
            'client_contact',
            'user_id',
            DB::raw('SUM(total_paid) as total_gasto'),
            DB::raw("COUNT(CASE WHEN total_paid > 0 AND date <= '$hoje' THEN 1 END) as total_reservas")
        )
            ->whereIn('status', [Reserva::STATUS_CONFIRMADA, Reserva::STATUS_CONCLUIDA, Reserva::STATUS_LANCADA_CAIXA])
            ->where('total_paid', '>', 0)
            ->whereYear('date', $ano) // Filtro de Ano
            ->when($arenaId, fn($q) => $q->where('arena_id', $arenaId));

        // Se o mês não for "all", aplica o filtro de mês com cast para inteiro (evita erro 500)
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

        // Buscamos apenas reservas que o jogo aconteceu (completed)
        // mas o pagamento está pendente ou parcial.
        $query = Reserva::with(['user', 'arena'])
            ->where('status', 'completed')
            ->whereIn('payment_status', ['unpaid', 'partial']);

        // Filtro por Arena (Opcional)
        if ($arenaId) {
            $query->where('arena_id', $arenaId);
        }

        // Filtro por Nome ou ID (Opcional)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('client_name', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        // Ordenamos das mais recentes para as mais antigas
        $dividas = $query->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate(30)
            ->withQueryString();

        // Calculamos o total global de dívidas para o resumo do relatório
        $totalGlobalDividas = $query->get()->sum(function ($r) {
            return ($r->final_price ?? $r->price) - $r->total_paid;
        });

        $arenas = Arena::all();

        return view('admin.financeiro.dividas', compact('dividas', 'totalGlobalDividas', 'arenas'));
    }
}
