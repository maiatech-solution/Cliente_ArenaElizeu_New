<?php

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Models\Bar\BarSale;
use App\Models\Bar\BarOrder;
use App\Models\Bar\BarStockMovement;
use App\Models\Bar\BarCashSession;
use App\Models\Bar\BarCashMovement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class BarOrderController extends Controller
{
    /**
     * 🛒 HISTÓRICO PDV (Venda Direta / Balcão)
     */
    public function indexPdv(Request $request)
    {
        $query = BarSale::with(['items.product', 'user', 'cashSession']);

        if ($request->filled('id')) $query->where('id', $request->id);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('date')) $query->whereDate('updated_at', $request->date);

        $vendas = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        return view('bar.vendas.pdv', compact('vendas'));
    }

    public function cancelarPdv(Request $request, BarSale $sale)
    {
        // 1. Validar Supervisor (Mantido)
        $supervisor = User::where('email', $request->supervisor_email)->first();
        if (
            !$supervisor || !Hash::check($request->supervisor_password, $supervisor->password) ||
            !in_array($supervisor->role, ['admin', 'gestor'])
        ) {
            return back()->with('error', '❌ Autorização negada: Senha de gestor inválida.');
        }

        // 2. Trava de Caixa (AJUSTADA PARA MULTI-USUÁRIO) 🛡️
        $caixaAberto = BarCashSession::where('status', 'open')
            ->where('user_id', auth()->id()) // Filtra apenas o caixa do operador atual
            ->first();

        if (!$caixaAberto) {
            return back()->with('error', '❌ OPERAÇÃO BLOQUEADA: Você não possui um turno de caixa aberto para processar este estorno.');
        }

        // Agora o ID baterá corretamente, pois estamos comparando com o caixa do dono da venda
        if ($sale->bar_cash_session_id != $caixaAberto->id) {
            return back()->with('error', '❌ OPERAÇÃO BLOQUEADA: Venda pertence a outro turno ou operador.');
        }

        // Verifica se já está cancelada (Mantido)
        if (in_array($sale->status, ['cancelado', 'cancelled', 'anulada'])) {
            return back()->with('error', 'Esta venda já está cancelada.');
        }

        try {
            DB::transaction(function () use ($sale, $supervisor, $request, $caixaAberto) {

                // 3. Devolver Itens ao Estoque (Mantido)
                foreach ($sale->items as $item) {
                    if ($item->product) {
                        $item->product->devolverEstoque($item->quantity, "PDV #{$sale->id}");

                        \App\Models\Bar\BarStockMovement::create([
                            'bar_product_id' => $item->bar_product_id,
                            'user_id'        => auth()->id(),
                            'type'           => 'entrada',
                            'quantity'       => $item->quantity,
                            'description'    => "CANCELAMENTO PDV #{$sale->id}: Autorizado por {$supervisor->name}.",
                        ]);
                    }
                }

                // 4. Estorno Financeiro (Mantido)
                if ($sale->payment_method === 'dinheiro') {
                    $caixaAberto->decrement('expected_balance', $sale->total_value);
                }

                // 5. Registrar Movimentação no Caixa (Mantido)
                $motivoDesc = $request->reason ? " | MOTIVO: " . $request->reason : " | MOTIVO: Não informado";
                $authDesc = " | POR: " . $supervisor->name;

                BarCashMovement::create([
                    'bar_cash_session_id' => $caixaAberto->id,
                    'user_id'             => auth()->id(),
                    'bar_sale_id'         => $sale->id,
                    'type'                => 'estorno',
                    'payment_method'      => $sale->payment_method ?? 'misto',
                    'amount'              => $sale->total_value,
                    'description'         => "ESTORNO PDV #{$sale->id}" . $motivoDesc . $authDesc
                ]);

                // 6. Atualizar status da venda
                $sale->update(['status' => 'cancelado']);
            });

            return back()->with('success', "✅ Venda PDV #{$sale->id} anulada com sucesso!");
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao cancelar: ' . $e->getMessage());
        }
    }

    /**
     * 🍽️ HISTÓRICO MESAS (Comandas)
     */
    public function indexMesas(Request $request)
    {
        $query = BarOrder::with(['items.product', 'user', 'cashSession'])
            ->whereIn('status', ['paid', 'cancelled']);

        if ($request->filled('id')) $query->where('id', $request->id);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('date')) $query->whereDate('updated_at', $request->date);

        $vendas = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        return view('bar.vendas.mesas', compact('vendas'));
    }

    public function cancelarMesa(Request $request, BarOrder $order)
    {
        // 1. Validar Supervisor
        if (!$request->supervisor_email) {
            return back()->with('error', '❌ Erro técnico: O e-mail do supervisor não foi enviado pelo formulário.');
        }

        $supervisor = User::where('email', $request->supervisor_email)->first();

        // Validação tripla: Usuário existe? Senha bate? É admin/gestor?
        if (
            !$supervisor ||
            !Hash::check($request->supervisor_password, $supervisor->password) ||
            !in_array($supervisor->role, ['admin', 'gestor'])
        ) {
            return back()->with('error', '❌ Autorização negada: E-mail ou Senha de gestor inválidos.');
        }

        // 2. Trava de Caixa (AJUSTADA PARA MULTI-USUÁRIO) 🛡️
        // Buscamos o caixa aberto especificamente DESTE usuário logado
        $caixaAberto = BarCashSession::where('status', 'open')
            ->where('user_id', auth()->id())
            ->first();

        if (!$caixaAberto) {
            return back()->with('error', '❌ OPERAÇÃO BLOQUEADA: Você não possui um turno de caixa aberto para processar este estorno.');
        }

        // Verificamos se a comanda pertence ao turno ATUAL deste usuário
        if ($order->bar_cash_session_id != $caixaAberto->id) {
            return back()->with('error', '❌ OPERAÇÃO BLOQUEADA: Esta comanda pertence a outro turno ou operador.');
        }

        try {
            DB::transaction(function () use ($order, $supervisor, $request, $caixaAberto) {

                // 3. Devolver itens ao estoque (Inteligente: Trata Combo e Simples) 🔄
                foreach ($order->items as $item) {
                    $productId = $item->bar_product_id ?? $item->product_id;

                    if ($productId && $item->product) {
                        // 🚀 Chama o método do Model que devolve os "filhos" caso seja combo
                        $item->product->devolverEstoque($item->quantity, "MESA #{$order->id}");

                        // Registro de movimentação para auditoria de cancelamento
                        BarStockMovement::create([
                            'bar_product_id' => $productId,
                            'user_id'        => auth()->id(),
                            'type'           => 'input',
                            'quantity'       => $item->quantity,
                            'description'    => "CANCELAMENTO MESA #{$order->id}: Autorizado por {$supervisor->name}.",
                        ]);
                    }
                }

                // 4. Registrar Estorno no Caixa
                $motivoDesc = $request->reason ? " | MOTIVO: " . $request->reason : " | MOTIVO: Não informado";
                $authDesc = " | POR: " . $supervisor->name; // 🔐 Auditoria: Quem deu a senha

                BarCashMovement::create([
                    'bar_cash_session_id' => $caixaAberto->id,
                    'user_id'             => auth()->id(),
                    'bar_order_id'        => $order->id,
                    'type'                => 'estorno',
                    'payment_method'      => $order->payment_method ?? 'misto',
                    'amount'              => $order->total_value,
                    'description'         => "ESTORNO MESA #{$order->id}" . $motivoDesc . $authDesc
                ]);

                // 5. Atualizar status no banco
                $order->update(['status' => 'cancelled']);
            });

            return back()->with('success', "✅ Comanda #{$order->id} anulada com sucesso!");
        } catch (\Exception $e) {
            Log::error("Erro ao cancelar mesa #{$order->id}: " . $e->getMessage());
            return back()->with('error', 'Erro ao cancelar: ' . $e->getMessage());
        }
    }
}
