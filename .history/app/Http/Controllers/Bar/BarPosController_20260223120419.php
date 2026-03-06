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

                // 1. BUSCAR SESSÃO DE CAIXA ATIVA
                $session = BarCashSession::where('status', 'open')->first();

                if (!$session) {
                    throw new \Exception("Não existe um caixa aberto! Por favor, abra o caixa primeiro.");
                }

                $dataAbertura = \Carbon\Carbon::parse($session->opened_at)->format('Y-m-d');
                $hoje = date('Y-m-d');

                if ($dataAbertura !== $hoje) {
                    throw new \Exception("⚠️ CAIXA VENCIDO: O caixa aberto é de ontem. Encerre o turno e abra um novo.");
                }

                $metodoFinal = count($request->payments) > 1 ? 'misto' : $request->payments[0]['method'];

                // 2. Criar a Venda
                $sale = new BarSale();
                $sale->user_id = auth()->id();
                $sale->total_value = $request->total_value;
                $sale->payment_method = $metodoFinal;
                $sale->status = 'pago';
                $sale->bar_cash_session_id = $session->id;
                $sale->save();

                $session->increment('total_vendas_sistema', $request->total_value);

                // 3. Processar Itens e Estoque Inteligente 🚀
                foreach ($request->items as $item) {
                    // Carregamos o produto com as composições E os produtos filhos (importante carregar o childProduct)
                    $product = BarProduct::with('compositions.product')->findOrFail($item['id']);

                    // --- NOVA VALIDAÇÃO UNIFICADA ---
                    if ($product->is_combo) {
                        // Se for combo, varre os itens da receita
                        foreach ($product->compositions as $comp) {
                            $filho = $comp->product;
                            $necessario = $comp->quantity * $item['quantity'];

                            if ($filho && $filho->manage_stock && $filho->stock_quantity < $necessario) {
                                throw new \Exception("Estoque insuficiente para compor o combo! Falta: {$filho->name} (Precisa de {$necessario}, mas só tem {$filho->stock_quantity})");
                            }
                        }
                    } else {
                        // Se for simples, mantém sua lógica original
                        if ($product->manage_stock && $product->stock_quantity < $item['quantity']) {
                            throw new \Exception("Estoque insuficiente para: {$product->name} (Disponível: {$product->stock_quantity})");
                        }
                    }
                    // --- FIM DA VALIDAÇÃO ---

                    BarSaleItem::create([
                        'bar_sale_id' => $sale->id,
                        'bar_product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'price_at_sale' => $product->sale_price
                    ]);

                    // Ele vai baixar o estoque do item OU dos itens do combo automaticamente!
                    $product->baixarEstoque($item['quantity'], $sale->id);
                }

                // 4. INTEGRAÇÃO COM O CAIXA
                foreach ($request->payments as $pay) {
                    BarCashMovement::create([
                        'bar_cash_session_id' => $session->id,
                        'user_id'             => auth()->id(),
                        'bar_sale_id'         => $sale->id,
                        'type'                => 'venda',
                        'payment_method'      => $pay['method'],
                        'amount'              => $pay['value'],
                        'description'         => "Venda Direta PDV #{$sale->id}"
                    ]);

                    if ($pay['method'] === 'dinheiro') {
                        $session->increment('expected_balance', $pay['value']);
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Venda finalizada e estoque atualizado!'
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
