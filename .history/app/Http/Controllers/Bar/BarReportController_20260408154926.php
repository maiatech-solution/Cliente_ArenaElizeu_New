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
     * DASHBOARD PRINCIPAL DE RELATÓRIOS (CORRIGIDO)
     */
    public function index(Request $request)
    {
        $mesReferencia = $request->input('mes_referencia', now()->format('Y-m'));
        $startDate = Carbon::parse($mesReferencia)->startOfMonth();
        $endDate = Carbon::parse($mesReferencia)->endOfMonth();

        $user = auth()->user();
        $isAdmin = in_array($user->role, ['admin', 'gestor']);

        // --- 1. FATURAMENTO REAL (Apenas o que NÃO é Voucher) ---
        // Filtramos para ignorar o que foi fechado como Voucher/Cortesia no faturamento
        $queryOrders = BarOrder::whereIn('status', ['paid', 'pago'])
            ->where('payment_method', 'not like', '%VOUCHER%')
            ->whereBetween('updated_at', [$startDate, $endDate]);

        $querySales = BarSale::whereIn('status', ['paid', 'pago'])
            ->where('payment_method', '!=', 'voucher')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if (!$isAdmin) {
            $queryOrders->where('user_id', $user->id);
            $querySales->where('user_id', $user->id);
        }

        $faturamentoMensal = $queryOrders->sum('total_value') + $querySales->sum('total_value');

        // --- 2. CÁLCULO DE VOUCHERS (O "Prejuízo" ou Investimento em Cortesia) ---
        // Aqui somamos o valor dos itens que saíram via Voucher
        $queryVouchersOrders = BarOrder::whereIn('status', ['paid', 'pago'])
            ->where('payment_method', 'like', '%VOUCHER%')
            ->whereBetween('updated_at', [$startDate, $endDate]);

        $queryVouchersSales = BarSale::whereIn('status', ['paid', 'pago'])
            ->where('payment_method', 'voucher')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if (!$isAdmin) {
            $queryVouchersOrders->where('user_id', $user->id);
            $queryVouchersSales->where('user_id', $user->id);
        }

        // Nota: Como no Voucher o total_value foi zerado para o caixa, aqui você pode somar
        // o subtotal dos itens se quiser ver o valor bruto, ou apenas contar as ocorrências.
        // Se o total_value no banco estiver zerado, use a soma dos itens:
        // Recupera os IDs para o cálculo detalhado
        $orderVoucherIds = $queryVouchersOrders->pluck('id');
        $saleVoucherIds = $queryVouchersSales->pluck('id');

        // Para Mesas (Orders), somamos o subtotal que existe lá
        $valorVoucherMesas = BarOrderItem::whereIn('bar_order_id', $orderVoucherIds)->sum('subtotal');

        // Para Balcão (Sales), calculamos manual: Quantidade x Preço Unitário
        // Isso evita o erro de "Column not found: subtotal"
        $valorVoucherBalcao = BarSaleItem::whereIn('bar_sale_id', $saleVoucherIds)
            ->get()
            ->sum(function ($item) {
                return $item->quantity * $item->unit_price;
            });

        $totalVouchersMes = $valorVoucherMesas + $valorVoucherBalcao;

        // --- 3. VOLUME DE ITENS (Tudo que saiu do estoque, inclusive cortesia) ---
        $allOrderIds = BarOrder::whereIn('status', ['paid', 'pago'])
            ->whereBetween('updated_at', [$startDate, $endDate])->pluck('id');
        $allSaleIds = BarSale::whereIn('status', ['paid', 'pago'])
            ->whereBetween('created_at', [$startDate, $endDate])->pluck('id');

        $totalItensMes = BarOrderItem::whereIn('bar_order_id', $allOrderIds)->sum('quantity')
            + BarSaleItem::whereIn('bar_sale_id', $allSaleIds)->sum('quantity');

        // --- 4. TICKET MÉDIO (Apenas sobre transações pagas para não distorcer a média) ---
        $totalTransacoesPagas = $queryOrders->count() + $querySales->count();
        $ticketMedio = $totalTransacoesPagas > 0 ? $faturamentoMensal / $totalTransacoesPagas : 0;

        // --- 5. SANGRIAS ---
        $querySangrias = BarCashMovement::where('type', 'sangria')
            ->whereBetween('created_at', [$startDate, $endDate]);
        if (!$isAdmin) $querySangrias->where('user_id', $user->id);

        $totalSangriasMes = $querySangrias->sum('amount');

        return view('bar.reports.index', compact(
            'faturamentoMensal',
            'totalVouchersMes',
            'totalItensMes',
            'ticketMedio',
            'totalSangriasMes',
            'mesReferencia'
        ));
    }

    /**
     * Relatório de Ranking de Produtos (Ajustado para Vouchers/Cortesias)
     */
    public function products(Request $request)
    {
        $mesReferencia = $request->input('mes_referencia', now()->format('Y-m'));
        $startDate = Carbon::parse($mesReferencia)->startOfMonth();
        $endDate = Carbon::parse($mesReferencia)->endOfMonth();

        $user = auth()->user();
        $isAdmin = in_array($user->role, ['admin', 'gestor']);

        // 1. Pegamos todas as ordens (Mesas) e vendas (PDV) finalizadas
        $orders = BarOrder::whereIn('status', ['paid', 'pago'])
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->with('items.product')
            ->get();

        $sales = BarSale::whereIn('status', ['paid', 'pago'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('items.product')
            ->get();

        // 2. Agrupamos os itens identificando o que é venda paga e o que é Voucher
        $rankingData = collect();

        // Processamento de Mesas
        foreach ($orders as $order) {
            $isVoucher = str_contains(strtoupper($order->payment_method), 'VOUCHER');
            foreach ($order->items as $item) {
                $this->aggregateItem($rankingData, $item, $isVoucher);
            }
        }

        // Processamento de Balcão (PDV)
        foreach ($sales as $sale) {
            $isVoucher = strtoupper($sale->payment_method) === 'VOUCHER';
            foreach ($sale->items as $item) {
                $this->aggregateItem($rankingData, $item, $isVoucher);
            }
        }

        // 3. Transformamos os dados brutos no Ranking final
        $ranking = $rankingData->map(function ($data) {
            $product = $data['product'];
            $purchasePrice = (float)($product->purchase_price ?? 0);
            $salePrice = (float)($product->sale_price ?? 0);

            // --- CÁLCULO FINANCEIRO REAL (Impactado pelos Vouchers) ---
            $faturamentoReal = (float)$data['paid_revenue'];
            // Custo total de TUDO que saiu do estoque (Pagas + Cortesias)
            $custoTotalEstoque = $purchasePrice * ($data['paid_qty'] + $data['voucher_qty']);
            // Lucro real no bolso (Ficará vermelho/negativo se houver muita cortesia)
            $totalProfit = $faturamentoReal - $custoTotalEstoque;

            // --- CÁLCULO TÉCNICO DE SAÚDE (Baseado no Preço de Cadastro) ---
            // Isso garante que o card de "Saúde do Mix" não fique negativo
            $marginTech = $salePrice > 0 ? (($salePrice - $purchasePrice) / $salePrice) * 100 : 0;

            return (object)[
                'product' => $product,
                'total_qty' => $data['paid_qty'] + $data['voucher_qty'],
                'total_paid_qty' => $data['paid_qty'],
                'total_voucher_qty' => $data['voucher_qty'],
                'total_revenue' => $faturamentoReal,
                'total_profit' => $totalProfit,
                'margin_percent' => $marginTech
            ];
        })->sortByDesc('total_qty');





        return view('bar.reports.products', compact('ranking', 'mesReferencia'));
    }


    /**
     * Função Auxiliar para agregar quantidades e valores por produto
     */
    private function aggregateItem(&$collection, $item, $isVoucher)
    {
        $id = $item->bar_product_id;

        if (!$collection->has($id)) {
            $collection->put($id, [
                'product' => $item->product,
                'paid_qty' => 0,
                'voucher_qty' => 0,
                'paid_revenue' => 0,
            ]);
        }

        $current = $collection->get($id);

        if ($isVoucher) {
            $current['voucher_qty'] += $item->quantity;
        } else {
            $current['paid_qty'] += $item->quantity;
            // Usa o preço registrado no momento da venda para precisão histórica
            $current['paid_revenue'] += $item->quantity * ($item->price_at_sale ?? $item->unit_price ?? 0);
        }

        $collection->put($id, $current);
    }

    /**
     * AUDITORIA DE FECHAMENTO DE CAIXA (Sincronizada com Fluxo de Caixa)
     */
    public function cashier(Request $request)
    {
        $mesReferencia = $request->input('mes_referencia', now()->format('Y-m'));
        $startDate = Carbon::parse($mesReferencia)->startOfMonth();
        $endDate = Carbon::parse($mesReferencia)->endOfMonth();

        $sessoes = BarCashSession::with('user')
            ->whereBetween('opened_at', [$startDate, $endDate])
            ->orderBy('opened_at', 'desc')->get();

        foreach ($sessoes as $s) {
            $movs = BarCashMovement::where('bar_cash_session_id', $s->id)->get();
            $s->vendas_turno = $movs->where('type', 'venda')->sum('amount') - $movs->where('type', 'estorno')->sum('amount');
        }

        return view('bar.reports.cashier', compact('sessoes', 'mesReferencia'));
    }

    /**
     * RESUMO DE VENDAS DIÁRIAS COM CÁLCULO DE LUCRO REAL
     */
    public function daily(Request $request)
    {
        $mesReferencia = $request->input('mes_referencia', now()->format('Y-m'));
        $startDate = Carbon::parse($mesReferencia)->startOfMonth();
        $endDate = Carbon::parse($mesReferencia)->endOfMonth();

        // 1. Busca os Itens
        $orderItems = BarOrderItem::whereHas('order', fn($q) => $q->whereIn('status', ['paid', 'pago'])->whereBetween('updated_at', [$startDate, $endDate]))->with('product')->get();
        $saleItems = BarSaleItem::whereHas('sale', fn($q) => $q->whereIn('status', ['paid', 'pago'])->whereBetween('created_at', [$startDate, $endDate]))->with('product')->get();

        $datas = [];
        $periodo = new \DatePeriod($startDate, new \DateInterval('P1D'), $endDate->copy()->addDay());
        foreach ($periodo as $d) {
            $datas[$d->format('Y-m-d')] = ['mesas' => 0, 'pdv' => 0, 'lucro_mesas' => 0, 'lucro_pdv' => 0, 'descontos' => 0];
        }

        // 2. Processa itens de Mesas (Soma o valor BRUTO primeiro)
        foreach ($orderItems as $i) {
            $dia = $i->updated_at->format('Y-m-d');
            if (isset($datas[$dia])) {
                $venda = $i->subtotal;
                $custo = ($i->product->purchase_price ?? 0) * $i->quantity;
                $datas[$dia]['mesas'] += $venda;
                $datas[$dia]['lucro_mesas'] += ($venda - $custo);
            }
        }

        // 3. Processa itens de PDV (Soma o valor BRUTO primeiro)
        foreach ($saleItems as $i) {
            $dia = $i->created_at->format('Y-m-d');
            if (isset($datas[$dia])) {
                $venda = $i->quantity * ($i->price_at_sale ?? $i->unit_price ?? 0);
                $custo = ($i->product->purchase_price ?? 0) * $i->quantity;
                $datas[$dia]['pdv'] += $venda;
                $datas[$dia]['lucro_pdv'] += ($venda - $custo);
            }
        }

        // 🎯 4. AJUSTE DE DESCONTOS (Lógica de Diferença para evitar erro de SQL)
        foreach ($datas as $data => $valores) {
            // Descontos em Mesas (usa a coluna discount_value se existir, senão calcula)
            $ordens = BarOrder::whereIn('status', ['paid', 'pago'])->whereDate('updated_at', $data)->get();
            $descMesas = 0;
            foreach ($ordens as $o) {
                // Se você tem a coluna na tabela de ordens, usamos ela, senão calculamos a diferença
                $descMesas += $o->discount_value ?? ($o->items->sum('subtotal') - $o->total_value);
            }

            // Descontos em PDV (Cálculo por diferença pura para evitar erro de coluna inexistente)
            $vendasPDV = BarSale::whereIn('status', ['paid', 'pago'])->whereDate('created_at', $data)->with('items')->get();
            $descPDV = 0;
            foreach ($vendasPDV as $v) {
                $brutoVenda = $v->items->sum(fn($item) => $item->quantity * ($item->price_at_sale ?? $item->unit_price ?? 0));
                $pagoReal = (float)$v->total_value;
                if ($brutoVenda > $pagoReal) {
                    $descPDV += ($brutoVenda - $pagoReal);
                }
            }

            $totalDesc = $descMesas + $descPDV;

            if ($totalDesc > 0.01) {
                $datas[$data]['mesas'] -= $descMesas;
                $datas[$data]['pdv'] -= $descPDV;
                $datas[$data]['lucro_mesas'] -= $descMesas;
                $datas[$data]['lucro_pdv'] -= $descPDV;
                $datas[$data]['descontos'] = $totalDesc;
            }
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

    public function operators(Request $request)
    {
        // 📅 Filtros de Data (Início e Fim do mês por padrão)
        $start = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $end = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));
        $search = $request->get('search');

        $query = \App\Models\Bar\BarCashMovement::with('user')
            ->whereBetween('created_at', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->whereIn('type', ['venda', 'estorno']);

        // 🔍 Filtro por Nome do Operador
        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $vendasPorOperador = $query->select(
            'user_id',
            \DB::raw("SUM(CASE WHEN type = 'venda' THEN amount ELSE 0 END) as total_bruto"),
            \DB::raw("SUM(CASE WHEN type = 'estorno' THEN amount ELSE 0 END) as total_estornado"),
            \DB::raw("COUNT(CASE WHEN type = 'venda' THEN 1 END) as qtd_vendas")
        )
            ->groupBy('user_id')
            ->get()
            ->map(function ($item) {
                $item->faturamento_liquido = $item->total_bruto - $item->total_estornado;
                return $item;
            })
            ->sortByDesc('faturamento_liquido');

        return view('bar.reports.operators', compact('vendasPorOperador', 'start', 'end', 'search'));
    }
}
