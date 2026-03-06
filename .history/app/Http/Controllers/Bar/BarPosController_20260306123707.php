<?php

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Models\Bar\BarProduct;
use App\Models\Bar\BarSale;
use App\Models\Bar\BarSaleItem;
use App\Models\Bar\BarCategory;
use App\Models\Bar\BarCashSession;    // Importado
use App\Models\Bar\BarCashMovement;   // Importado
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BarPosController extends Controller
{
    public function index()
    {
        $products = BarProduct::where('is_active', true)
            ->where(function ($query) {
                $query->where('stock_quantity', '>', 0)
                    ->orWhere('manage_stock', false);
            })
            ->orderBy('name')
            ->get();

        $categories = BarCategory::orderBy('name')->get();

        return view('bar.pos.index', compact('products', 'categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'payments' => 'required|array',
            'total_value' => 'required|numeric'
        ]);

        try {
            return DB::transaction(function () use ($request) {

                // 1. 🛡️ BUSCAR SESSÃO ESPECÍFICA DO USUÁRIO LOGADO
                // Alterado de first() para garantir que a venda caia no caixa de QUEM está vendendo
                $session = BarCashSession::where('status', 'open')
                    ->where('user_id', auth()->id())
                    ->first();

                if (!$session) {
                    throw new \Exception("Você não possui um caixa aberto! Por favor, abra o seu turno primeiro.");
                }

                $dataAbertura = \Carbon\Carbon::parse($session->opened_at)->format('Y-m-d');
                $hoje = date('Y-m-d');

                if ($dataAbertura !== $hoje) {
                    throw new \Exception("⚠️ SEU CAIXA VENCEU: O seu turno aberto é de um dia anterior. Encerre-o e abra um novo.");
                }

                $metodoFinal = count($request->payments) > 1 ? 'misto' : $request->payments[0]['method'];

                // 2. Criar a Venda vinculada ao caixa do usuário
                $sale = new BarSale();
                $sale->user_id = auth()->id();
                $sale->total_value = $request->total_value;
                $sale->payment_method = $metodoFinal;
                $sale->status = 'pago';
                $sale->bar_cash_session_id = $session->id; // Vincula ao ID da sessão do operador
                $sale->save();

                // Incrementa o faturamento apenas na sessão deste usuário
                $session->increment('total_vendas_sistema', $request->total_value);

                // 3. Processar Itens e Estoque (Mantendo sua lógica de Combos)
                foreach ($request->items as $item) {
                    $product = BarProduct::with('compositions.product')->findOrFail($item['id']);

                    if ($product->is_combo) {
                        foreach ($product->compositions as $comp) {
                            $filho = $comp->product;
                            $necessario = $comp->quantity * $item['quantity'];

                            if ($filho && $filho->manage_stock && $filho->stock_quantity < $necessario) {
                                throw new \Exception("Estoque insuficiente para compor o combo! Falta: {$filho->name}");
                            }
                        }
                    } else {
                        if ($product->manage_stock && $product->stock_quantity < $item['quantity']) {
                            throw new \Exception("Estoque insuficiente para: {$product->name}");
                        }
                    }

                    BarSaleItem::create([
                        'bar_sale_id' => $sale->id,
                        'bar_product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'price_at_sale' => $product->sale_price
                    ]);

                    $product->baixarEstoque($item['quantity'], $sale->id);
                }

                // 4. INTEGRAÇÃO INDIVIDUAL COM O CAIXA
                foreach ($request->payments as $pay) {
                    BarCashMovement::create([
                        'bar_cash_session_id' => $session->id, // Caixa do operador atual
                        'user_id'             => auth()->id(),
                        'bar_sale_id'         => $sale->id,
                        'type'                => 'venda',
                        'payment_method'      => $pay['method'],
                        'amount'              => $pay['value'],
                        'description'         => "Venda Direta PDV #{$sale->id} (Operador: " . auth()->user()->name . ")"
                    ]);

                    // Atualiza a gaveta física apenas se for dinheiro
                    if ($pay['method'] === 'dinheiro') {
                        $session->increment('expected_balance', $pay['value']);
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Venda finalizada no seu caixa com sucesso!'
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function painel()
    {
        return view('bar.pos.painel');
    }
}
