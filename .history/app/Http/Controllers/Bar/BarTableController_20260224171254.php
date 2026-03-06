<?php

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Models\Bar\BarTable;
use App\Models\Bar\BarOrder;
use App\Models\Bar\BarOrderItem;
use App\Models\Bar\BarProduct;
use App\Models\Bar\BarCategory;
use App\Models\Bar\BarStockMovement;
use App\Models\Bar\BarCashSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BarTableController extends Controller
{
    /**
     * Exibe o Mapa de Mesas
     */
    public function index()
    {
        // O index agora esconde APENAS o que for 'inactive'.
        $tables = BarTable::where('status', '!=', 'inactive')
            ->orderByRaw('CAST(identifier AS UNSIGNED) ASC')
            ->get();

        // 🛡️ DETECÇÃO DE CAIXA VENCIDO (Para alimentar o banner e os botões cinzas no Front)
        $openSession = BarCashSession::where('status', 'open')->first();
        $caixaVencido = false;

        if ($openSession) {
            $dataAbertura = Carbon::parse($openSession->opened_at)->startOfDay();
            $hoje = Carbon::today();

            if ($dataAbertura->lt($hoje)) {
                $caixaVencido = true;
            }
        }

        return view('bar.tables.index', compact('tables', 'caixaVencido', 'openSession'));
    }

    /**
     * Sincroniza a quantidade de mesas (Cria ou oculta mesas extras)
     */
    public function sync(Request $request)
    {
        $request->validate(['total_tables' => 'required|integer|min:1']);
        $totalDesejado = (int) $request->total_tables;

        // 1. Identifica a maior mesa já criada (pelo número, não por contagem)
        $maxAtual = BarTable::max(DB::raw('CAST(identifier AS UNSIGNED)')) ?: 0;

        // 2. Cria novas mesas APENAS se o número solicitado for maior que o histórico
        if ($totalDesejado > $maxAtual) {
            for ($i = $maxAtual + 1; $i <= $totalDesejado; $i++) {
                BarTable::create([
                    'identifier' => $i,
                    'status' => 'available'
                ]);
            }
        }

        // 3. Ativa mesas que estavam ocultas ou reservadas dentro do novo limite
        BarTable::whereRaw('CAST(identifier AS UNSIGNED) <= ?', [$totalDesejado])
            ->whereIn('status', ['inactive', 'reserved'])
            ->update(['status' => 'available']);

        // 4. 🛡️ Desativa mesas fora do limite (Apenas se NÃO estiverem ocupadas)
        // Forçamos o valor com aspas simples diretamente no SQL para evitar o erro de truncamento
        DB::table('bar_tables')
            ->whereRaw('CAST(identifier AS UNSIGNED) > ?', [$totalDesejado])
            ->where('status', '!=', 'occupied')
            ->update([
                'status' => DB::raw("'inactive'"), // 🔥 Note as aspas simples dentro das duplas: "'valor'"
                'updated_at' => now()
            ]);

        // 5. Verifica se alguma mesa ficou visível por estar ocupada mesmo acima do limite
        $ocupadasForaLimite = BarTable::whereRaw('CAST(identifier AS UNSIGNED) > ?', [$totalDesejado])
            ->where('status', 'occupied')
            ->count();

        if ($ocupadasForaLimite > 0) {
            return back()->with('warning', "Layout atualizado! Porém, {$ocupadasForaLimite} mesa(s) acima do limite continuam visíveis por estarem com comandas abertas.");
        }

        return back()->with('success', 'Layout do salão atualizado!');
    }

    /**
     * Ativa/Desativa Mesa (Usa 'reserved' como bloqueio)
     */
    public function toggleStatus($id)
    {
        $table = BarTable::findOrFail($id);

        if ($table->status === 'occupied') {
            return back()->with('error', 'Mesa ocupada não pode ser alterada!');
        }

        $table->status = ($table->status === 'reserved') ? 'available' : 'reserved';
        $table->save();

        return back();
    }

    /**
     * Abre a Comanda (Com trava de segurança de data)
     */
    public function open($id)
    {
        $table = BarTable::findOrFail($id);

        // 1. BUSCA A SESSÃO DE CAIXA ATIVA
        $session = BarCashSession::where('status', 'open')->first();

        // 2. 🛡️ VALIDAÇÃO DE CAIXA: Verifica se existe caixa aberto
        if (!$session) {
            return back()->with('error', '⚠️ Bloqueio: Não existe um caixa aberto! Por favor, abra o turno na Gestão de Caixa antes de atender mesas.');
        }

        // 3. 🛡️ VALIDAÇÃO DE DATA: Impede abrir mesa com caixa de ontem
        $dataAbertura = Carbon::parse($session->opened_at)->format('Y-m-d');
        $hoje = date('Y-m-d');

        if ($dataAbertura !== $hoje) {
            return back()->with('error', '⚠️ ATENÇÃO: O caixa aberto pertence ao dia anterior (' . Carbon::parse($session->opened_at)->format('d/m') . '). Você deve encerrar o turno antigo e abrir um novo para hoje antes de iniciar novos atendimentos.');
        }

        // 4. VERIFICA DISPONIBILIDADE DA MESA
        if ($table->status !== 'available') {
            return back()->with('error', 'Esta mesa não está disponível.');
        }

        try {
            return DB::transaction(function () use ($table) {
                // Cria a ordem vinculada à mesa
                BarOrder::create([
                    'bar_table_id' => $table->id,
                    'user_id' => auth()->id(),
                    'status' => 'open',
                    'total_value' => 0.00
                ]);

                // Atualiza o status da mesa para ocupada
                $table->status = 'occupied';
                $table->save();

                return back()->with('success', "Mesa {$table->identifier} aberta com sucesso!");
            });
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao abrir mesa: ' . $e->getMessage());
        }
    }

    /**
     * Exibe os itens da mesa
     */
    public function showOrder($id)
    {
        $table = BarTable::findOrFail($id);

        $order = BarOrder::with('items.product')
            ->where('bar_table_id', $table->id)
            ->where('status', 'open')
            ->firstOrFail();

        $products = BarProduct::orderBy('name')->get();
        $categories = BarCategory::all();

        return view('bar.tables.show', compact('table', 'order', 'products', 'categories'));
    }

    /**
     * Lança item na comanda via AJAX (Agora com Trava de Combo 🛡️)
     */
    public function addItem(Request $request, $orderId)
    {
        try {
            return DB::transaction(function () use ($request, $orderId) {
                $order = BarOrder::findOrFail($orderId);
                // Carregamos o produto com as composições E o produto real (filho)
                $product = BarProduct::with('compositions.product')->findOrFail($request->product_id);
                $qty = $request->quantity ?? 1;

                // --- 🛡️ VALIDAÇÃO DE ESTOQUE UNIFICADA ---
                if ($product->is_combo) {
                    // Se for combo, verifica cada item da receita antes de lançar
                    foreach ($product->compositions as $comp) {
                        $filho = $comp->product;
                        $necessario = $comp->quantity * $qty;

                        if ($filho && $filho->manage_stock && $filho->stock_quantity < $necessario) {
                            throw new \Exception("Estoque insuficiente para o combo! Falta: {$filho->name} (Disponível: {$filho->stock_quantity})");
                        }
                    }
                } else {
                    // Se for simples, valida o próprio estoque
                    if ($product->manage_stock && $product->stock_quantity < $qty) {
                        throw new \Exception("Estoque insuficiente para: {$product->name} (Disponível: {$product->stock_quantity})");
                    }
                }
                // --- FIM DA VALIDAÇÃO ---

                $item = BarOrderItem::where('bar_order_id', $order->id)
                    ->where('bar_product_id', $product->id)
                    ->first();

                if ($item) {
                    $item->increment('quantity', $qty);
                    $item->update(['subtotal' => $item->quantity * $product->sale_price]);
                } else {
                    BarOrderItem::create([
                        'bar_order_id'   => $order->id,
                        'bar_product_id' => $product->id,
                        'quantity'       => $qty,
                        'unit_price'     => $product->sale_price,
                        'subtotal'       => $qty * $product->sale_price
                    ]);
                }

                $order->update(['total_value' => $order->items()->sum('subtotal')]);

                // Baixa o estoque (seja item simples ou combo)
                $product->baixarEstoque($qty, "Mesa #{$order->table->identifier} (Comanda #{$order->id})");

                return response()->json(['success' => true]);
            });
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove item da comanda (Atualizado para Combos 🔄)
     */
    public function removeItem($itemId)
    {
        try {
            return DB::transaction(function () use ($itemId) {
                $item = BarOrderItem::findOrFail($itemId);
                $order = $item->order;
                $product = $item->product;
                $quantidadeEstornada = $item->quantity;

                if ($product) {
                    // 🚀 Chama a inteligência de estorno do Model que criamos
                    $product->devolverEstoque($quantidadeEstornada, "Remoção Mesa #{$order->table->identifier}");
                }

                $item->delete();
                $order->update(['total_value' => $order->items()->sum('subtotal') ?? 0]);

                return back()->with('success', 'Item removido e estoque devolvido com sucesso!');
            });
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao remover item: ' . $e->getMessage());
        }
    }

    /**
     * 🏁 FINALIZAR MESA
     * Registra pagamentos, limpa a mesa e salva detalhes da venda na comanda.
     */
    public function closeOrder(Request $request, $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $table = BarTable::findOrFail($id);
            $session = BarCashSession::where('status', 'open')->first();

            // 🛡️ Validação de Segurança: Caixa
            if (!$session) {
                return redirect()->route('bar.tables.index')
                    ->with('error', '⚠️ Operação Bloqueada: Não há nenhum caixa aberto.');
            }

            // 🛡️ Validação de Segurança: Comanda
            $order = $table->orders()->where('status', 'open')->latest()->first();
            if (!$order) {
                return redirect()->route('bar.tables.index')->with('error', '⚠️ Nenhuma comanda ativa encontrada.');
            }

            // 💰 Cálculos de Valores
            $discountValue = (float)($request->discount_value ?? 0);
            $finalValue = (float)$order->total_value - $discountValue;

            // --- 💳 PROCESSAMENTO DOS PAGAMENTOS ---
            $pagamentosArray = json_decode($request->pagamentos, true);
            $nomesMetodos = [];

            if (is_array($pagamentosArray)) {
                foreach ($pagamentosArray as $pag) {
                    $valorItem = floatval($pag['valor'] ?? 0);

                    if ($valorItem > 0) {
                        // Formata o nome para salvar na string da comanda (Ex: DINHEIRO)
                        $nomesMetodos[] = mb_strtoupper($pag['metodo'], 'UTF-8');

                        // 1. Registra cada movimentação no Caixa (Histórico de Movimentos)
                        \App\Models\Bar\BarCashMovement::create([
                            'bar_cash_session_id' => $session->id,
                            'user_id'             => auth()->id(),
                            'bar_order_id'        => $order->id,
                            'type'                => 'venda',
                            'payment_method'      => $pag['metodo'],
                            'amount'              => $valorItem,
                            'description'         => "Venda Mesa #{$table->identifier}",
                        ]);

                        // 2. Atualiza saldo esperado se for Dinheiro
                        if (strtolower($pag['metodo']) == 'dinheiro') {
                            $session->increment('expected_balance', $valorItem);
                        }
                    }
                }
            }

            // Define a string que aparecerá no histórico (Ex: "PIX" ou "DINHEIRO, CARTÃO")
            $metodosString = !empty($nomesMetodos) ? implode(', ', array_unique($nomesMetodos)) : 'PAGO';

            // 📝 3. ATUALIZAÇÃO FINAL DA COMANDA (PERSISTÊNCIA)
            $order->status = 'paid';
            $order->payment_method = $metodosString;
            $order->customer_name = $request->customer_name;
            $order->customer_phone = $request->customer_phone;
            $order->discount_value = $discountValue;
            $order->total_value = $finalValue; // Salva o valor líquido (pago pelo cliente)
            $order->closed_at = now();
            $order->bar_cash_session_id = $session->id;

            // Salva de forma explícita para garantir a gravação no banco
            $order->save();

            // 🔥 4. ATUALIZAÇÃO DO FATURAMENTO DA SESSÃO
            $session->increment('total_vendas_sistema', $finalValue);

            // ✅ 5. LIBERA A MESA PARA O PRÓXIMO CLIENTE
            $table->update(['status' => 'available']);

            // 🖨️ 6. REDIRECIONAMENTO COM RECIBO (OPCIONAL)
            if ($request->print_coupon == "1") {
                return redirect()->route('bar.tables.receipt', $order->id)
                    ->with('show_success_modal', true)
                    ->with('success', 'Venda finalizada com sucesso!');
            }

            return redirect()->route('bar.tables.index')->with('success', 'Venda finalizada com sucesso!');
        });
    }

    /**
     * 🖨️ EXIBIR RECIBO PARA IMPRESSÃO (Com suporte a descontos)
     */
    public function printReceipt($orderId)
    {
        // 1. Carrega a ordem com os itens e a mesa
        $order = BarOrder::with(['items.product', 'table'])->findOrFail($orderId);

        // 2. Calcula o subtotal bruto (soma dos subtotais de cada item antes do desconto da ordem)
        // Usamos floatval para garantir que o cálculo seja numérico
        $subtotalBruto = $order->items->sum(function ($item) {
            return floatval($item->subtotal);
        });

        // 3. Retorna a view enviando o subtotal calculado
        return view('bar.tables.receipt', compact('order', 'subtotalBruto'));
    }

    public function painel()
    {
        return view('bar.tables.painel');
    }
}
