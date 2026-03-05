<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

// Modelos
use App\Models\Reserva;
use App\Models\User;
use App\Models\FinancialTransaction;
use App\Models\Cashier;

// Importação do Fiscal
use App\Http\Controllers\FinanceiroController;

class PaymentController extends Controller

{

    /**

     * Verifica e corrige reservas de No-Show (Integridade de Dados)

     */

    private function checkAndCorrectNoShowPaidAmounts()

    {

        $reservasToCorrect = Reserva::where('status', 'no_show')

            ->where('payment_status', 'unpaid')

            ->where('total_paid', '>', 0)

            ->where('final_price', '>', 0)

            ->get();



        if ($reservasToCorrect->isNotEmpty()) {

            DB::transaction(function () use ($reservasToCorrect) {

                foreach ($reservasToCorrect as $reserva) {

                    $reserva->total_paid = 0.00;

                    $reserva->final_price = 0.00;

                    $reserva->save();

                    Log::warning("CORREÇÃO: Reserva ID {$reserva->id} sincronizada.");
                }
            });
        }
    }



    /**
     * Dashboard de Caixa e Histórico
     */
    public function index(Request $request)
    {
        $this->checkAndCorrectNoShowPaidAmounts();

        $reservaIdParam = $request->input('reserva_id');
        $dateParam = $request->input('date');
        $selectedDateString = $dateParam ?? Carbon::today()->toDateString();
        $dateObject = Carbon::parse($selectedDateString);
        $selectedArenaId = $request->input('arena_id');
        $searchTerm = $request->input('search');
        $filterDebts = $request->input('filter') === 'debts';

        // 1. GLOBAL: Transações de todas as arenas (Para os cards laterais não zerarem)
        $allTransactionsOfDay = FinancialTransaction::whereDate('paid_at', $dateObject)->get();

        // 2. FILTRADO: Transações da arena selecionada (Para a listagem e saldo do card principal)
        $financialTransactions = FinancialTransaction::whereDate('paid_at', $dateObject)
            ->when($selectedArenaId, fn($q) => $q->where('arena_id', $selectedArenaId))
            ->with(['reserva', 'manager', 'payer', 'arena'])
            ->orderBy('paid_at', 'desc')
            ->get();

        // 3. SEGUNDO: Pegamos os IDs de reservas que movimentaram dinheiro hoje
        $reservaIdsComMovimentacaoHoje = $financialTransactions->pluck('reserva_id')->filter()->unique();

        // 4. TERCEIRO: Consulta de Reservas 🎯
        $query = Reserva::with(['user', 'arena', 'transactions']); // Adicionado eager loading de transactions

        if ($filterDebts) {
            $query->where('status', 'completed')->whereIn('payment_status', ['unpaid', 'partial']);
        } elseif ($reservaIdParam) {
            $query->where('id', $reservaIdParam);
        } else {
            $query->where(function ($q) use ($dateObject, $reservaIdsComMovimentacaoHoje) {
                $q->whereDate('date', $dateObject)
                    ->orWhereIn('id', $reservaIdsComMovimentacaoHoje);
            });
        }

        // Filtros de busca e arena
        if ($selectedArenaId) $query->where('arena_id', $selectedArenaId);
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('client_name', 'LIKE', "%$searchTerm%")
                    ->orWhere('client_contact', 'LIKE', "%$searchTerm%");
            });
        }

        $reservas = $query->whereNotNull('user_id')
            ->where('is_fixed', false)
            ->orderBy($filterDebts ? 'date' : 'start_time', 'asc')
            ->get();

        // 🚀 4.1 SINCRONIZAÇÃO FORÇADA (AUTO-CORREÇÃO)
        // Percorre as reservas carregadas e garante que o total_paid seja a soma real do extrato
        foreach ($reservas as $reserva) {
            $realPaid = (float) $reserva->transactions->sum('amount');
            if (round((float)$reserva->total_paid, 2) !== round($realPaid, 2)) {
                $reserva->total_paid = $realPaid;
                // Atualizamos no banco silenciosamente para não disparar observers desnecessários
                \DB::table('reservas')->where('id', $reserva->id)->update(['total_paid' => $realPaid]);
            }
        }

        // 5. Saldo Líquido Real (Desta arena ou Geral se não houver filtro)
        $totalRecebidoDiaLiquido = $financialTransactions->sum('amount');

        // 6. Lógica de Status do Caixa e Histórico
        $cashierRecord = Cashier::where('date', $selectedDateString)
            ->when($selectedArenaId, fn($q) => $q->where('arena_id', $selectedArenaId))
            ->first();
        $cashierStatus = $cashierRecord->status ?? 'open';

        $cashierHistory = Cashier::with(['user', 'arena'])
            ->when($selectedArenaId, fn($q) => $q->where('arena_id', $selectedArenaId))
            ->orderBy('date', 'desc')
            ->limit(10)
            ->get();

        // 7. Faturamento por Arena
        $arenasAtivas = \App\Models\Arena::all();
        $faturamentoPorArena = $arenasAtivas->map(function ($arena) use ($allTransactionsOfDay) {
            return (object)[
                'id'    => $arena->id,
                'name'  => $arena->name,
                'total' => $allTransactionsOfDay->where('arena_id', $arena->id)->sum('amount')
            ];
        });

        // 🚫 8. CORREÇÃO DO NO-SHOW: Conta os logs de falta no financeiro do dia
        $noShowCount = $allTransactionsOfDay
            ->when($selectedArenaId, fn($q) => $q->where('arena_id', $selectedArenaId))
            ->filter(function ($t) {
                return str_contains($t->description, 'Falta marcada') ||
                    str_contains($t->description, 'No-Show');
            })->count();

        return view('admin.payment.index', [
            'selectedDate'            => $selectedDateString,
            'reservas'                => $reservas,
            'financialTransactions'   => $financialTransactions,
            'totalRecebidoDiaLiquido' => $totalRecebidoDiaLiquido,
            'faturamentoPorArena'     => $faturamentoPorArena,
            'cashierStatus'           => $cashierStatus,
            'cashierHistory'          => $cashierHistory,
            'totalReservasDia'        => $reservas->count(),
            'totalPending'            => $reservas->whereIn('status', ['confirmed', 'pending'])->sum(fn($r) => max(0, ($r->final_price ?? $r->price) - $r->total_paid)),
            'noShowCount'             => $noShowCount,
            'totalAuthorizedDebt'     => $reservas->where('status', 'completed')->whereIn('payment_status', ['unpaid', 'partial'])->sum(fn($r) => max(0, ($r->final_price ?? $r->price) - $r->total_paid)),
        ]);
    }


    /**
     * 🎯 FECHAR CAIXA: Grava a auditoria no banco com cálculo automático de segurança por arena.
     */
    public function closeCash(Request $request)
    {
        $validated = $request->validate([
            'date'          => 'required|date',
            'actual_amount' => 'required|numeric',
            'arena_id'      => 'required|exists:arenas,id',
        ]);

        try {
            $date = $validated['date'];
            $arenaId = $validated['arena_id'];

            // --- 🛡️ VALIDAÇÃO PROFISSIONAL DE PENDÊNCIAS ---
            // Só travamos se houver reservas CONFIRMADAS ou PENDENTES que JÁ TERMINARAM.
            // Se for "Dívida Ativa" (Partial) ou "Pago" (Paid), o sistema permite o fechamento.
            $agora = now();
            $pendenciasCriticas = \App\Models\Reserva::where('arena_id', $arenaId)
                ->whereDate('date', $date)
                ->where('is_fixed', false)
                ->whereIn('status', ['confirmed', 'pending'])
                ->where('payment_status', 'unpaid') // Dívida ativa (partial) não trava o caixa
                ->get()
                ->filter(function ($r) use ($agora) {
                    // Monta o Carbon do fim do jogo para comparar com a hora atual
                    $fimJogo = \Carbon\Carbon::parse($r->date->format('Y-m-d') . ' ' . $r->end_time);
                    return $agora->greaterThan($fimJogo);
                });

            if ($pendenciasCriticas->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "🚨 Bloqueio: Existem {$pendenciasCriticas->count()} jogo(s) finalizados sem definição financeira (Quitado, Falta ou Dívida). Resolva-os para fechar."
                ], 403);
            }
            // ------------------------------------------------

            $calculatedSystem = FinancialTransaction::whereDate('paid_at', $date)
                ->where('arena_id', $arenaId)
                ->sum('amount');

            $calculated = round((float)$calculatedSystem, 2);
            $actual     = round((float)$validated['actual_amount'], 2);
            $difference = round($actual - $calculated, 2);

            // 🚀 REGRA DE AUTORIZAÇÃO DO SUPERVISOR
            if ($difference != 0 && Auth::user()->role === 'colaborador') {
                if (!$request->filled('supervisor_token')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Divergência de valores detectada. Requer autorização do supervisor.'
                    ], 403);
                }
            }

            DB::transaction(function () use ($date, $arenaId, $calculated, $actual, $difference, $request) {
                Cashier::updateOrCreate(
                    ['date' => $date, 'arena_id' => $arenaId],
                    [
                        'user_id'           => Auth::id(),
                        'calculated_amount' => $calculated,
                        'actual_amount'     => $actual,
                        'difference'        => $difference,
                        'status'            => 'closed',
                        'closing_time'      => now(),
                        'notes'             => $request->input('notes') . ($request->filled('supervisor_token') ? " | [AUTORIZADO POR SUPERVISOR]" : ""),
                    ]
                );
            });

            return response()->json(['success' => true, 'message' => 'Caixa fechado com sucesso!']);
        } catch (\Exception $e) {
            \Log::error("Erro no fechamento: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 🎯 REABRIR CAIXA: Registra justificativa e invalida o fechamento anterior.
     */
    public function reopenCash(Request $request)
    {
        // 1. Validação dos campos do formulário
        $request->validate([
            'date'     => 'required|date',
            'reason'   => 'required|string|min:5',
            'arena_id' => 'required|exists:arenas,id',
        ]);

        try {
            // 🚀 2. TRAVA DE SEGURANÇA: Se for colaborador, EXIGE o supervisor_token
            if (Auth::user()->role === 'colaborador') {
                if (!$request->filled('supervisor_token')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A reabertura de caixa exige autorização de um supervisor.'
                    ], 403);
                }
            }

            \Log::info("Tentando reabrir caixa: Data {$request->date} | Arena {$request->arena_id}");

            // 3. Busca o registro
            $cashier = Cashier::whereDate('date', $request->date)
                ->where('arena_id', $request->arena_id)
                ->first();

            if (!$cashier) {
                return response()->json([
                    'success' => false,
                    'message' => "Não existe um fechamento registrado para esta arena no dia " . \Carbon\Carbon::parse($request->date)->format('d/m/Y') . "."
                ], 404);
            }

            // 4. Monta a nota de quem autorizou
            $autorizacaoTxt = $request->filled('supervisor_token')
                ? " | [AUTORIZADO POR: {$request->supervisor_token}]"
                : "";

            // 5. Atualiza para reabrir
            $cashier->update([
                'status'            => 'open',
                'reopen_reason'     => $request->reason . $autorizacaoTxt,
                'reopened_at'       => now(),
                'reopened_by'       => Auth::id(),
                'actual_amount'     => 0,
                'difference'        => 0,
                'closing_time'      => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Caixa da unidade reaberto com sucesso!'
            ]);
        } catch (\Exception $e) {
            \Log::error("Erro fatal ao reabrir caixa: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Falha interna ao processar reabertura.'
            ], 500);
        }
    }


    /**
     * Processa o Pagamento (Blindado contra duplicidade e erro de saldo)
     * Corrigido para somar o histórico real e evitar duplicidade de valores.
     */
    public function processPayment(Request $request, $reservaId)
    {
        $reserva = Reserva::with('arena')->findOrFail($reservaId);
        $dataOperacional = $request->input('payment_date') ?? now()->toDateString();
        $labelData = \Carbon\Carbon::parse($dataOperacional)->format('d/m/Y');

        // 🛡️ TRAVA DE CAIXA: Impede lançamentos se o caixa da arena já estiver fechado
        if (\App\Http\Controllers\FinanceiroController::isCashClosed($dataOperacional, $reserva->arena_id)) {
            return response()->json([
                'success' => false,
                'message' => "Ação bloqueada: O caixa do dia {$labelData} já está encerrado nesta unidade."
            ], 403);
        }

        $validated = $request->validate([
            'final_price'     => 'required|numeric|min:0',
            'amount_paid'     => 'required|numeric|min:0',
            'payment_method'  => 'required|string|max:50',
            'apply_to_series' => 'nullable|boolean',
            'payment_date'    => 'nullable|date',
        ]);

        try {
            $paymentStatus = 'pending';

            DB::transaction(function () use ($validated, $reserva, &$paymentStatus, $dataOperacional) {
                // 🔒 LOCK PARA EVITAR CONCORRÊNCIA (Race Condition)
                $reservaFresh = Reserva::where('id', $reserva->id)->lockForUpdate()->first();

                $finalPrice = round((float) $validated['final_price'], 2);
                $amountReceivedNow = round((float) $validated['amount_paid'], 2);

                // --- 🛡️ CORREÇÃO CRÍTICA DA SOMA ---
                // Recalculamos o total pago real baseado na SOMA das transações existentes no banco.
                // Isso resolve o problema de herdar valores antigos (como após estornos de manutenção).
                $jaPagoReal = (float) $reservaFresh->transactions()
                    ->whereDate('paid_at', $dataOperacional)
                    ->sum('amount');
                $newTotalPaid = round($jaPagoReal + $amountReceivedNow, 2);

                // ✅ Verificação de duplicidade: Se já está pago e tentam pagar mais, trava.
                if ($jaPagoReal >= $finalPrice && $finalPrice > 0 && $amountReceivedNow > 0) {
                    throw new \Exception('ALREADY_PAID');
                }

                // Segurança contra pagar mais que o total (tolerância de 5 centavos para arredondamentos)
                if ($newTotalPaid > ($finalPrice + 0.05)) {
                    throw new \Exception("Valor excede o total. O saldo devedor real era: R$ " . number_format($finalPrice - $jaPagoReal, 2, ',', '.'));
                }

                // Define status financeiro e visual da reserva
                if ($newTotalPaid >= $finalPrice && $finalPrice > 0) {
                    $paymentStatus = 'paid';
                    $newVisualStatus = 'completed';
                } elseif ($newTotalPaid > 0) {
                    $paymentStatus = 'partial';
                    // Se estava em manutenção, volta para confirmado ao receber pagamento parcial
                    $newVisualStatus = ($reservaFresh->status === 'maintenance') ? 'confirmed' : $reservaFresh->status;
                } else {
                    $paymentStatus = 'unpaid';
                    $newVisualStatus = $reservaFresh->status;
                }

                // Atualiza a reserva com os valores recalculados
                $reservaFresh->update([
                    'total_paid'     => $newTotalPaid,
                    'final_price'    => $finalPrice,
                    'payment_status' => $paymentStatus,
                    'status'         => $newVisualStatus,
                    'manager_id'     => Auth::id(),
                ]);

                // Atualiza série futura para mensalistas
                if (!empty($validated['apply_to_series']) && $reservaFresh->recurrent_series_id) {
                    Reserva::where('recurrent_series_id', $reservaFresh->recurrent_series_id)
                        ->where('date', '>', $reservaFresh->date)
                        ->where('payment_status', 'unpaid')
                        ->update([
                            'price' => $finalPrice,
                            'final_price' => $finalPrice
                        ]);
                }

                // --- REGISTRO DA TRANSAÇÃO FINANCEIRA ---
                if ($amountReceivedNow > 0) {
                    FinancialTransaction::create([
                        'reserva_id'     => $reservaFresh->id,
                        'arena_id'       => $reservaFresh->arena_id,
                        'user_id'        => $reservaFresh->user_id,
                        'manager_id'     => Auth::id(),
                        'amount'         => $amountReceivedNow,
                        'type'           => ($paymentStatus === 'paid') ? FinancialTransaction::TYPE_PAYMENT : 'partial_payment',
                        'payment_method' => $validated['payment_method'],
                        'description'    => "Pagamento reserva #{$reservaFresh->id} | Cliente: {$reservaFresh->client_name}",
                        'paid_at'        => $dataOperacional . ' ' . now()->format('H:i:s'),
                    ]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => "Pagamento de R$ " . number_format($validated['amount_paid'], 2, ',', '.') . " processado com sucesso!",
                'status'  => $paymentStatus
            ]);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'ALREADY_PAID') {
                return response()->json([
                    'success' => true,
                    'message' => 'Esta reserva já foi quitada anteriormente.',
                    'status' => 'paid'
                ]);
            }

            Log::error("Erro no processamento de pagamento ID #{$reservaId}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }


    /**
     * Registra Falta (No-Show) - Blindado com auditoria e preservação de histórico
     */
    public function registerNoShow(Request $request, $reservaId)
    {
        // 1. Carrega a reserva com as relações necessárias
        $reserva = \App\Models\Reserva::with(['arena', 'user'])->findOrFail($reservaId);

        // --- 🎯 AJUSTE DE DATA OPERACIONAL ---
        $dataOperacional = $request->input('payment_date') ?? now()->toDateString();
        $labelData = \Carbon\Carbon::parse($dataOperacional)->format('d/m/Y');

        // 🎯 VERIFICAÇÃO DE SEGURANÇA: Bloqueia se o caixa da data operacional estiver fechado.
        if (\App\Http\Controllers\FinanceiroController::isCashClosed($dataOperacional, $reserva->arena_id)) {
            return response()->json([
                'success' => false,
                'message' => "Ação bloqueada: O caixa de {$labelData} já está encerrado nesta unidade."
            ], 403);
        }

        try {
            \DB::beginTransaction();

            $totalOriginalPago = round((float) $reserva->total_paid, 2);
            $shouldRefund = $request->boolean('should_refund');
            $valorParaEstornar = round((float) $request->input('refund_amount', 0), 2);

            $clienteNome = $reserva->client_name ?? $reserva->user?->name ?? 'Cliente Externo';
            $horario = \Carbon\Carbon::parse($reserva->start_time)->format('H:i');
            $infoContexto = " | Jogo original: " . \Carbon\Carbon::parse($reserva->date)->format('d/m') . " às {$horario}";

            if ($totalOriginalPago > 0) {
                if ($shouldRefund && $valorParaEstornar > 0) {
                    // 💰 REGISTRA A SAÍDA (ESTORNO) NA DATA OPERACIONAL
                    \App\Models\FinancialTransaction::create([
                        'reserva_id'     => null,
                        'arena_id'       => $reserva->arena_id,
                        'user_id'        => $reserva->user_id,
                        'manager_id'     => \Auth::id(),
                        'amount'         => -$valorParaEstornar,
                        'type'           => 'refund',
                        'payment_method' => 'cash_out',
                        'description'    => "SAÍDA (Estorno No-Show) Ref. Reserva #{$reserva->id} | Cliente: {$clienteNome}" . $infoContexto,
                        'paid_at'        => $dataOperacional . ' ' . now()->format('H:i:s'),
                    ]);

                    // Anota as transações antigas para auditoria
                    \App\Models\FinancialTransaction::where('reserva_id', $reserva->id)
                        ->where('type', '!=', 'refund')
                        ->update([
                            'reserva_id'  => null,
                            'description' => \DB::raw("CONCAT(description, ' (No-Show c/ Estorno em " . date('d/m') . ")')")
                        ]);
                } else {
                    // 🔒 RETENÇÃO (Multa): O dinheiro fica no caixa, vira "Multa" e desvincula da reserva
                    \App\Models\FinancialTransaction::where('reserva_id', $reserva->id)
                        ->update([
                            'reserva_id'     => null,
                            'type'           => 'no_show_penalty',
                            'payment_method' => 'retained_funds',
                            'description'    => \DB::raw("CONCAT(description, ' [RETIDO COMO MULTA EM " . date('d/m') . " - Reserva #{$reserva->id}]')")
                        ]);

                    // Log informativo no extrato do dia
                    \App\Models\FinancialTransaction::create([
                        'reserva_id'     => null,
                        'arena_id'       => $reserva->arena_id,
                        'manager_id'     => \Auth::id(),
                        'amount'         => 0,
                        'type'           => 'no_show_penalty',
                        'payment_method' => 'retained_funds',
                        'description'    => "LOG: Falta marcada. Valor de R$ {$totalOriginalPago} (Ref #{$reserva->id}) retido como multa.",
                        'paid_at'        => $dataOperacional . ' ' . now()->format('H:i:s'),
                    ]);
                }
            }

            // Penalidade de No-Show para o usuário (Bloqueio automático após 3 faltas)
            if ($reserva->user) {
                $user = $reserva->user;
                $user->increment('no_show_count');
                if ($user->no_show_count >= 3) {
                    $user->update(['is_blocked' => true]);
                }
            }

            // 🔄 Libera o horário no mapa de reservas (Cria o slot verde/vago novamente)
            if (method_exists(app(\App\Http\Controllers\ReservaController::class), 'recreateFixedSlot')) {
                app(\App\Http\Controllers\ReservaController::class)->recreateFixedSlot($reserva);
            }

            // 🚀 A MUDANÇA CHAVE: Atualizamos o status para 'no_show' em vez de deletar
            $reserva->update([
                'status' => 'no_show',
                'managed_by' => \Auth::id(),
                'cancellation_reason' => '[Falta Registrada] ' . ($request->input('no_show_reason') ?? 'Cliente não compareceu'),
                'is_fixed' => false,
                'total_paid' => 0,
                'final_price' => 0,
            ]);

            \DB::commit();
            return response()->json([
                'success' => true,
                'message' => "Falta registrada e financeiro ajustado no caixa de {$labelData}."
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error("Erro no No-Show #{$reservaId}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🎯 LANÇAR COMO PENDÊNCIA (Pagar Depois):
     * Blindado com Lock de banco e verificação de integridade.
     */
    public function markAsPendingDebt(Request $request, $reservaId)
    {
        // LOG DE ENTRADA: Monitoramento de auditoria
        \Log::info("--- INÍCIO PROCESSO DÍVIDA ---", [
            'reserva_id' => $reservaId,
            'motivo' => $request->reason,
            'usuario' => \Auth::user()->name ?? 'Desconhecido'
        ]);

        try {
            // Buscamos a reserva com Lock para garantir que ninguém pague ela enquanto estamos pendenciando
            $reserva = \App\Models\Reserva::where('id', $reservaId)->lockForUpdate()->firstOrFail();

            // 1. Validação do Motivo (Mínimo 5 caracteres para evitar "abcde")
            $validated = $request->validate([
                'reason' => 'required|string|min:5|max:255',
            ]);

            // 🛡️ TRAVA DE CAIXA OPERACIONAL
            // Verificamos se o caixa da data do jogo já foi encerrado
            if (\App\Http\Controllers\FinanceiroController::isCashClosed($reserva->date, $reserva->arena_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ação bloqueada: O caixa do dia ' . \Carbon\Carbon::parse($reserva->date)->format('d/m/Y') . ' já está encerrado.'
                ], 403);
            }

            // 2. Verificação de Saldo (Não pendenciar o que já está pago)
            $totalDevido = round((float)($reserva->final_price ?? $reserva->price), 2);
            $totalPago = round((float)$reserva->total_paid, 2);

            if ($totalPago >= $totalDevido && $totalDevido > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta reserva já consta como totalmente paga.'
                ], 422);
            }

            // 3. Execução da Transação
            \DB::transaction(function () use ($reserva, $validated, $totalPago) {
                // Define se é uma dívida total (unpaid) ou se o cara pagou uma parte e deve o resto (partial)
                $novoStatusPagamento = ($totalPago > 0) ? 'partial' : 'unpaid';

                // Atualiza o status para 'completed' (jogo aconteceu) mas mantém o financeiro aberto
                $reserva->update([
                    'status' => 'completed',
                    'payment_status' => $novoStatusPagamento,
                    'manager_id' => \Auth::id(),
                    // Histórico interno nas notas para auditoria futura
                    'notes' => $reserva->notes . " | [DÍVIDA EM " . now()->format('d/m H:i') . "]: " . $validated['reason'] . " (Autorizado por: " . \Auth::user()->name . ")"
                ]);
            });

            \Log::info("✅ Reserva #{$reservaId} movida para dívidas.");

            return response()->json([
                'success' => true,
                'message' => 'O jogo foi finalizado e o saldo devedor foi enviado para o Gerenciador de Pendências.'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'O motivo da pendência é obrigatório (mín. 5 caracteres).'], 422);
        } catch (\Exception $e) {
            \Log::error("❌ Erro ao pendenciar reserva #{$reservaId}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 💸 MOVIMENTAÇÃO AVULSA: Sangria (Saída) ou Reforço (Entrada)
     * Refinado para garantir vínculo obrigatório com uma Arena e isolamento de caixa.
     */
    public function storeAvulsa(Request $request)
    {
        // 1. Validação: arena_id é obrigatório para saber de qual caixa sai o dinheiro
        $validated = $request->validate([
            'date'           => 'required|date',
            'type'           => 'required|in:in,out',
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'description'    => 'required|string|max:255',
            'arena_id'       => 'required|exists:arenas,id',
        ], [
            'arena_id.required' => 'Selecione a Arena para vincular esta movimentação.',
        ]);

        try {
            // 2. 🎯 TRAVA DE SEGURANÇA AJUSTADA:
            // Agora validamos se o caixa daquela arena específica está fechado.
            // Isso evita que um lançamento avulso "quebre" a auditoria de um caixa já lacrado.
            if (\App\Http\Controllers\FinanceiroController::isCashClosed($validated['date'], $validated['arena_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ação bloqueada: O caixa desta arena para o dia ' . \Carbon\Carbon::parse($validated['date'])->format('d/m/Y') . ' já está encerrado.'
                ], 403);
            }

            // 3. Lógica do Valor: Entrada (+) ou Saída (-)
            $finalAmount = $validated['type'] === 'out'
                ? -abs($validated['amount'])
                : abs($validated['amount']);

            // 4. Identificação do tipo para o banco de dados
            $transactionType = $validated['type'] === 'out' ? 'sangria' : 'reforco';
            $prefixLabel = $validated['type'] === 'out' ? '🔴 SANGRIA: ' : '🟢 REFORÇO: ';

            // 5. Criação da transação
            FinancialTransaction::create([
                'arena_id'       => $validated['arena_id'], // ✅ Vínculo obrigatório
                'manager_id'     => Auth::id(),
                'amount'         => $finalAmount,
                'type'           => $transactionType,
                'payment_method' => $validated['payment_method'],
                'description'    => $prefixLabel . $validated['description'],
                // Registra a data do caixa com o horário real da operação para timeline correta
                'paid_at'        => $validated['date'] . ' ' . now()->format('H:i:s'),
            ]);

            return response()->json([
                'success' => true,
                'message' => ($validated['type'] === 'out' ? 'Saída' : 'Entrada') . ' registrada com sucesso!'
            ]);
        } catch (\Exception $e) {
            Log::error("Erro na movimentação avulsa: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao processar movimentação: ' . $e->getMessage()
            ], 500);
        }
    }
}
