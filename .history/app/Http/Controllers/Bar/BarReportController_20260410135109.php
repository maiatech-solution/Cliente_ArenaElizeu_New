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
        // 1. Filtros de Período e Permissões
        $mesReferencia = $request->input('mes_referencia', now()->format('Y-m'));
        $startDate = Carbon::parse($mesReferencia)->startOfMonth();
        $endDate = Carbon::parse($mesReferencia)->endOfMonth();

        $user = auth()->user();
        $isAdmin = in_array($user->role, ['admin', 'gestor']);

        // --- 1. FATURAMENTO REAL (Dinheiro que entrou, ignorando Vouchers) ---
        $queryOrders = BarOrder::whereIn('status', ['paid', 'pago'])
            ->where('payment_method', 'not like', '%VOUCHER%')
            ->whereBetween('updated_at', [$startDate, $endDate]);

        $querySales = BarSale::whereIn('status', ['paid', 'pago'])
            ->where('payment_method', 'not like', '%VOUCHER%')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if (!$isAdmin) {
            $queryOrders->where('user_id', $user->id);
            $querySales->where('user_id', $user->id);
        }

        $faturamentoMensal = $queryOrders->sum('total_value') + $querySales->sum('total_value');

        // --- 2. CÁLCULO DE VOUCHERS (Valor que você "deu" em cortesia) ---
        $queryVouchersOrders = BarOrder::whereIn('status', ['paid', 'pago'])
            ->where('payment_method', 'like', '%VOUCHER%')
            ->whereBetween('updated_at', [$startDate, $endDate]);

        $queryVouchersSales = BarSale::whereIn('status', ['paid', 'pago'])
            ->where('payment_method', 'like', '%VOUCHER%')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if (!$isAdmin) {
            $queryVouchersOrders->where('user_id', $user->id);
            $queryVouchersSales->where('user_id', $user->id);
        }

        $orderVoucherIds = $queryVouchersOrders->pluck('id');
        $saleVoucherIds = $queryVouchersSales->pluck('id');

        // Valor de face das Mesas
        $valorVoucherMesas = BarOrderItem::whereIn('bar_order_id', $orderVoucherIds)->sum('subtotal');

        // Valor de face do Balcão (Usando a coluna correta 'price_at_sale')
        $valorVoucherBalcao = BarSaleItem::whereIn('bar_sale_id', $saleVoucherIds)
            ->select(DB::raw('SUM(quantity * COALESCE(price_at_sale, 0)) as total'))
            ->value('total') ?? 0;

        $totalVouchersMes = $valorVoucherMesas + $valorVoucherBalcao;

        // --- 3. VOLUME DE ITENS (Tudo que saiu do estoque) ---
        $totalItensMes = BarOrderItem::whereIn('bar_order_id', function ($q) use ($startDate, $endDate) {
            $q->select('id')->from('bar_orders')->whereIn('status', ['paid', 'pago'])->whereBetween('updated_at', [$startDate, $endDate]);
        })->sum('quantity') +
            BarSaleItem::whereIn('bar_sale_id', function ($q) use ($startDate, $endDate) {
                $q->select('id')->from('bar_sales')->whereIn('status', ['paid', 'pago'])->whereBetween('created_at', [$startDate, $endDate]);
            })->sum('quantity');

        // --- 4. TICKET MÉDIO (Baseado no faturamento real) ---
        $totalTransacoesPagas = $queryOrders->count() + $querySales->count();
        $ticketMedio = $totalTransacoesPagas > 0 ? $faturamentoMensal / $totalTransacoesPagas : 0;

        // --- 5. SANGRIAS ---
        $totalSangriasMes = BarCashMovement::where('type', 'sangria')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when(!$isAdmin, fn($q) => $q->where('user_id', $user->id))
            ->sum('amount');

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
            ->when(!$isAdmin, fn($q) => $q->where('user_id', $user->id))
            ->with('items.product')
            ->get();

        $sales = BarSale::whereIn('status', ['paid', 'pago'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when(!$isAdmin, fn($q) => $q->where('user_id', $user->id))
            ->with('items.product')
            ->get();

        // 2. Agrupamos os itens identificando o que é venda paga e o que é Voucher
        $rankingData = collect();

        // Processamento de Mesas
        foreach ($orders as $order) {
            $isVoucher = str_contains(strtolower($order->payment_method ?? ''), 'voucher');
            foreach ($order->items as $item) {
                $this->aggregateItem($rankingData, $item, $isVoucher);
            }
        }

        // Processamento de Balcão (PDV)
        foreach ($sales as $sale) {
            $isVoucher = str_contains(strtolower($sale->payment_method ?? ''), 'voucher');
            foreach ($sale->items as $item) {
                $this->aggregateItem($rankingData, $item, $isVoucher);
            }
        }

        // 3. Transformamos os dados brutos no Ranking final
        $ranking = $rankingData->map(function ($data) {
            $product = $data['product'];
            $purchasePrice = (float)($product->purchase_price ?? 0);
            $salePrice = (float)($product->sale_price ?? 0);

            // --- MÉTRICAS DE VOLUME ---
            $totalSaidas = $data['paid_qty'] + $data['voucher_qty'];

            // --- CÁLCULO FINANCEIRO REAL (Impactado pelos Vouchers) ---
            $faturamentoReal = (float)$data['paid_revenue'];

            // Custo total de TUDO que saiu do estoque (Pagas + Cortesias)
            $custoTotalEstoque = $purchasePrice * $totalSaidas;

            // Lucro real no bolso (Ficará vermelho/negativo se houver muita cortesia)
            $totalProfit = $faturamentoReal - $custoTotalEstoque;

            // --- CÁLCULO DE INVESTIMENTO ---
            $investimentoCortesia = $data['voucher_qty'] * $salePrice;

            // --- CÁLCULO TÉCNICO DE SAÚDE (Baseado no Preço de Cadastro) ---
            $marginTech = $salePrice > 0 ? (($salePrice - $purchasePrice) / $salePrice) * 100 : 0;

            return (object)[
                'product' => $product,
                'total_qty' => $totalSaidas,
                'total_paid_qty' => $data['paid_qty'],
                'total_voucher_qty' => $data['voucher_qty'],
                'total_revenue' => $faturamentoReal,
                'total_profit' => $totalProfit,
                'investimento_cortesia' => $investimentoCortesia,
                'margin_percent' => $marginTech,
                'is_critical' => ($faturamentoReal > 0 && $totalProfit <= 0) // Alerta se as cortesias "comeram" o lucro
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

            // 🚀 PRECISÃO HISTÓRICA:
            // Tenta pegar o preço salvo na venda, se não houver, usa o unit_price da tabela de itens,
            // e como última garantia, o preço atual do cadastro do produto.
            $precoPraticado = $item->price_at_sale ?? $item->unit_price ?? ($item->product->sale_price ?? 0);

            $current['paid_revenue'] += $item->quantity * $precoPraticado;
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

        // 1. Buscamos as sessões com os movimentos já carregados (Eager Loading)
        // Isso evita o problema de performance (N+1) que existia no foreach
        $sessoes = BarCashSession::with(['user', 'movements'])
            ->whereBetween('opened_at', [$startDate, $endDate])
            ->orderBy('opened_at', 'desc')
            ->get();

        // Métodos que REALMENTE trazem dinheiro físico ou digital (Exclui Voucher/Cortesia)
        $metodosFinanceiros = ['dinheiro', 'pix', 'debito', 'credito', 'cartao', 'misto', 'crédito', 'débito'];

        foreach ($sessoes as $s) {
            // Filtramos os movimentos da sessão em memória (muito mais rápido que nova query)
            $movs = $s->movements;

            // 💰 Vendas Reais (Dinheiro/Pix/Cartão)
            $vendasReais = $movs->where('type', 'venda')
                ->filter(fn($m) => in_array(strtolower($m->payment_method), $metodosFinanceiros))
                ->sum('amount');

            // 🔙 Estornos Financeiros
            $estornosReais = $movs->where('type', 'estorno')
                ->filter(fn($m) => in_array(strtolower($m->payment_method), $metodosFinanceiros))
                ->sum('amount');

            // ➕ Reforços (Entrada de troco)
            $reforcos = $movs->where('type', 'reforco')->sum('amount');

            // ➖ Sangrias (Retiradas para pagamento ou segurança)
            $sangrias = $movs->where('type', 'sangria')->sum('amount');

            // 🎯 SALDO ESPERADO EM CAIXA
            // Vendas - Estornos + Reforços - Sangrias
            // Nota: O valor de abertura (troco inicial) deve ser somado se não estiver nos movimentos como 'reforco'
            $s->vendas_turno = $vendasReais - $estornosReais;
            $s->saldo_final_esperado = ($vendasReais - $estornosReais) + $reforcos - $sangrias;

            // Volume de Vouchers (Apenas para informação na listagem, se quiser exibir)
            $s->total_vouchers = $movs->where('type', 'venda')
                ->filter(fn($m) => str_contains(strtolower($m->payment_method), 'voucher'))
                ->sum('amount');
        }

        return view('bar.reports.cashier', compact('sessoes', 'mesReferencia'));
    }

    /**
     * RESUMO DE VENDAS DIÁRIAS COM CÁLCULO DE LUCRO REAL
     */
    public function daily(Request $request)
    {
        $mesReferencia = $request->input('mes_referencia', now()->format('Y-m'));
        $startDate = \Carbon\Carbon::parse($mesReferencia)->startOfMonth();
        $endDate = \Carbon\Carbon::parse($mesReferencia)->endOfMonth();

        // 1. Inicializa o array com todos os dias do mês
        $datas = [];
        $periodo = new \DatePeriod($startDate, new \DateInterval('P1D'), $endDate->copy()->addDay());
        foreach ($periodo as $d) {
            $datas[$d->format('Y-m-d')] = [
                'mesas' => 0,
                'pdv' => 0,
                'lucro_mesas' => 0,
                'lucro_pdv' => 0,
                'descontos' => 0,
                'vouchers' => 0
            ];
        }

        // 2. Busca itens de Mesas (BarOrder)
        $orderItems = BarOrderItem::whereHas('order', function ($q) use ($startDate, $endDate) {
            $q->whereIn('status', ['paid', 'pago'])->whereBetween('updated_at', [$startDate, $endDate]);
        })->with(['order', 'product'])->get();

        foreach ($orderItems as $i) {
            $dia = $i->updated_at->format('Y-m-d');
            if (isset($datas[$dia])) {
                $isVoucher = str_contains(strtolower($i->order->payment_method ?? ''), 'voucher');
                $venda = (float) $i->subtotal;
                $custo = (float) ($i->product->purchase_price ?? 0) * $i->quantity;

                if ($isVoucher) {
                    // Registra apenas o valor da cortesia para auditoria, sem negativar o lucro
                    $datas[$dia]['vouchers'] += $venda;
                    // $datas[$dia]['lucro_mesas'] -= $custo; // REMOVIDO para não negativar o lucro diário
                } else {
                    $datas[$dia]['mesas'] += $venda;
                    $datas[$dia]['lucro_mesas'] += ($venda - $custo);
                }
            }
        }

        // 3. Busca itens de PDV (BarSale)
        $saleItems = BarSaleItem::whereHas('sale', function ($q) use ($startDate, $endDate) {
            $q->whereIn('status', ['paid', 'pago'])->whereBetween('created_at', [$startDate, $endDate]);
        })->with(['sale', 'product'])->get();

        foreach ($saleItems as $i) {
            $dia = $i->created_at->format('Y-m-d');
            if (isset($datas[$dia])) {
                $isVoucher = str_contains(strtolower($i->sale->payment_method ?? ''), 'voucher');
                $venda = (float) $i->quantity * ($i->price_at_sale ?? $i->unit_price ?? 0);
                $custo = (float) ($i->product->purchase_price ?? 0) * $i->quantity;

                if ($isVoucher) {
                    // Registra apenas o valor da cortesia para auditoria
                    $datas[$dia]['vouchers'] += $venda;
                    // $datas[$dia]['lucro_pdv'] -= $custo; // REMOVIDO para não negativar o lucro diário
                } else {
                    $datas[$dia]['pdv'] += $venda;
                    $datas[$dia]['lucro_pdv'] += ($venda - $custo);
                }
            }
        }

        // 4. Ajuste Final de Descontos (Apenas para vendas reais)
        foreach ($datas as $data => $valores) {
            $ordens = BarOrder::whereIn('status', ['paid', 'pago'])
                ->where('payment_method', 'not like', '%voucher%')
                ->whereDate('updated_at', $data)->get();

            foreach ($ordens as $o) {
                $subtotalItens = (float) $o->items->sum('subtotal');
                $valorPago = (float) $o->total_value;
                $desconto = (float) ($o->discount_value > 0 ? $o->discount_value : ($subtotalItens - $valorPago));

                if ($desconto > 0.01) {
                    $datas[$data]['mesas'] -= $desconto;
                    $datas[$data]['lucro_mesas'] -= $desconto;
                    $datas[$data]['descontos'] += $desconto;
                }
            }
        }

        return view('bar.reports.daily', compact('datas', 'mesReferencia'));
    }

    /**
     * MEIOS DE PAGAMENTO
     */
    public function payments(Request $request)
    {
        $mesReferencia = $request->input('mes_referencia', now()->format('Y-m'));
        $startDate = \Carbon\Carbon::parse($mesReferencia)->startOfMonth();
        $endDate = \Carbon\Carbon::parse($mesReferencia)->endOfMonth();

        $user = auth()->user();
        $isAdmin = in_array($user->role, ['admin', 'gestor']);

        $query = DB::table('bar_cash_movements')
            ->where('type', 'venda')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if (!$isAdmin) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        // 1. Pegamos os dados agrupados para os cards e gráfico
        $pagamentos = (clone $query)->select(
            'payment_method',
            DB::raw('SUM(amount) as total'),
            DB::raw('COUNT(*) as qtd')
        )
            ->groupBy('payment_method')
            ->get();

        // 2. 🔍 NOVIDADE: Lista detalhada de TODOS os Vouchers para conferência
        $listaVouchers = DB::table('bar_cash_movements')
            ->where('type', 'venda')
            ->where('payment_method', 'like', '%voucher%')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when(!$isAdmin, fn($q) => $q->where('user_id', $user->id))
            ->orderBy('created_at', 'desc')
            ->get();

        $totalFinanceiroReal = $pagamentos->filter(function ($p) {
            return !str_contains(strtolower($p->payment_method), 'voucher');
        })->sum('total');

        return view('bar.reports.payments', compact('pagamentos', 'mesReferencia', 'totalFinanceiroReal', 'listaVouchers'));
    }

    /**
     * DESCONTOS E CANCELAMENTOS (LOGS)
     */
    public function cancelations(Request $request)
    {
        $mesReferencia = $request->input('mes_referencia', now()->format('Y-m'));
        $startDate = Carbon::parse($mesReferencia)->startOfMonth();
        $endDate = Carbon::parse($mesReferencia)->endOfMonth();

        // 1. Financeiro (Estornos de Caixa)
        // Monitora quem devolveu dinheiro para clientes e por qual motivo
        $cancelamentosFinanceiros = BarCashMovement::with(['user'])
            ->where('type', 'estorno')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();

        // 2. Prejuízo Real (Perdas/Vencidos/Quebras)
        // Itens que saíram do estoque mas não foram vendidos (o verdadeiro prejuízo)
        $perdasReais = BarStockMovement::with(['product', 'user'])
            ->where('type', 'perda')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();

        // 💰 Cálculo do prejuízo total em R$ baseado no preço de CUSTO
        $valorTotalPerdas = $perdasReais->sum(function ($movimento) {
            $custoUnitario = (float)($movimento->product->purchase_price ?? 0);
            return abs($movimento->quantity) * $custoUnitario;
        });

        // 3. Log de Retornos ao Estoque
        // Garante que, se uma venda foi cancelada, o produto "voltou pra prateleira"
        $retornosEstoque = BarStockMovement::with(['product', 'user'])
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
            'valorTotalPerdas'
        ));
    }

    /**
     * CONTROLE DE ESTOQUE (MOVIMENTAÇÕES) COM FILTROS
     */
    public function movements(Request $request)
    {
        // 1. Base da Query (Já trazemos as relações para evitar o problema de N+1)
        $query = BarStockMovement::with(['product.category', 'user']);

        // --- Aplicamos os Filtros ---
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }
        if ($request->filled('search')) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
            });
        }

        // 2. 🔥 CÁLCULO TOTAL (Para os cards do topo)
        $allMovementsInPeriod = $query->get();

        // 3. Paginação para a Tabela
        $movimentacoes = $query->orderBy('created_at', 'desc')
            ->paginate(30)
            ->withQueryString();

        // --- 🚀 NOVIDADE: Identificando Cortesias via Texto de Referência ---
        $movimentacoes->getCollection()->transform(function ($mov) {
            $isVoucher = false;

            // 1. Tenta extrair o número da Ref: XX da descrição
            if (preg_match('/Ref:\s*(\d+)/i', $mov->description, $matches)) {
                $saleId = $matches[1];

                // 2. Busca a venda pelo ID extraído
                $venda = \App\Models\Bar\BarSale::find($saleId);

                // 3. Checa se o pagamento foi Voucher
                $isVoucher = $venda && str_contains(strtolower($venda->payment_method ?? ''), 'voucher');
            }

            // 4. Fallback caso o texto já diga "voucher" (lançamentos manuais)
            if (!$isVoucher && str_contains(strtolower($mov->description ?? ''), 'voucher')) {
                $isVoucher = true;
            }

            $mov->is_voucher = $isVoucher;
            return $mov;
        });

        // 4. Inventário Atual
        $inventorySummary = \App\Models\Bar\BarProduct::with('category')
            ->orderBy('stock_quantity', 'asc')
            ->get();

        return view('bar.reports.movements', compact('movimentacoes', 'inventorySummary', 'allMovementsInPeriod'));
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
                // 🎯 Ajuste: Tenta quantity ou qtd / unit_price ou price_at_sale
                $qtd = $item->quantity ?? $item->qtd ?? 0;
                $precoUnitario = $item->price_at_sale ?? $item->unit_price ?? $item->price ?? 0;

                $valorItem = $qtd * $precoUnitario;
                $subtotalBruto += $valorItem;

                return [
                    'nome'     => $item->product->name ?? 'Produto',
                    'qtd'      => $qtd,
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

    /**
     * VENDAS POR OPERADORES
     */
    public function operators(Request $request)
    {
        // 1. Filtros de Data
        $start = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $end = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));
        $search = $request->get('search');

        // Métodos que trazem dinheiro real (Lógica consistente com o resto do sistema)
        $metodosFinanceiros = ['dinheiro', 'pix', 'debito', 'credito', 'cartao', 'misto', 'crédito', 'débito'];

        // 2. Query Principal
        $query = BarCashMovement::with('user')
            ->whereBetween('created_at', [$start . ' 00:00:00', $end . ' 23:59:59']);

        // 🔍 Filtro por Nome do Operador
        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // 📊 Agrupamento com separação de Dinheiro Real vs Vouchers
        $vendasPorOperador = $query->select(
            'user_id',
            // Total que entrou no caixa (apenas métodos financeiros)
            DB::raw("SUM(CASE WHEN type = 'venda' AND payment_method IN ('" . implode("','", $metodosFinanceiros) . "') THEN amount ELSE 0 END) as total_financeiro"),

            // Total de Vouchers (Cortesias que este operador emitiu)
            DB::raw("SUM(CASE WHEN type = 'venda' AND payment_method LIKE '%voucher%' THEN amount ELSE 0 END) as total_vouchers"),

            // Estornos financeiros
            DB::raw("SUM(CASE WHEN type = 'estorno' THEN amount ELSE 0 END) as total_estornado"),

            // Quantidade de transações realizadas
            DB::raw("COUNT(CASE WHEN type = 'venda' THEN 1 END) as qtd_vendas")
        )
            ->groupBy('user_id')
            ->get()
            ->map(function ($item) {
                // Cálculo da produtividade líquida
                $item->faturamento_liquido = $item->total_financeiro - $item->total_estornado;

                // Ticket Médio Real (Baseado no faturamento financeiro)
                $item->ticket_medio = $item->qtd_vendas > 0
                    ? $item->faturamento_liquido / $item->qtd_vendas
                    : 0;

                return $item;
            })
            ->sortByDesc('faturamento_liquido');

        return view('bar.reports.operators', compact('vendasPorOperador', 'start', 'end', 'search'));
    }

    public function vouchers(Request $request)
    {
        $start = $request->get('data_inicio', now()->startOfMonth()->format('Y-m-d'));
        $end = $request->get('data_fim', now()->format('Y-m-d'));
        $search = $request->get('search');

        $query = \App\Models\Bar\BarCashMovement::with(['user', 'barOrder'])
            ->where('payment_method', 'voucher')
            ->whereBetween('created_at', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('id', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($u) use ($search) {
                            $u->where('name', 'like', "%{$search}%");
                        });
                });
            });

        $cortesias = $query->orderBy('created_at', 'desc')->get()->map(function ($item) {
            $valorReal = 0;
            $vendaIdReal = null;

            // 1. Se for MESA (Relacionamento Direto)
            if ($item->bar_order_id) {
                $vendaMesa = \DB::table('bar_orders')->where('id', $item->bar_order_id)->first();
                if ($vendaMesa) {
                    $valorReal = $vendaMesa->total_value;
                    $vendaIdReal = $vendaMesa->id;
                }
            }

            // 2. Se for PDV (Pescaria por tempo/usuário)
            if ($valorReal <= 0) {
                $vendaPdv = \DB::table('bar_sales')
                    ->where('user_id', $item->user_id)
                    ->where('payment_method', 'voucher')
                    ->whereBetween('created_at', [
                        $item->created_at->subSeconds(2),
                        $item->created_at->addSeconds(2)
                    ])->first();

                if ($vendaPdv) {
                    $valorReal = $vendaPdv->total_value;
                    $vendaIdReal = $vendaPdv->id;
                }
            }

            // Injeta as propriedades que a View vai precisar
            $item->valor_real = ($valorReal > 0) ? $valorReal : ($item->amount ?? 0);
            $item->venda_id_real = $vendaIdReal;
            $item->origem_tipo = $item->bar_order_id ? 'mesa' : 'pdv';

            return $item;
        });

        $totalValor = $cortesias->sum('valor_real');
        $totalQtd = $cortesias->count();

        return view('bar.reports.vouchers', compact('cortesias', 'totalValor', 'totalQtd', 'start', 'end', 'search'));
    }
}
