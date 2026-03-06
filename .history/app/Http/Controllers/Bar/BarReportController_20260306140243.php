<?php

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Models\Bar\BarOrder;
use App\Models\Bar\BarOrderItem;
use App\Models\Bar\BarSale;
use App\Models\Bar\BarSaleItem;
use App\Models\Bar\BarCashMovement;
use App\Models\Bar\BarCashSession;
use App\Models\Bar\BarProduct;
use App\Models\Bar\BarStockMovement;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BarReportController extends Controller
{
    /**
     * DASHBOARD PRINCIPAL DE RELATÓRIOS
     */
    /**
     * DASHBOARD PRINCIPAL DE RELATÓRIOS (CORRIGIDO)
     */
    public function index(Request $request)
    {
        $mesReferencia = $request->input('mes_referencia', now()->format('Y-m'));
        $startDate = Carbon::parse($mesReferencia)->startOfMonth();
        $endDate = Carbon::parse($mesReferencia)->endOfMonth();

        // 🛡️ LÓGICA DE PRIVACIDADE:
        // Se for Admin ou Gestor, vê o faturamento total da Arena.
        // Se for Colaborador, vê apenas o que ele vendeu no mês.
        $user = auth()->user();
        $isAdmin = in_array($user->role, ['admin', 'gestor']);

        // 1. Faturamento Consolidado
        $queryMesas = BarOrder::whereIn('status', ['paid', 'pago'])
            ->whereBetween('updated_at', [$startDate, $endDate]);

        $queryPDV = BarSale::whereIn('status', ['paid', 'pago'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        // Se NÃO for admin, filtra pelo ID do usuário logado
        if (!$isAdmin) {
            $queryMesas->where('user_id', $user->id);
            $queryPDV->where('user_id', $user->id);
        }

        $faturamentoMesas = $queryMesas->sum('total_value');
        $faturamentoPDV = $queryPDV->sum('total_value');
        $faturamentoMensal = $faturamentoMesas + $faturamentoPDV;

        // 2. Itens Vendidos (Com Filtro de Usuário)
        $itensMesas = BarOrderItem::whereHas('order', function ($q) use ($startDate, $endDate, $isAdmin, $user) {
            $q->whereIn('status', ['paid', 'pago'])
                ->whereBetween('updated_at', [$startDate, $endDate]);
            if (!$isAdmin) $q->where('user_id', $user->id);
        })->sum('quantity');

        $itensPDV = BarSaleItem::whereHas('sale', function ($q) use ($startDate, $endDate, $isAdmin, $user) {
            $q->whereIn('status', ['paid', 'pago'])
                ->whereBetween('created_at', [$startDate, $endDate]);
            if (!$isAdmin) $q->where('user_id', $user->id);
        })->sum('quantity');

        $totalItensMes = $itensMesas + $itensPDV;

        // 3. Ticket Médio (Filtrado)
        $countMesas = (clone $queryMesas)->count();
        $countPDV = (clone $queryPDV)->count();
        $transacoes = $countMesas + $countPDV;

        $ticketMedio = $transacoes > 0 ? $faturamentoMensal / $transacoes : 0;

        // 4. Sangrias (Apenas as que o usuário fez, se não for admin)
        $querySangria = BarCashMovement::where('type', 'sangria')
            ->whereBetween('created_at', [$startDate, $endDate]);
        if (!$isAdmin) $querySangria->where('user_id', $user->id);

        $totalSangriasMes = $querySangria->sum('amount');

        return view('bar.reports.index', compact('faturamentoMensal', 'totalItensMes', 'ticketMedio', 'totalSangriasMes', 'mesReferencia'));
    }

    /**
     * RANKING DE PRODUTOS + MARGEM DE LUCRO
     */
    public function products(Request $request)
    {
        $mesReferencia = $request->input('mes_referencia', now()->format('Y-m'));
        $startDate = Carbon::parse($mesReferencia)->startOfMonth();
        $endDate = Carbon::parse($mesReferencia)->endOfMonth();

        // 1. Query das Mesas (Status: paid)
        $ordersPart = DB::table('bar_order_items as oi')
            ->join('bar_orders as o', 'oi.bar_order_id', '=', 'o.id')
            ->select('oi.bar_product_id', 'oi.quantity', 'oi.subtotal')
            ->where('o.status', 'paid') // Nas mesas é 'paid'
            ->whereBetween('o.updated_at', [$startDate, $endDate]);

        // 2. Query do PDV (Status: pago) - 🚨 AQUI ESTAVA O ERRO
        $salesPart = DB::table('bar_sale_items as si')
            ->join('bar_sales as s', 'si.bar_sale_id', '=', 's.id')
            ->select(
                'si.bar_product_id',
                'si.quantity',
                DB::raw('(si.quantity * si.price_at_sale) as subtotal')
            )
            ->where('s.status', 'pago') // 🎯 No PDV seu banco usa 'pago'
            ->whereBetween('s.created_at', [$startDate, $endDate]);

        // Unifica as duas origens
        $rankingFinal = $ordersPart->unionAll($salesPart);

        $ranking = DB::table(DB::raw("({$rankingFinal->toSql()}) as combined"))
            ->mergeBindings($rankingFinal)
            ->select(
                'bar_product_id',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(subtotal) as total_revenue')
            )
            ->groupBy('bar_product_id')
            ->orderBy('total_qty', 'desc')
            ->get();

        // 3. Processa os dados de lucro e produtos
        foreach ($ranking as $item) {
            $product = BarProduct::with('category')->find($item->bar_product_id);
            $item->product = $product;

            if ($product) {
                $custoUnitario = $product->purchase_price ?? 0;
                $item->total_cost = $custoUnitario * $item->total_qty;
                $item->total_profit = $item->total_revenue - $item->total_cost;
                $item->margin_percent = $item->total_revenue > 0 ? ($item->total_profit / $item->total_revenue) * 100 : 0;
            }
        }

        return view('bar.reports.products', compact('ranking', 'mesReferencia'));
    }

    /**
     * AUDITORIA DE FECHAMENTO DE CAIXA (Sincronizada com Fluxo de Caixa)
     */
    public function cashier(Request $request)
    {
        $mesReferencia = $request->input('mes_referencia', now()->format('Y-m'));
        $startDate = \Carbon\Carbon::parse($mesReferencia)->startOfMonth();
        $endDate = \Carbon\Carbon::parse($mesReferencia)->endOfMonth();

        $sessoes = \App\Models\Bar\BarCashSession::with('user')
            ->whereBetween('opened_at', [$startDate, $endDate])
            ->orderBy('opened_at', 'desc')
            ->get();

        foreach ($sessoes as $sessao) {
            // 🎯 A FONTE ÚNICA DA VERDADE: Movimentações financeiras da sessão
            $movimentacoes = \App\Models\Bar\BarCashMovement::where('bar_cash_session_id', $sessao->id)->get();

            // 1. Somatória de Vendas Brutas (Registradas como 'venda' no fluxo)
            $vendasBrutasTotal = $movimentacoes->where('type', 'venda')->sum('amount');

            // 2. Somatória de Estornos (O que anula a venda)
            $totalEstornado = $movimentacoes->where('type', 'estorno')->sum('amount');

            // 3. Reforços e Sangrias
            $reforcos = $movimentacoes->where('type', 'reforco')->sum('amount');
            $sangrias = $movimentacoes->where('type', 'sangria')->sum('amount');

            // 4. Resultado para a Tabela de Auditoria

            // "Vendas do Turno" agora mostra o Líquido Real (Vendas - Estornos)
            // Isso fará com que o valor na coluna "Vendas" bata com o que o usuário viu no dashboard
            $sessao->vendas_turno = $vendasBrutasTotal - $totalEstornado;

            // 📊 FÓRMULA MESTRA (Idêntica ao Controller de Fechamento)
            // Total Esperado = (Fundo Inicial + Vendas Brutas + Reforços) - (Sangrias + Estornos)
            $sessao->total_sistema_esperado = ($sessao->opening_balance + $vendasBrutasTotal + $reforcos) - ($sangrias + $totalEstornado);
        }

        return view('bar.reports.cashier', compact('sessoes', 'mesReferencia'));
    }

    /**
     * RESUMO DE VENDAS DIÁRIAS
     */
    public function daily(Request $request)
    {
        $mesReferencia = $request->input('mes_referencia', now()->format('Y-m'));
        $startDate = \Carbon\Carbon::parse($mesReferencia)->startOfMonth();
        $endDate = \Carbon\Carbon::parse($mesReferencia)->endOfMonth();

        // 1. Vendas de Mesas (Status: paid)
        $vendasMesas = \App\Models\Bar\BarOrder::where('status', 'paid')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(updated_at) as date'), DB::raw('SUM(total_value) as total'))
            ->groupBy('date')->get();

        // 2. Vendas de PDV (Status: pago) - AJUSTADO PARA O SEU BANCO
        $vendasPDV = \App\Models\Bar\BarSale::where('status', 'pago')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total_value) as total'))
            ->groupBy('date')->get();

        // 3. Monta o array com todos os dias do mês para o gráfico ficar bonito
        $datas = [];
        $periodo = new \DatePeriod($startDate, new \DateInterval('P1D'), $endDate->addDay());

        foreach ($periodo as $data) {
            $datas[$data->format('Y-m-d')] = ['mesas' => 0, 'pdv' => 0];
        }

        foreach ($vendasMesas as $v) {
            $datas[$v->date]['mesas'] = $v->total;
        }
        foreach ($vendasPDV as $v) {
            $datas[$v->date]['pdv'] = $v->total;
        }

        return view('bar.reports.daily', compact('datas', 'mesReferencia'));
    }

    /**
     * MEIOS DE PAGAMENTO
     */
    public function payments(Request $request)
    {
        // 1. Filtros de Período
        $mesReferencia = $request->input('mes_referencia', now()->format('Y-m'));
        $startDate = \Carbon\Carbon::parse($mesReferencia)->startOfMonth();
        $endDate = \Carbon\Carbon::parse($mesReferencia)->endOfMonth();

        // 🛡️ LÓGICA DE PRIVACIDADE MULTI-CAIXA
        $user = auth()->user();
        $isAdmin = in_array($user->role, ['admin', 'gestor']);

        // 2. Query Principal na bar_cash_movements
        $query = DB::table('bar_cash_movements')
            ->where('type', 'venda') // Apenas entradas de vendas
            ->whereBetween('created_at', [$startDate, $endDate]);

        // 🔥 O FILTRO MÁGICO: Se não for admin, vê apenas os SEUS pagamentos
        if (!$isAdmin) {
            $query->where('user_id', $user->id);
        }

        // Filtro por nome do método (Caso queira buscar um específico)
        if ($request->filled('search')) {
            $query->where('payment_method', 'like', '%' . $request->search . '%');
        }

        // Filtro por data específica dentro do mês
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $pagamentos = $query->select(
            'payment_method',
            DB::raw('SUM(amount) as total'),
            DB::raw('COUNT(*) as qtd')
        )
            ->groupBy('payment_method')
            ->get();

        return view('bar.reports.payments', compact('pagamentos', 'mesReferencia'));
    }

    /**
     * DESCONTOS E CANCELAMENTOS (LOGS)
     */
    public function cancelations(Request $request)
    {
        $mesReferencia = $request->input('mes_referencia', now()->format('Y-m'));
        $startDate = \Carbon\Carbon::parse($mesReferencia)->startOfMonth();
        $endDate = \Carbon\Carbon::parse($mesReferencia)->endOfMonth();

        // 1. Financeiro (Estornos de Caixa)
        $cancelamentosFinanceiros = \App\Models\Bar\BarCashMovement::with(['user'])
            ->where('type', 'estorno')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();

        // 2. Prejuízo Real (Perdas/Vencidos)
        $perdasReais = \App\Models\Bar\BarStockMovement::with(['product', 'user'])
            ->where('type', 'perda')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();

        // 💰 NOVO: Cálculo do prejuízo total em R$ (Baseado no preço de custo)
        $valorTotalPerdas = $perdasReais->sum(function ($movimento) {
            return abs($movimento->quantity) * ($movimento->product->purchase_price ?? 0);
        });

        // 3. Apenas Retorno (Itens que voltaram para o estoque)
        $retornosEstoque = \App\Models\Bar\BarStockMovement::with(['product', 'user'])
            ->where('type', 'input')
            ->where(function ($q) {
                $q->where('description', 'like', '%CANCELAMENTO%')
                    ->orWhere('description', 'like', '%ESTORNO%');
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('bar.reports.cancelations', compact(
            'cancelamentosFinanceiros',
            'perdasReais',
            'retornosEstoque',
            'mesReferencia',
            'valorTotalPerdas' // <-- Enviando para a view
        ));
    }

    /**
     * CONTROLE DE ESTOQUE (MOVIMENTAÇÕES) COM FILTROS
     */
    public function movements(Request $request)
    {
        // 1. Query para o Histórico de Movimentações (Tabela)
        $query = BarStockMovement::with(['product.category', 'user']);

        // Filtro por Tipo (Entrada ou Saída)
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filtro por Data Específica
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        // Filtro por Busca de Nome de Produto
        if ($request->filled('search')) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
            });
        }

        // Paginação das movimentações
        $movimentacoes = $query->orderBy('created_at', 'desc')
            ->paginate(30)
            ->withQueryString();

        // 2. 🔥 NOVIDADE: Busca a Posição Atual de todos os itens (Resumo do Topo)
        // Ordenamos pelos que têm menos estoque primeiro para destacar o que precisa comprar
        $inventorySummary = \App\Models\Bar\BarProduct::with('category')
            ->orderBy('stock_quantity', 'asc')
            ->get();

        return view('bar.reports.movements', compact('movimentacoes', 'inventorySummary'));
    }

    public function getDetails($tipo, $id)
    {
        try {
            $tipoLower = strtolower($tipo);

            // 1. Busca os dados conforme o tipo (Mesa ou Venda Direta)
            if ($tipoLower === 'mesa' || $tipoLower === 'mesas') {
                $venda = BarOrder::with(['items.product', 'user'])->findOrFail($id);
            } else {
                $venda = BarSale::with(['items.product', 'user'])->findOrFail($id);
            }

            // 2. Formatação dos Itens e Cálculo do Subtotal Bruto
            $subtotalBruto = 0;
            $itensFormatados = $venda->items->map(function ($item) use (&$subtotalBruto) {
                $precoUnitario = $item->price_at_sale ?? $item->unit_price ?? 0;
                $valorItem = $item->quantity * $precoUnitario;
                $subtotalBruto += $valorItem;

                return [
                    'nome'     => $item->product->name ?? 'Produto',
                    'qtd'      => $item->quantity,
                    'subtotal' => number_format($valorItem, 2, ',', '.')
                ];
            });

            // 3. Definição de Valores (Total e Desconto Real)
            $valorPago = (float)$venda->total_value;

            // Se a coluna discount_value existir, usamos ela. Caso contrário, calculamos a diferença.
            $desconto = isset($venda->discount_value)
                ? (float)$venda->discount_value
                : ($subtotalBruto - $valorPago);

            // 4. Tratativa do Meio de Pagamento / Status
            $pagamentoInfo = $venda->payment_method;

            if (!$pagamentoInfo) {
                $pagamentoInfo = match ($venda->status) {
                    'paid', 'pago' => 'PAGO',
                    'cancelled', 'cancelado' => 'ANULADA',
                    default => 'ABERTO',
                };
            }

            // 5. Retorno do JSON para o Modal
            return response()->json([
                'id'        => $venda->id,
                'tipo'      => strtoupper($tipo),
                'data'      => $venda->created_at->format('d/m/Y H:i'),
                'operador'  => $venda->user->name ?? 'N/A',
                'cliente'   => $venda->customer_name ?? 'Não identificado', // Campo novo
                'pagamento' => strtoupper($pagamentoInfo),
                'total'     => number_format($valorPago, 2, ',', '.'),
                'total_raw' => $valorPago,
                'desconto'  => $desconto > 0.01 ? (float)$desconto : 0,
                'itens'     => $itensFormatados
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => true,
                'message' => $e->getMessage()
            ], 404);
        }
    }
}
