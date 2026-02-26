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

        // 1. PRIMEIRO: Pegamos as transações financeiras do dia (O dinheiro real)
        $financialTransactions = FinancialTransaction::whereDate('paid_at', $dateObject)
            ->when($selectedArenaId, fn($q) => $q->where('arena_id', $selectedArenaId))
            ->with(['reserva', 'manager', 'payer', 'arena'])
            ->orderBy('paid_at', 'desc')
            ->get();

        // 2. SEGUNDO: Pegamos os IDs de reservas que movimentaram dinheiro hoje
        $reservaIdsComMovimentacaoHoje = $financialTransactions->pluck('reserva_id')->filter()->unique();

        // 3. TERCEIRO: Consulta de Reservas (A Lente Corrigida) 🎯
        $query = Reserva::with(['user', 'arena']);

        if ($filterDebts) {
            $query->where('status', 'completed')->whereIn('payment_status', ['unpaid', 'partial']);
        } elseif ($reservaIdParam) {
            $query->where('id', $reservaIdParam);
        } else {
            // AQUI ESTÁ A MÁGICA:
            // Mostra reservas que JOGAM hoje OU que PAGARAM hoje.
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

        // 4. Saldo Líquido Real (Mantido)
        $totalRecebidoDiaLiquido = $financialTransactions->sum('amount');

        // 5. Lógica de Status do Caixa e Histórico (Mantido)
        $cashierRecord = Cashier::where('date', $selectedDateString)
            ->when($selectedArenaId, fn($q) => $q->where('arena_id', $selectedArenaId))
            ->first();
        $cashierStatus = $cashierRecord->status ?? 'open';

        $cashierHistory = Cashier::with(['user', 'arena'])
            ->when($selectedArenaId, fn($q) => $q->where('arena_id', $selectedArenaId))
            ->orderBy('date', 'desc')
            ->limit(10)
            ->get();

        // Faturamento por Arena (Otimizado para usar a coleção já carregada)
        $arenasAtivas = \App\Models\Arena::all();
        $faturamentoPorArena = $arenasAtivas->map(function ($arena) use ($financialTransactions) {
            return (object)[
                'id'    => $arena->id,
                'name'  => $arena->name,
                'total' => $financialTransactions->where('arena_id', $arena->id)->sum('amount')
            ];
        });

        return view('admin.payment.index', [
            'selectedDate'               => $selectedDateString,
            'reservas'                   => $reservas,
            'financialTransactions'      => $financialTransactions,
            'totalRecebidoDiaLiquido'    => $totalRecebidoDiaLiquido,
            'faturamentoPorArena'        => $faturamentoPorArena,
            'cashierStatus'              => $cashierStatus,
            'cashierHistory'             => $cashierHistory,
            'totalReservasDia'           => $reservas->where('date', $selectedDateString)->count(),
            'totalPending'               => $reservas->whereIn('status', ['confirmed', 'pending'])->sum(fn($r) => max(0, ($r->final_price ?? $r->price) - $r->total_paid)),
            'noShowCount'                => $reservas->where('status', 'no_show')->count(),
            'totalAuthorizedDebt'        => $reservas->where('status', 'completed')->whereIn('payment_status', ['unpaid', 'partial'])->sum(fn($r) => max(0, ($r->final_price ?? $r->price) - $r->total_paid)),
            // ... outros KPIs que você usa na view
        ]);
    }


    /**
     * 🎯 FECHAR CAIXA: Grava a auditoria no banco com cálculo automático de segurança por arena.
     */
    public function closeCash(Request $request)
    {
        // 1. Validação rigorosa: arena_id é obrigatório para fechamento individualizado
        $validated = $request->validate([
            'date'          => 'required|date',
            'actual_amount' => 'required|numeric',
            'arena_id'      => 'required|exists:arenas,id', // Garante que o fechamento pertence a uma arena válida
        ], [
            'arena_id.required' => 'É necessário selecionar uma arena para realizar o fechamento.',
        ]);

        try {
            $date = $validated['date'];
            $arenaId = $validated['arena_id'];

            // 2. CÁLCULO DE SEGURANÇA (O servidor recalcula o saldo baseado apenas na arena selecionada)
            $calculatedSystem = FinancialTransaction::whereDate('paid_at', $date)
                ->where('arena_id', $arenaId)
                ->sum('amount');

            // 3. Precisão decimal para evitar erros de ponto flutuante
            $calculated = round((float)$calculatedSystem, 2);
            $actual     = round((float)$validated['actual_amount'], 2);
            $difference = round($actual - $calculated, 2);

            // 4. Persistência (Usa transação para garantir que o status e o registro sejam salvos juntos)
            DB::transaction(function () use ($date, $arenaId, $calculated, $actual, $difference, $request) {
                Cashier::updateOrCreate(
                    [
                        'date'     => $date,
                        'arena_id' => $arenaId // Chave composta: data + arena
                    ],
                    [
                        'user_id'           => Auth::id(),
                        'calculated_amount' => $calculated,
                        'actual_amount'     => $actual,
                        'difference'        => $difference,
                        'status'            => 'closed',
                        'closing_time'      => now(),
                        'notes'             => $request->input('notes'),
                    ]
                );
            });

            return response()->json([
                'success'    => true,
                'message'    => 'Caixa da unidade fechado com sucesso!',
                'difference' => $difference,
                'system_sum' => $calculated
            ]);
        } catch (\Exception $e) {
            \Log::error("Erro crítico ao fechar caixa da arena {$request->input('arena_id')}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar o fechamento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🎯 REABRIR CAIXA: Registra justificativa e invalida o fechamento anterior.
     */
    public function reopenCash(Request $request)
    {
        $request->validate([
            'date'     => 'required|date',
            'reason'   => 'required|string|min:5',
            'arena_id' => 'required|exists:arenas,id',
        ]);

        try {
            // 1. Log de Auditoria Técnica (Aparece no storage/logs/laravel.log)
            \Log::info("Tentando reabrir caixa: Data {$request->date} | Arena {$request->arena_id}");

            // 2. Busca o registro. Removi o firstOrFail para tratar o erro amigavelmente
            $cashier = Cashier::whereDate('date', $request->date) // Usando whereDate para garantir compatibilidade
                ->where('arena_id', $request->arena_id)
                ->first();

            if (!$cashier) {
                return response()->json([
                    'success' => false,
                    'message' => "Não existe um fechamento registrado para esta arena no dia " . \Carbon\Carbon::parse($request->date)->format('d/m/Y') . ". Verifique se você selecionou a unidade correta no filtro."
                ], 404);
            }

            // 3. Atualiza para reabrir
            $cashier->update([
                'status'            => 'open',
                'reopen_reason'     => $request->reason,
                'reopened_at'       => now(),
                'reopened_by'       => Auth::id(),
                'actual_amount'     => 0,
                'difference'        => 0,
                'closing_time'      => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Caixa da unidade reaberto. Alterações financeiras liberadas.'
            ]);
        } catch (\Exception $e) {
            \Log::error("Erro fatal ao reabrir caixa da arena {$request->arena_id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Falha interna ao processar reabertura. Detalhes: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Processa o Pagamento (Integrado com trava de segurança e suporte a virada de meia-noite)
     */
    public function processPayment(Request $request, $reservaId)
    {
        $reserva = Reserva::with('arena')->findOrFail($reservaId);

        // --- 1. LÓGICA DE DATA OPERACIONAL CORRIGIDA ---
        // Priorizamos a data que o usuário está operando no sistema (payment_date).
        // Se não vier (caso de agendamento rápido), usamos a data atual (now).
        $dataOperacional = $request->input('payment_date') ?? now()->toDateString();

        // --- 2. TRAVA DE CAIXA (SEGURANÇA) ---
        // Agora verificamos se o caixa da data ONDE O DINHEIRO ESTÁ ENTRANDO está fechado.
        if (\App\Http\Controllers\FinanceiroController::isCashClosed($dataOperacional, $reserva->arena_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Ação bloqueada: O caixa do dia ' . \Carbon\Carbon::parse($dataOperacional)->format('d/m/Y') . ' já está encerrado nesta unidade.'
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
                $finalPrice = round((float) $validated['final_price'], 2);
                $amountReceivedNow = round((float) $validated['amount_paid'], 2);
                $newTotalPaid = round((float) $reserva->total_paid + $amountReceivedNow, 2);

                if ($newTotalPaid > $finalPrice) {
                    throw new \Exception("O valor total pago não pode ser maior que o preço final.");
                }

                $newVisualStatus = $reserva->status;
                if ($newTotalPaid >= $finalPrice && $finalPrice > 0) {
                    $paymentStatus = 'paid';
                    $newVisualStatus = 'completed';
                } elseif ($newTotalPaid > 0) {
                    $paymentStatus = 'partial';
                }

                $reserva->update([
                    'total_paid'     => $newTotalPaid,
                    'final_price'    => $finalPrice,
                    'payment_status' => $paymentStatus,
                    'status'         => $newVisualStatus,
                    'manager_id'     => Auth::id(),
                ]);

                if (!empty($validated['apply_to_series']) && $reserva->recurrent_series_id) {
                    Reserva::where('recurrent_series_id', $reserva->recurrent_series_id)
                        ->where('date', '>', $reserva->date)
                        ->where('payment_status', 'unpaid')
                        ->update([
                            'price' => $finalPrice,
                            'final_price' => $finalPrice
                        ]);
                }

                // --- 3. AUDITORIA FINANCEIRA (O CORAÇÃO DO CAIXA) ---
                if ($amountReceivedNow > 0) {
                    FinancialTransaction::create([
                        'reserva_id'     => $reserva->id,
                        'arena_id'       => $reserva->arena_id,
                        'user_id'        => $reserva->user_id,
                        'manager_id'     => Auth::id(),
                        'amount'         => $amountReceivedNow,
                        'type'           => ($paymentStatus === 'paid') ? FinancialTransaction::TYPE_PAYMENT : 'partial_payment',
                        'payment_method' => $validated['payment_method'],
                        'description'    => "Pagamento reserva #{$reserva->id} | {$reserva->arena->name}",
                        // AGORA: Gravamos com a data que o dinheiro entrou no caixa (Hoje), 
                        // mesmo que o jogo seja amanhã.
                        'paid_at'        => $dataOperacional . ' ' . now()->format('H:i:s'),
                    ]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Pagamento processado e saldo atualizado no caixa de hoje!',
                'status'  => $paymentStatus
            ]);
        } catch (\Exception $e) {
            Log::error("Erro pagamento #{$reservaId}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }


    /**
     * Registra Falta (No-Show) - Com lógica de proteção de histórico financeiro
     */
    public function registerNoShow(Request $request, $reservaId)
    {
        $reserva = Reserva::with(['arena', 'user'])->findOrFail($reservaId);

        // --- 🎯 AJUSTE DE DATA OPERACIONAL CORRIGIDA ---
        // Priorizamos a data do caixa que está aberto na tela (payment_date).
        // Se não houver, usamos a data atual (now), garantindo que o estorno caia no caixa de hoje.
        $dataOperacional = $request->input('payment_date') ?? now()->toDateString();

        // 🎯 VERIFICAÇÃO DE SEGURANÇA: Bloqueia se o caixa da data de execução estiver fechado.
        if (\App\Http\Controllers\FinanceiroController::isCashClosed($dataOperacional, $reserva->arena_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Ação bloqueada: O caixa da unidade ' . ($reserva->arena->name ?? '') . ' para o dia ' . \Carbon\Carbon::parse($dataOperacional)->format('d/m/Y') . ' já está encerrado.'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $totalOriginalPago = round((float) $reserva->total_paid, 2);
            $shouldRefund = $request->boolean('should_refund');
            $valorParaEstornar = round((float) $request->input('refund_amount', 0), 2);

            $clienteNome = $reserva->client_name ?? $reserva->user?->name ?? 'Cliente Externo';
            $horario = \Carbon\Carbon::parse($reserva->start_time)->format('H:i');
            $infoContexto = " | Jogo original: " . \Carbon\Carbon::parse($reserva->date)->format('d/m') . " às {$horario}";

            if ($totalOriginalPago > 0) {
                if ($shouldRefund && $valorParaEstornar > 0) {
                    // 💰 REGISTRA A SAÍDA (ESTORNO) NO CAIXA ATUAL
                    FinancialTransaction::create([
                        'reserva_id'     => null,
                        'arena_id'       => $reserva->arena_id,
                        'user_id'        => $reserva->user_id,
                        'manager_id'     => Auth::id(),
                        'amount'         => -$valorParaEstornar,
                        'type'           => 'refund',
                        'payment_method' => 'cash_out',
                        'description'    => "SAÍDA (Estorno No-Show) Ref. Reserva #{$reserva->id} | Cliente: {$clienteNome}" . $infoContexto,
                        // Gravamos com a data operacional + hora atual para timeline
                        'paid_at'        => $dataOperacional . ' ' . now()->format('H:i:s'),
                    ]);

                    // Atualiza transações antigas para histórico
                    FinancialTransaction::where('reserva_id', $reserva->id)
                        ->where('type', '!=', 'refund')
                        ->update([
                            'reserva_id'  => null,
                            'description' => DB::raw("CONCAT(description, ' (No-Show c/ Estorno em " . date('d/m') . ")')")
                        ]);
                } else {
                    // 🔒 RETENÇÃO (Multa): Mantém o dinheiro no sistema, mas muda o tipo da transação
                    FinancialTransaction::where('reserva_id', $reserva->id)
                        ->update([
                            'reserva_id'     => null,
                            'type'           => 'no_show_penalty',
                            'payment_method' => 'retained_funds',
                            'description'    => DB::raw("CONCAT(description, ' [RETIDO COMO MULTA EM " . date('d/m') . " - Reserva #{$reserva->id}]')")
                        ]);

                    // Log visual no extrato do dia (Zero value para não duplicar saldo, apenas informar)
                    FinancialTransaction::create([
                        'reserva_id'     => null,
                        'arena_id'       => $reserva->arena_id,
                        'manager_id'     => Auth::id(),
                        'amount'         => 0,
                        'type'           => 'no_show_penalty',
                        'payment_method' => 'retained_funds',
                        'description'    => "LOG: Falta marcada. Valor de R$ {$totalOriginalPago} (Ref #{$reserva->id}) retido como multa.",
                        'paid_at'        => $dataOperacional . ' ' . now()->format('H:i:s'),
                    ]);
                }
            }

            if ($reserva->user) {
                $user = $reserva->user;
                $user->increment('no_show_count');
                if ($user->no_show_count >= 3) {
                    $user->update(['is_blocked' => true]);
                }
            }

            app(\App\Http\Controllers\ReservaController::class)->recreateFixedSlot($reserva);
            $reserva->delete();

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Falta registrada e financeiro ajustado para o caixa de hoje.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro no No-Show #{$reservaId}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao processar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 🎯 LANÇAR COMO PENDÊNCIA (Pagar Depois):
     * Finaliza a reserva operacionalmente mas exige um motivo para a dívida.
     */
    public function markAsPendingDebt(Request $request, $reservaId)
    {
        // LOG DE ENTRADA: Saber se o JS chegou aqui e o que enviou
        \Log::info("--- INÍCIO PROCESSO DÍVIDA ---", [
            'reserva_id_url' => $reservaId,
            'reserva_id_body' => $request->reserva_id,
            'motivo' => $request->reason,
            'usuario' => \Auth::user()->name ?? 'Não autenticado'
        ]);

        try {
            $reserva = \App\Models\Reserva::findOrFail($reservaId);

            // 1. Validação do Motivo
            $validated = $request->validate([
                'reason' => 'required|string|min:5|max:255',
            ]);

            // 🛡️ TRAVA DE CAIXA
            if (\App\Http\Controllers\FinanceiroController::isCashClosed($reserva->date, $reserva->arena_id)) {
                \Log::warning("⚠️ Tentativa de pendenciar em caixa fechado. Reserva #{$reservaId}");
                return response()->json([
                    'success' => false,
                    'message' => 'Ação bloqueada: O caixa desta unidade para o dia ' . \Carbon\Carbon::parse($reserva->date)->format('d/m/Y') . ' já está encerrado.'
                ], 403);
            }

            // 2. Verificação de Saldo
            $totalDevido = ($reserva->final_price ?? $reserva->price);
            if ($reserva->total_paid >= $totalDevido && $totalDevido > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta reserva já consta como paga no sistema.'
                ], 422);
            }

            // 3. Execução da Transação
            \DB::transaction(function () use ($reserva, $validated) {
                $novoStatusPagamento = ($reserva->total_paid > 0) ? 'partial' : 'unpaid';

                $reserva->update([
                    'status' => 'completed',
                    'payment_status' => $novoStatusPagamento,
                    'manager_id' => \Auth::id(),
                    'notes' => $reserva->notes . " | [DÍVIDA AUTORIZADA]: " . $validated['reason'] . " (por " . \Auth::user()->name . " em " . now()->format('d/m H:i') . ")"
                ]);
            });

            \Log::info("✅ Reserva #{$reservaId} marcada como dívida com sucesso.");

            return response()->json([
                'success' => true,
                'message' => 'Reserva marcada como pendência de pagamento com sucesso.'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error("❌ Erro de Validação: ", $e->errors());
            return response()->json(['success' => false, 'message' => 'O motivo deve ter pelo menos 5 caracteres.'], 422);
        } catch (\Exception $e) {
            \Log::error("❌ Erro ao pendenciar reserva #{$reservaId}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro interno ao processar: ' . $e->getMessage()], 500);
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
