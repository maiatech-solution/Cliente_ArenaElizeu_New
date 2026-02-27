<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use App\Models\FinancialTransaction;
use App\Http\Controllers\FinanceiroController;
use Illuminate\Database\Eloquent\Builder;

class ReservaController extends Controller
{
    /**
     * Exibe a página pública de agendamento (que carrega os slots via API).
     */
    public function index()
    {
        return view('reserva.index');
    }

    /**
     * Exibe o Dashboard administrativo (incluindo o alerta de renovação).
     */
    public function dashboard()
    {
        $pendingReservationsCount = Reserva::where('status', Reserva::STATUS_PENDENTE)
            ->whereDate('date', '>=', Carbon::today()->startOfDay())
            ->count();

        $expiringSeries = $this->getEndingRecurrentSeries();

        return view('dashboard', [
            'pendingReservationsCount' => $pendingReservationsCount,
            'expiringSeriesCount' => count($expiringSeries),
            'expiringSeries' => $expiringSeries,
        ]);
    }


    // -------------------------------------------------------------------------
    // MÉTODOS AUXILIARES (CheckOverlap, Manipulação de Slots Fixos, Cliente)
    // -------------------------------------------------------------------------

    /**
     * Helper CRÍTICO: Checa se há sobreposição no calendário (apenas reservas de cliente).
     */
    /**
     * Helper CRÍTICO: Checa se há sobreposição no calendário filtrando por ARENA.
     */
    public function checkOverlap($date, $startTime, $endTime, $arenaId, $checkActiveOnly = true, $excludeReservaId = null)
    {
        // 1. Normalização dos horários (Garante o formato H:i:s para comparação no banco)
        try {
            $startTimeNormalized = Carbon::createFromFormat('G:i', $startTime)->format('H:i:s');
            $endTimeNormalized = Carbon::parse($endTime)->format('H:i:s');
        } catch (\Exception $e) {
            $startTimeNormalized = Carbon::parse($startTime)->format('H:i:s');
            $endTimeNormalized = Carbon::parse($endTime)->format('H:i:s');
        }

        // 2. Construção da Query de Conflito
        $query = Reserva::where('date', $date)
            ->where('arena_id', $arenaId) // 🎯 CORREÇÃO: Filtra apenas na quadra específica
            ->where('is_fixed', false)     // Apenas reservas reais de clientes
            ->where(function ($q) use ($startTimeNormalized, $endTimeNormalized) {
                // Lógica de interseção de horários: (Inicio < FimExistente) E (Fim > InicioExistente)
                $q->where('start_time', '<', $endTimeNormalized)
                    ->where('end_time', '>', $startTimeNormalized);
            });

        // 3. Regra de Negócio: O que conta como "Bloqueado"?
        if ($checkActiveOnly) {
            // Para permitir que vários clientes fiquem "Pendentes" no mesmo horário,
            // o conflito só existe se já houver alguém Confirmado ou Pago.
            $query->whereIn('status', [
                Reserva::STATUS_CONFIRMADA,
                Reserva::STATUS_CONCLUIDA,
                Reserva::STATUS_LANCADA_CAIXA
            ]);
        }

        // 4. Ignora a própria reserva em caso de edição
        if ($excludeReservaId) {
            $query->where('id', '!=', $excludeReservaId);
        }

        return $query->exists();
    }


    /**
     * Busca IDs de reservas confirmadas que ocupam o mesmo espaço e tempo.
     * 🎯 AJUSTADO: Agora filtra por ARENA para evitar alertas falsos entre quadras.
     */
    protected function getConflictingReservaIds(string $date, string $startTime, string $endTime, int $arenaId, ?int $ignoreReservaId = null)
    {
        // Status que realmente bloqueiam o horário
        $activeStatuses = [
            Reserva::STATUS_CONFIRMADA,
            Reserva::STATUS_CONCLUIDA,
            Reserva::STATUS_LANCADA_CAIXA
        ];

        try {
            $startTimeNormalized = Carbon::createFromFormat('G:i', $startTime)->format('H:i:s');
            $endTimeNormalized = Carbon::parse($endTime)->format('H:i:s');
        } catch (\Exception $e) {
            $startTimeNormalized = Carbon::parse($startTime)->format('H:i:s');
            $endTimeNormalized = Carbon::parse($endTime)->format('H:i:s');
        }

        $conflictingReservas = Reserva::whereIn('status', $activeStatuses)
            ->whereDate('date', $date)
            ->where('arena_id', $arenaId) // 🏟️ FILTRO ESSENCIAL: O conflito só existe na mesma quadra
            ->where('is_fixed', false)
            ->when($ignoreReservaId, function ($query) use ($ignoreReservaId) {
                return $query->where('id', '!=', $ignoreReservaId);
            })
            ->where(function ($query) use ($startTimeNormalized, $endTimeNormalized) {
                // Lógica de sobreposição: Início < Fim e Fim > Início
                $query->where('start_time', '<', $endTimeNormalized)
                    ->where('end_time', '>', $startTimeNormalized);
            })
            ->pluck('id');

        return $conflictingReservas->isEmpty() ? null : $conflictingReservas->implode(', ');
    }

    public function cancelarPontual(Request $request, $id)
    {
        $reserva = Reserva::findOrFail($id);

        // 1. Validação de segurança (Aceita confirmada ou paga)
        $statusAceitaveis = [
            Reserva::STATUS_CONFIRMADA,
            Reserva::STATUS_CONCLUIDA,
            'completed',
            'concluida'
        ];

        if (!in_array($reserva->status, $statusAceitaveis)) {
            return response()->json([
                'success' => false,
                'message' => 'Erro: Status atual (' . $reserva->status . ') não permite cancelamento.'
            ], 400);
        }

        $shouldRefund = $request->input('should_refund', false);
        $amountToRefund = (float) $request->input('paid_amount_ref', 0);
        $reason = $request->input('cancellation_reason', 'Cancelamento de reserva pontual');

        DB::beginTransaction();
        try {
            // 🛑 Processa o financeiro e recria o slot verde via finalizeStatus
            $result = $this->finalizeStatus(
                $reserva,
                Reserva::STATUS_CANCELADA,
                '[Gestor] ' . $reason,
                (bool)$shouldRefund,
                $amountToRefund
            );

            // 🗑️ LIMPEZA VISUAL: Deleta a reserva do cliente para liberar o slot no Dashboard
            // Como o finalizeStatus já recriou o slot FREE, o Dashboard mostrará o slot verde agora.
            $reserva->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reserva cancelada com sucesso!' . ($result['message_finance'] ?? '')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro no cancelamento da reserva #{$id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function recreateFixedSlot(Reserva $reserva)
    {
        // ALTERADO: Só aborta se for fixo E status 'free'.
        // Se for 'maintenance', ele PRECISA continuar para virar 'free'.
        if ($reserva->is_fixed && $reserva->status === Reserva::STATUS_FREE) {
            return;
        }

        // 2. Verifica se já existe um slot fixo no mesmo horário E NA MESMA QUADRA
        $existingFixedSlot = Reserva::where('is_fixed', true)
            ->where('arena_id', $reserva->arena_id)
            ->where('date', $reserva->date)
            ->where('start_time', $reserva->start_time)
            ->where('end_time', $reserva->end_time)
            ->first();

        // 3. Se não houver, recria o slot como LIVRE ('free')
        if (!$existingFixedSlot) {
            Reserva::create([
                'arena_id'       => $reserva->arena_id,
                'date'           => $reserva->date,
                'day_of_week'    => $reserva->day_of_week ?? \Carbon\Carbon::parse($reserva->date)->dayOfWeek,
                'start_time'     => $reserva->start_time,
                'end_time'       => $reserva->end_time,
                'price'          => $reserva->price,
                'status'         => 'free', // Forçamos o status livre
                'is_fixed'       => true,
                'is_recurrent'   => false,
                'client_name'    => 'Slot Livre',
                'client_contact' => 'N/A',
                'user_id'        => null,
            ]);
            Log::info("Slot fixo recriado com sucesso.");
        } else {
            // 4. Se ele já existe (o que não deveria ocorrer pois deletamos antes), apenas garante que está FREE
            $existingFixedSlot->update(['status' => 'free', 'client_name' => 'Slot Livre']);
        }
    }

    /**
     * Helper CRÍTICO: Consome o slot fixo de disponibilidade (remove)
     * garantindo que a remoção ocorra apenas na ARENA correta.
     */
    public function consumeFixedSlot(Reserva $reserva)
    {
        // 1. Evita processar se for um slot fixo (não faz sentido consumir a si mesmo)
        if ($reserva->is_fixed) {
            return;
        }

        // 2. Encontra o slot fixo correspondente e o remove
        // 🎯 INTEGRIDADE: Adicionado filtro por arena_id para isolar as quadras.
        $fixedSlot = Reserva::where('is_fixed', true)
            ->where('arena_id', $reserva->arena_id) // Filtra especificamente na quadra da reserva
            ->where('date', $reserva->date)
            ->where('start_time', $reserva->start_time)
            ->where('end_time', $reserva->end_time)
            ->whereIn('status', [Reserva::STATUS_FREE, Reserva::STATUS_MAINTENANCE])
            ->first();

        if ($fixedSlot) {
            // Remove o slot de disponibilidade (evento verde) para dar lugar à reserva real
            $fixedSlot->delete();
            Log::info("Slot fixo ID {$fixedSlot->id} consumido para a reserva ID {$reserva->id} na Arena #{$reserva->arena_id}.");
        } else {
            // Log de aviso caso o sistema tente consumir algo que já foi removido ou não existe
            Log::warning("Aviso: Tentativa de consumir slot fixo para reserva ID {$reserva->id}, mas nenhum slot livre foi encontrado na Arena #{$reserva->arena_id} para este horário.");
        }
    }

    /**
     * Encontra ou cria um usuário cliente (baseado no whatsapp_contact).
     */
    public function findOrCreateClient(array $data): User
    {
        $contact = $data['whatsapp_contact'];
        $name = $data['name'];
        $inputEmail = $data['email'] ?? null;

        $emailToUse = $inputEmail;

        // LÓGICA: Se o email do input estiver vazio, gera um provisório.
        if (empty($inputEmail)) {
            $uniquePart = Str::random(5);
            $emailToUse = "temp_" . time() . "{$uniquePart}" . "@arena.local";
        }

        // 1. Tenta encontrar o usuário pelo WhatsApp
        $user = User::where('whatsapp_contact', $contact)->first();

        if ($user) {
            // 2. Cliente encontrado: Atualiza o nome e e-mail (se for temp ou se for fornecido)
            $updateData = ['name' => $name];

            // Atualiza o e-mail APENAS SE: (a) for um e-mail temporário OU (b) o cliente forneceu um e-mail real.
            if (Str::contains($user->email, '@arena.local') || !empty($inputEmail)) {
                $updateData['email'] = $emailToUse;
            }

            $updateData['name'] = $name;

            if ($user->role === 'client') {
                $updateData['role'] = 'cliente';
            }

            $user->update($updateData);
            Log::info("Cliente existente encontrado e atualizado (ID: {$user->id}).");
            return $user;
        } else {
            // 3. Novo Cliente: Cria um novo usuário
            $randomPassword = Str::random(12);
            $newUser = User::create([
                'name' => $name,
                'email' => $emailToUse,
                'whatsapp_contact' => $contact,
                'password' => Hash::make($randomPassword),
                'role' => 'cliente',
                'is_admin' => false,
                'data_nascimento' => $data['data_nascimento'] ?? null,
            ]);
            Log::info("Novo cliente criado (ID: {$newUser->id}). E-mail usado: {$emailToUse}");
            return $newUser;
        }
    }


    // -------------------------------------------------------------------------
    // 🎯 MÉTODOS DE SERVIÇO CENTRALIZADO (LÓGICA DE NEGÓCIOS)
    // -------------------------------------------------------------------------

    /**
     * Cria/Atualiza uma Reserva de Cliente confirmada, consumindo o slot fixo.
     * Método centralizado usado por AdminController::storeReserva e ::storeQuickReservaApi.
     *
     * @param array $validatedData Dados validados da requisição.
     * @param User $clientUser O objeto User do cliente.
     * @param int|null $fixedSlotId ID do slot fixo a ser consumido (se houver).
     * @return Reserva
     * @throws \Exception
     */

    /**
     * Cria uma Reserva de Cliente confirmada, consumindo o slot fixo.
     * Método centralizado e robusto para evitar erros de SQL e validação.
     */
    public function createConfirmedReserva(array $validatedData, User $clientUser, ?int $fixedSlotId = null): Reserva
    {
        // 1. Identificação da Arena (Prioriza o dado validado, depois o request)
        $arenaId = $validatedData['arena_id'] ?? request('arena_id');

        if (!$arenaId) {
            throw new \Exception('A identificação da quadra (arena_id) é obrigatória para criar uma reserva.');
        }

        // 🛡️ ADICIONADO: Validação Crítica de Caixa por Arena
        // Isso impede que o bloqueio de uma arena (Futebol) afete outra (Vôlei)
        $financeiroController = app(\App\Http\Controllers\FinanceiroController::class);
        if ($financeiroController->isCashClosed($validatedData['date'], $arenaId)) {
            throw new \Exception("Bloqueio de Segurança: O caixa desta arena para o dia " . date('d/m/Y', strtotime($validatedData['date'])) . " já está encerrado. Reabra-o para agendar.");
        }

        // 2. Checagem de Conflito (Ajustado para o novo formato multiquadras)
        if ($this->checkOverlap(
            $validatedData['date'],
            $validatedData['start_time'],
            $validatedData['end_time'],
            $arenaId,
            true,
            null
        )) {
            throw new \Exception('O horário selecionado já está ocupado por outra reserva confirmada nesta quadra.');
        }

        // 3. Normalização dos horários para o formato H:i:s
        try {
            $startTimeNormalized = \Carbon\Carbon::parse($validatedData['start_time'])->format('H:i:s');
            $endTimeNormalized = \Carbon\Carbon::parse($validatedData['end_time'])->format('H:i:s');
        } catch (\Exception $e) {
            $startTimeNormalized = \Carbon\Carbon::createFromFormat('H:i', $validatedData['start_time'])->format('H:i:s');
            $endTimeNormalized = \Carbon\Carbon::createFromFormat('H:i', $validatedData['end_time'])->format('H:i:s');
        }

        // 4. Tratamento de Valores Financeiros
        $price = (float) ($validatedData['price'] ?? ($validatedData['fixed_price'] ?? 0));
        $signalValueRaw = $validatedData['signal_value'] ?? 0;

        if (is_string($signalValueRaw)) {
            $signalValue = (float) str_replace(',', '.', str_replace('.', '', $signalValueRaw));
        } else {
            $signalValue = (float) $signalValueRaw;
        }

        $totalPaid = $signalValue;

        $paymentStatus = 'pending';
        $newReservaStatus = Reserva::STATUS_CONFIRMADA;

        if ($signalValue > 0) {
            $isTotalPaid = (abs($signalValue - $price) < 0.01 || $signalValue > $price);
            $paymentStatus = $isTotalPaid ? 'paid' : 'partial';

            if ($isTotalPaid) {
                $newReservaStatus = Reserva::STATUS_CONCLUIDA;
            }
        }

        // 5. Consome o slot fixo antes de criar a reserva real
        if ($fixedSlotId) {
            Reserva::where('id', $fixedSlotId)
                ->where('arena_id', $arenaId)
                ->where('is_fixed', true)
                ->delete();

            Log::info("Slot fixo ID {$fixedSlotId} removido para conversão na Arena {$arenaId}.");
        }

        // 6. Criação da Reserva
        $newReserva = Reserva::create([
            'user_id'          => $clientUser->id,
            'arena_id'         => $arenaId,
            'date'             => $validatedData['date'],
            'day_of_week'      => \Carbon\Carbon::parse($validatedData['date'])->dayOfWeek,
            'start_time'       => $startTimeNormalized,
            'end_time'         => $endTimeNormalized,
            'price'            => $price,
            'final_price'      => $price,
            'signal_value'     => $signalValue,
            'total_paid'       => $totalPaid,
            'payment_status'   => $paymentStatus,
            'client_name'      => $clientUser->name,
            'client_contact'   => $clientUser->whatsapp_contact ?? ($clientUser->email ?? 'N/A'),
            'notes'            => $validatedData['notes'] ?? null,
            'status'           => $newReservaStatus,
            'is_fixed'         => false,
            'is_recurrent'     => (bool) ($validatedData['is_recurrent'] ?? false),
            'manager_id'       => \Illuminate\Support\Facades\Auth::id(),
        ]);

        // 7. Registro da Transação Financeira do Sinal
        if ($signalValue > 0) {
            // Criamos a transação passando explicitamente o arena_id
            \App\Models\FinancialTransaction::create([
                'reserva_id'     => $newReserva->id,
                'arena_id'       => $arenaId,
                'user_id'        => $newReserva->user_id,
                'manager_id'     => \Illuminate\Support\Facades\Auth::id(),
                'amount'         => $signalValue,
                'type'           => \App\Models\FinancialTransaction::TYPE_SIGNAL,
                'payment_method' => $validatedData['payment_method'] ?? 'manual',
                'description'    => 'Sinal/Entrada via Agendamento Rápido Dashboard.',
                'paid_at'        => \Carbon\Carbon::now(),
            ]);
        }

        return $newReserva;
    }

    /**
     * Lógica centralizada para Cancelamento ou No-Show (Finalização de Status).
     * Corrigido para processar estornos reais que impactam o saldo do caixa atual.
     */
    public function finalizeStatus(Reserva $reserva, string $newStatus, string $reason, bool $shouldRefund, float $amountPaidRef)
    {
        // 1. Validação de Estado
        $statusAceitaveis = [
            Reserva::STATUS_CONFIRMADA,
            Reserva::STATUS_CONCLUIDA,
            'completed',
            'concluida'
        ];

        if (!in_array($reserva->status, $statusAceitaveis)) {
            throw new \Exception('A reserva não está em um status que permite alteração (Status atual: ' . $reserva->status . ').');
        }

        $amountPaid = (float) $amountPaidRef;
        $messageFinance = "";
        $arenaId = $reserva->arena_id;

        // --- 💰 PASSO 1: GERENCIAMENTO FINANCEIRO (ANTECIPADO POR SEGURANÇA) ---
        if ($amountPaid > 0) {
            if ($shouldRefund) {
                // LÓGICA DE ESTORNO
                FinancialTransaction::create([
                    'reserva_id'     => $reserva->id,
                    'arena_id'       => $arenaId,
                    'user_id'        => $reserva->user_id,
                    'manager_id'     => Auth::id(),
                    'amount'         => -$amountPaid,
                    'type'           => FinancialTransaction::TYPE_REFUND,
                    'payment_method' => 'outro',
                    'description'    => "ESTORNO/DEVOLUÇÃO: " . $reason . " (Reserva #{$reserva->id})",
                    'paid_at'        => now(),
                ]);

                $messageFinance = " O valor de R$ " . number_format($amountPaid, 2, ',', '.') . " foi registrado como SAÍDA (Estorno) no caixa.";
            } else {
                // LÓGICA DE RETENÇÃO: Limpa transações anteriores e cria a compensação
                FinancialTransaction::where('reserva_id', $reserva->id)
                    ->where('arena_id', $arenaId)
                    ->whereIn('type', [
                        FinancialTransaction::TYPE_SIGNAL,
                        FinancialTransaction::TYPE_PAYMENT
                    ])
                    ->delete();

                $type = ($newStatus === Reserva::STATUS_CANCELADA)
                    ? ($reserva->is_recurrent ? FinancialTransaction::TYPE_RETEN_CANC_P_COMP : FinancialTransaction::TYPE_RETEN_CANC_COMP)
                    : FinancialTransaction::TYPE_RETEN_NOSHOW_COMP;

                FinancialTransaction::create([
                    'reserva_id'     => $reserva->id,
                    'arena_id'       => $arenaId,
                    'user_id'        => $reserva->user_id,
                    'manager_id'     => Auth::id(),
                    'amount'         => $amountPaid,
                    'type'           => $type,
                    'payment_method' => 'retained_funds',
                    'description'    => "Compensação: Valor retido por " . ($newStatus === Reserva::STATUS_CANCELADA ? 'Cancelamento' : 'Falta') . " na " . ($reserva->arena->name ?? 'Quadra') . ".",
                    'paid_at'        => now(),
                ]);

                $messageFinance = " O valor de R$ " . number_format($amountPaid, 2, ',', '.') . " foi mantido como crédito de retenção no caixa.";
            }
        }

        // --- 📝 PASSO 2: ATUALIZAÇÃO DOS DADOS DA RESERVA ---
        $updateData = [
            'status' => $newStatus,
            'manager_id' => Auth::id(),
        ];

        if ($newStatus === Reserva::STATUS_CANCELADA) {
            $updateData['cancellation_reason'] = $reason;
            $updateData['no_show_reason'] = null;
        } elseif ($newStatus === Reserva::STATUS_NO_SHOW) {
            $updateData['no_show_reason'] = $reason;
            $updateData['cancellation_reason'] = null;
        }

        $reserva->update($updateData);

        // 🚀 REPUTAÇÃO AUTOMÁTICA
        if ($newStatus === Reserva::STATUS_NO_SHOW) {
            $user = $reserva->user;
            if ($user) {
                $user->no_show_count += 1;
                $user->save();
                Log::info("Reputação atualizada para Cliente ID: {$user->id} devido a No-Show na Reserva #{$reserva->id}");
            }
        }

        // 🏟️ LIBERAÇÃO DO INVENTÁRIO (Slot Verde)
        $this->recreateFixedSlot($reserva);

        return ['message_finance' => $messageFinance];
    }

    /**
     * Cancela todas as reservas futuras de uma série.
     * * @param int $masterId O ID da reserva mestra.
     * @param string $reason Motivo do cancelamento.
     * @param bool $shouldRefund Se deve estornar o sinal da série.
     * @param float $amountPaidRef Sinal pago na mestra.
     * @return array
     * @throws \Exception
     */

    /**
     * Cancela todas as reservas futuras de uma série.
     * Ajustado para evitar duplicidade de estorno e erro de saldo negativo.
     */
    public function cancelSeries(int $masterId, string $reason, bool $shouldRefund, float $amountPaidRef)
    {
        $today = \Carbon\Carbon::today()->toDateString();
        $managerId = \Illuminate\Support\Facades\Auth::id();
        $cancelledCount = 0;
        $messageFinance = "";

        // 1. Busca todas as reservas da série futuras ou de hoje
        $seriesReservas = \App\Models\Reserva::where(function ($query) use ($masterId) {
            $query->where('recurrent_series_id', $masterId)
                ->orWhere('id', $masterId);
        })
            ->where('is_fixed', false)
            ->whereDate('date', '>=', $today)
            ->get();

        // 2. Localiza a reserva mestre para referência de Arena/Usuário
        $anchorReserva = $seriesReservas->where('id', $masterId)->first() ?? $seriesReservas->first();

        if (!$anchorReserva) {
            throw new \Exception("Nenhuma reserva encontrada para a série.");
        }

        // --- 💰 PASSO FINANCEIRO ANTECIPADO (BLINDAGEM TOTAL) ---
        if ($amountPaidRef > 0) {
            // Verificamos se JÁ EXISTE um estorno para este MasterId no dia de hoje
            // Isso impede o estorno duplicado caso o cancelamento individual tenha ocorrido segundos antes
            $jaEstornado = \App\Models\FinancialTransaction::where('reserva_id', $masterId)
                ->where('type', \App\Models\FinancialTransaction::TYPE_REFUND)
                ->whereDate('paid_at', \Carbon\Carbon::today())
                ->exists();

            if ($shouldRefund && !$jaEstornado) {
                \App\Models\FinancialTransaction::create([
                    'reserva_id'     => $masterId,
                    'arena_id'       => $anchorReserva->arena_id,
                    'user_id'        => $anchorReserva->user_id,
                    'manager_id'     => $managerId,
                    'amount'         => -$amountPaidRef,
                    'type'           => \App\Models\FinancialTransaction::TYPE_REFUND,
                    'payment_method' => 'outro',
                    'description'    => "ESTORNO SÉRIE RECORRENTE: " . $reason . " (Master #{$masterId})",
                    'paid_at'        => now(),
                ]);
                $messageFinance = " O valor de R$ " . number_format($amountPaidRef, 2, ',', '.') . " foi estornado do caixa.";
            } elseif (!$shouldRefund && !$jaEstornado) {
                // Se não for estornar, apenas registra a retenção se já não houver transação
                \App\Models\FinancialTransaction::create([
                    'reserva_id'     => $masterId,
                    'arena_id'       => $anchorReserva->arena_id,
                    'user_id'        => $anchorReserva->user_id,
                    'manager_id'     => $managerId,
                    'amount'         => $amountPaidRef,
                    'type'           => \App\Models\FinancialTransaction::TYPE_RETEN_CANC_S_COMP,
                    'payment_method' => 'retained_funds',
                    'description'    => "Retenção de valor de série: " . $reason,
                    'paid_at'        => now(),
                ]);
                $messageFinance = " O valor foi mantido como retenção pela arena.";
            } else {
                $messageFinance = " (O ajuste financeiro já havia sido processado individualmente).";
            }
        }

        // --- 🗓️ LOOP DE EXCLUSÃO ---
        foreach ($seriesReservas as $slot) {

            // Se já está cancelada ou deletada, ignora para não recriar slots verdes duplicados
            if (in_array($slot->status, [\App\Models\Reserva::STATUS_CANCELADA, 'cancelled', 'rejected'])) {
                continue;
            }

            $slotStartDateTime = \Carbon\Carbon::parse($slot->date->format('Y-m-d') . ' ' . $slot->start_time);

            // Não cancela jogos que já passaram hoje
            if ($slotStartDateTime->isPast() && $slot->date->isToday()) {
                continue;
            }

            // 1. Zera o preço final para que o caixa entenda que não há dívida
            $pagoJa = (float)($slot->total_paid ?? 0);
            $slot->update([
                'final_price' => $pagoJa,
                'status' => \App\Models\Reserva::STATUS_CANCELADA
            ]);

            // 2. Libera o slot na agenda (volta a ser verde)
            $this->recreateFixedSlot($slot);

            // 3. Limpa transações de sinal/pagamento individuais para não poluir o histórico
            // (O estorno/retenção mestre feito acima é o que vale para o caixa)
            \App\Models\FinancialTransaction::where('reserva_id', $slot->id)
                ->whereIn('type', [
                    \App\Models\FinancialTransaction::TYPE_SIGNAL,
                    \App\Models\FinancialTransaction::TYPE_PAYMENT
                ])
                ->delete();

            // 4. Deleta a reserva do cliente
            $slot->delete();
            $cancelledCount++;
        }

        return [
            'cancelled_count' => $cancelledCount,
            'message_finance' => $messageFinance,
        ];
    }


    // -------------------------------------------------------------------------
    // 🗓️ MÉTODOS API PARA O DASHBOARD (AGENDAMENTO RÁPIDO)
    // -------------------------------------------------------------------------


    /**
     * API: Cria uma reserva pontual (quick) ou recorrente a partir do Dashboard.
     */
    public function storeQuickReservaApi(Request $request)
    {
        // 1. Validação dos Dados Recebidos
        $validator = Validator::make($request->all(), [
            'date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'end_time' => [
                'required',
                'date_format:H:i',
                function ($attribute, $value, $fail) use ($request) {
                    $startTime = $request->input('start_time');
                    if (($value === '00:00' || $value === '0:00') && $startTime === '23:00') {
                        return;
                    }
                    try {
                        $startTimeCarbon = \Carbon\Carbon::parse($startTime);
                        $endTimeCarbon = \Carbon\Carbon::parse($value);

                        if ($endTimeCarbon->lte($startTimeCarbon) && $value !== '00:00') {
                            $fail('O horário final deve ser posterior ao horário inicial.');
                        }
                    } catch (\Exception $e) {
                        $fail('Formato de horário inválido.');
                    }
                }
            ],
            'fixed_price' => 'nullable|numeric|min:0',
            'reserva_id_to_update' => 'required|exists:reservas,id',
            'arena_id' => 'required',
            'client_name' => 'required|string|max:255',
            'client_contact' => 'required|digits:11|max:255',
            'signal_value' => 'nullable',
            'payment_method' => 'required|string',
            'is_recurrent' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ], [
            'reserva_id_to_update.exists' => 'O slot de horário selecionado não existe ou não está disponível.',
            'client_contact.digits' => 'O WhatsApp deve conter exatamente 11 dígitos (DDD + Número).',
            'arena_id.required' => 'A identificação da quadra é obrigatória para este agendamento.',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $validated = $validator->validated();

        // 🛡️ DEBUG & PROTEÇÃO: Validação de Caixa ANTES de processar
        $financeiroController = app(\App\Http\Controllers\FinanceiroController::class);
        $arenaId = $validated['arena_id'];
        $dataReserva = $validated['date'];

        if ($financeiroController->isCashClosed($dataReserva, $arenaId)) {
            return response()->json([
                'success' => false,
                'message' => "Bloqueio de Segurança: O caixa desta arena para o dia " . date('d/m/Y', strtotime($dataReserva)) . " já está encerrado. Reabra-o para agendar."
            ], 422);
        }

        // 2. Redirecionamento de Lógica: Se for recorrente, delega para o método de série
        if ($request->boolean('is_recurrent')) {
            return $this->storeRecurrentReservaApi($request);
        }

        $reservaIdToUpdate = $validated['reserva_id_to_update'];
        $startTimeRaw = $validated['start_time'];
        $endTimeRaw = $validated['end_time'];

        if ($startTimeRaw === '23:00' && ($endTimeRaw === '0:00' || $endTimeRaw === '00:00')) {
            $validated['end_time'] = '23:59';
        }

        // 3. Verificação do Slot Fixo
        $oldReserva = Reserva::find($reservaIdToUpdate);
        if (!$oldReserva || !$oldReserva->is_fixed || $oldReserva->status !== Reserva::STATUS_FREE) {
            return response()->json(['success' => false, 'message' => 'O slot selecionado não está mais disponível.'], 409);
        }

        // 4. Tratamento do valor do sinal
        if (!empty($validated['signal_value'])) {
            $validated['signal_value'] = (float) str_replace(',', '.', str_replace('.', '', $validated['signal_value']));
        }

        // 5. Processamento/Busca do Cliente
        $clientUser = $this->findOrCreateClient([
            'name' => $validated['client_name'],
            'whatsapp_contact' => $validated['client_contact'],
            'email' => null,
        ]);

        if (!$clientUser) {
            return response()->json(['success' => false, 'message' => 'Erro ao processar dados do cliente.'], 500);
        }

        // 🚀 5.1 TRAVA DE BLACKLIST (is_blocked): Adicionado para respeitar a punição de faltas
        if ($clientUser->is_blocked) {
            return response()->json([
                'success' => false,
                'message' => '🚫 Bloqueio de Blacklist: Este cliente está impedido de realizar novos agendamentos.'
            ], 403);
        }

        $validated['price'] = $validated['fixed_price'];

        DB::beginTransaction();
        try {
            // 6. DELEGA A LÓGICA DE CRIAÇÃO
            $newReserva = $this->createConfirmedReserva($validated, $clientUser, $reservaIdToUpdate);

            // 🏟️ GARANTIA: Força a Arena correta
            $newReserva->update(['arena_id' => $arenaId]);

            DB::commit();

            $message = "Agendamento pontual para {$newReserva->client_name} confirmado com sucesso!";
            if ($newReserva->signal_value > 0) {
                $message .= " Sinal de R$ " . number_format($newReserva->signal_value, 2, ',', '.') . " registrado.";
            }

            return response()->json(['success' => true, 'message' => $message], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro no Agendamento Rápido: " . $e->getMessage());

            if (str_contains($e->getMessage(), 'caixa')) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            $this->recreateFixedSlot($oldReserva);
            return response()->json(['success' => false, 'message' => 'Erro ao salvar reserva: ' . $e->getMessage()], 500);
        }
    }


    /**
     * API: Cria uma série recorrente (6 meses) a partir do Agendamento Rápido do Dashboard.
     */
    public function storeRecurrentReservaApi(Request $request)
    {
        // 1. Validação
        $validator = Validator::make($request->all(), [
            'date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'end_time' => [
                'required',
                'date_format:H:i',
                function ($attribute, $value, $fail) use ($request) {
                    $startTime = $request->input('start_time');
                    if (($value === '0:00' || $value === '00:00') && $startTime === '23:00') {
                        return;
                    }
                    try {
                        $startTimeCarbon = \Carbon\Carbon::createFromFormat('H:i', $startTime);
                        $endTimeCarbon = \Carbon\Carbon::createFromFormat('H:i', $value);

                        if ($endTimeCarbon->lte($startTimeCarbon)) {
                            $fail('O horário final deve ser posterior ao horário inicial.');
                        }
                    } catch (\Exception $e) {
                        $fail('Formato de horário inválido.');
                    }
                }
            ],
            'fixed_price' => 'required|numeric|min:0',
            'arena_id' => 'required',
            'reserva_id_to_update' => 'required|exists:reservas,id',
            'client_name' => 'required|string|max:255',
            'client_contact' => 'required|digits:11|max:255',
            'signal_value' => 'nullable',
            'payment_method' => 'required|string',
            'notes' => 'nullable|string',
        ], [
            'reserva_id_to_update.exists' => 'O slot de horário selecionado não existe ou não está disponível.',
            'client_contact.digits' => 'O WhatsApp deve conter exatamente 11 dígitos (DDD + Número).',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $validated = $validator->validated();

        // 2. Normalização de Valores e Horários
        $price = (float) $validated['fixed_price'];
        $signalValueRaw = $validated['signal_value'] ?? '0,00';
        $signalValue = is_string($signalValueRaw)
            ? (float) str_replace(',', '.', str_replace('.', '', $signalValueRaw))
            : (float) $signalValueRaw;

        // --- 🕒 CORREÇÃO CRÍTICA PARA MEIA-NOITE ---
        $isMidnight = false;
        if ($validated['start_time'] === '23:00' && ($validated['end_time'] === '0:00' || $validated['end_time'] === '00:00')) {
            $validated['end_time'] = '23:59';
            $isMidnight = true;
        }

        $initialDate = Carbon::parse($validated['date']);
        $dayOfWeek = $initialDate->dayOfWeek;
        $startTimeNormalized = Carbon::createFromFormat('H:i', $validated['start_time'])->format('H:i:s');
        $endTimeNormalized = $isMidnight ? '23:59:59' : Carbon::createFromFormat('H:i', $validated['end_time'])->format('H:i:s');

        $scheduleId = $validated['reserva_id_to_update'];
        $arenaId = $validated['arena_id'];
        $endDate = $initialDate->copy()->addMonths(6);

        // 3. Processamento do Cliente
        $clientUser = $this->findOrCreateClient([
            'name' => $validated['client_name'],
            'whatsapp_contact' => $validated['client_contact'],
            'email' => null,
            'data_nascimento' => null,
        ]);

        if (!$clientUser) {
            return response()->json(['success' => false, 'message' => 'Erro interno ao identificar ou criar o cliente.'], 500);
        }

        // 🚀 3.1 TRAVA DE SEGURANÇA: BLACKLIST (is_blocked)
        if ($clientUser->is_blocked) {
            return response()->json([
                'success' => false,
                'message' => '🚫 Bloqueio de Blacklist: Este cliente possui restrições para novos agendamentos mensais.'
            ], 403);
        }

        // 🛡️ TRAVA DE CONFLITO DE MENSALISTA FUTURO (COM DIAGNÓSTICO)
        $futureMensalista = Reserva::where('arena_id', $arenaId)
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', $startTimeNormalized)
            ->where('is_recurrent', true)
            ->where('is_fixed', false)
            ->whereIn('status', [Reserva::STATUS_CONFIRMADA, Reserva::STATUS_CONCLUIDA])
            ->where('date', '>', $initialDate->toDateString())
            ->orderBy('date', 'asc') // Pega o primeiro conflito que encontrar
            ->first();

        if ($futureMensalista) {
            // Convertemos a data para o formato brasileiro para o erro ficar claro
            $dataConflito = \Carbon\Carbon::parse($futureMensalista->date)->format('d/m/Y');

            return response()->json([
                'success' => false,
                'message' => "⚠️ BLOQUEIO: O cliente '{$futureMensalista->client_name}' já é mensalista deste horário a partir do dia {$dataConflito}. Verifique se não há uma reserva recorrente antiga que precisa ser cancelada primeiro."
            ], 422);
        }

        // 4. Coleta datas futuras
        $datesToSchedule = [];
        $dateIterate = $initialDate->copy();
        while ($dateIterate->lte($endDate)) {
            $datesToSchedule[] = $dateIterate->toDateString();
            $dateIterate->addWeek();
        }

        $reservasToCreate = [];
        $fixedSlotsToDelete = [];
        $conflictCount = 0;

        // 5. Verificação de Disponibilidade na Série
        foreach ($datesToSchedule as $dateString) {
            $isFirstDate = $dateString === $initialDate->toDateString();

            $overlapWithReal = Reserva::whereDate('date', $dateString)
                ->where('arena_id', $arenaId)
                ->where('is_fixed', false)
                ->whereIn('status', [Reserva::STATUS_CONFIRMADA, Reserva::STATUS_PENDENTE, Reserva::STATUS_CONCLUIDA])
                ->where(function ($q) use ($startTimeNormalized, $endTimeNormalized) {
                    $q->where('start_time', '<', $endTimeNormalized)
                        ->where('end_time', '>', $startTimeNormalized);
                })->exists();

            $fixedSlotQuery = Reserva::where('is_fixed', true)
                ->where('arena_id', $arenaId)
                ->whereDate('date', $dateString)
                ->where('start_time', $startTimeNormalized)
                ->where('status', Reserva::STATUS_FREE);

            if ($startTimeNormalized === '23:00:00') {
                $fixedSlotQuery->where(function ($q) {
                    $q->where('end_time', '00:00:00')
                        ->orWhere('end_time', 'LIKE', '23:59%');
                });
            } else {
                $fixedSlotQuery->where('end_time', $endTimeNormalized);
            }

            if ($isFirstDate) {
                $fixedSlotQuery->where('id', $scheduleId);
            }

            $fixedSlot = $fixedSlotQuery->first();

            if ($overlapWithReal || !$fixedSlot) {
                $conflictCount++;
                if ($isFirstDate) {
                    return response()->json(['success' => false, 'message' => "O slot inicial desta série já foi ocupado."], 409);
                }
                continue;
            }

            $fixedSlotsToDelete[] = $fixedSlot->id;
            $slotSignal = $isFirstDate ? $signalValue : 0.00;
            $isPaid = (abs($slotSignal - $price) < 0.01 || $slotSignal > $price);

            $reservasToCreate[] = [
                'user_id' => $clientUser->id,
                'arena_id' => $arenaId,
                'manager_id' => Auth::id(),
                'date' => $dateString,
                'day_of_week' => $dayOfWeek,
                'start_time' => $startTimeNormalized,
                'end_time' => $endTimeNormalized,
                'price' => $price,
                'final_price' => $price,
                'signal_value' => $slotSignal,
                'total_paid' => $slotSignal,
                'payment_status' => $isPaid ? 'paid' : ($slotSignal > 0 ? 'partial' : 'pending'),
                'status' => $isPaid ? Reserva::STATUS_CONCLUIDA : Reserva::STATUS_CONFIRMADA,
                'client_name' => $clientUser->name,
                'client_contact' => $clientUser->whatsapp_contact,
                'notes' => $validated['notes'],
                'is_fixed' => false,
                'is_recurrent' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        // 6. Persistência no Banco
        DB::beginTransaction();
        try {
            Reserva::whereIn('id', $fixedSlotsToDelete)->delete();

            $firstReservaData = array_shift($reservasToCreate);
            $masterReserva = Reserva::create($firstReservaData);
            $masterReserva->update(['recurrent_series_id' => $masterReserva->id]);

            $batchReservas = array_map(function ($item) use ($masterReserva) {
                $item['recurrent_series_id'] = $masterReserva->id;
                return $item;
            }, $reservasToCreate);

            if (!empty($batchReservas)) {
                Reserva::insert($batchReservas);
            }

            if ($signalValue > 0) {
                FinancialTransaction::create([
                    'reserva_id' => $masterReserva->id,
                    'arena_id'   => $masterReserva->arena_id,
                    'user_id'    => $clientUser->id,
                    'manager_id' => Auth::id(),
                    'amount'     => $signalValue,
                    'type'       => FinancialTransaction::TYPE_SIGNAL,
                    'payment_method' => $validated['payment_method'],
                    'description' => 'Sinal de série recorrente (Dashboard)',
                    'paid_at'    => Carbon::now(),
                ]);
            }

            DB::commit();

            $total = count($batchReservas) + 1;
            return response()->json([
                'success' => true,
                'message' => "Série de {$total} reservas criada com sucesso!" . ($conflictCount > 0 ? " ({$conflictCount} conflitos pulados)." : "")
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro Série Recorrente: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro: ' . $e->getMessage()], 500);
        }
    }


    // -------------------------------------------------------------------------
    // ✅ MÉTODOS CORRIGIDOS PARA O FULLCALENDAR (API FullCalendar)
    // -------------------------------------------------------------------------

    /**
     * API: Retorna TODAS as reservas e slots fixos para o FullCalendar.
     * Este método unifica a busca de slots (livres/manutenção) e reservas de clientes.
     */
    public function getCalendarEvents(Request $request)
    {
        $start = $request->get('start');
        $end = $request->get('end');
        $arenaId = $request->get('arena_id');

        $visibleStatuses = [
            Reserva::STATUS_CONFIRMADA,
            Reserva::STATUS_PENDENTE,
            Reserva::STATUS_CONCLUIDA,
            Reserva::STATUS_NO_SHOW,
            Reserva::STATUS_LANCADA_CAIXA,
            Reserva::STATUS_FREE,
            Reserva::STATUS_MAINTENANCE,
            Reserva::STATUS_CANCELADA,
            Reserva::STATUS_REJEITADA
        ];

        $query = Reserva::whereBetween('date', [$start, $end])
            ->whereIn('status', $visibleStatuses);

        if ($arenaId) {
            $query->where('arena_id', $arenaId);
        }

        $allEvents = $query->get();

        // 🚀 FORÇAMOS A CONVERSÃO AQUI
        $formattedEvents = $this->mapToFullCalendarEvents($allEvents);

        return response()->json($formattedEvents);
    }


    /**
     * Helper para mapear objetos Reserva para o formato JSON do FullCalendar.
     * 🛑 CORRIGIDO: Removida a lógica de prefixo de título aqui para evitar duplicação no JS.
     */
    /**
     * Helper para mapear objetos Reserva para o formato JSON do FullCalendar.
     * 🛑 CORRIGIDO: Agora garante que os extendedProps contenham todos os dados financeiros
     * para que o Dashboard habilite as opções de estorno e retenção.
     */
    protected function mapToFullCalendarEvents($reservations)
    {
        $events = [];

        foreach ($reservations as $reserva) {

            // --- GARANTIA DE DATA CORRETA ---
            $dateString = $reserva->date->toDateString();

            $startTimeFormat = $reserva->start_time->format('H:i:s');
            $endTimeFormat = $reserva->end_time->format('H:i:s');

            $startDateTime = Carbon::parse($dateString . ' ' . $startTimeFormat);
            $endDateTime = Carbon::parse($dateString . ' ' . $endTimeFormat);
            // ---------------------------------

            $basePrice = number_format($reserva->price, 2, ',', '.');
            $title = $reserva->client_name . ' - R$ ' . $basePrice;
            $color = '#4f46e5';
            $className = 'fc-event-quick';
            $isPaid = false;
            $isFinalized = false;

            $retainedAmount = (float)$reserva->total_paid;
            $finalPriceFloat = (float)$reserva->final_price > 0 ? (float)$reserva->final_price : (float)$reserva->price;
            $isTotalPaid = (abs($retainedAmount - $finalPriceFloat) < 0.01 && $finalPriceFloat > 0);

            if ($reserva->is_fixed) {
                if ($reserva->status === Reserva::STATUS_FREE) {
                    $title = 'LIVRE: R$ ' . $basePrice;
                    $color = '#22c55e';
                    $className = 'fc-event-available';
                    $isFinalized = false;
                } elseif ($reserva->status === Reserva::STATUS_MAINTENANCE) {
                    $title = 'MANUTENÇÃO: Indisponível';
                    $color = '#f59e0b';
                    $className = 'fc-event-maintenance';
                    $isFinalized = true;
                }
            } elseif ($reserva->status === Reserva::STATUS_NO_SHOW) {
                $isFinalized = true;
                $isPaid = ($retainedAmount > 0.00);
                $color = '#E53E3E';
                $className = 'fc-event-no-show';
            } elseif (in_array($reserva->status, [Reserva::STATUS_CONCLUIDA, Reserva::STATUS_LANCADA_CAIXA])) {
                $isFinalized = true;
                $isPaid = true;
                $color = '#10b981';
                $className .= ' fc-event-concluida';
            } elseif (in_array($reserva->status, [Reserva::STATUS_CANCELADA, Reserva::STATUS_REJEITADA, Reserva::STATUS_EXPIRADA])) {
                $isFinalized = true;
                $isPaid = false;
                $color = '#94a3b8';
                $className = 'fc-event-cancelled';
            } elseif ($reserva->status === Reserva::STATUS_PENDENTE) {
                $color = '#ff9800';
                $className = 'fc-event-pending';
            } elseif ($reserva->status === Reserva::STATUS_CONFIRMADA) {
                if ($isTotalPaid) {
                    $isPaid = true;
                    $isFinalized = true;
                    $color = '#10b981';
                    $className .= ' fc-event-concluida';
                } elseif ($retainedAmount > 0) {
                    $isPaid = true;
                }
            }

            if (!$reserva->is_fixed && $reserva->is_recurrent) {
                if (!$isFinalized && $reserva->status !== Reserva::STATUS_PENDENTE) {
                    $color = '#C026D3';
                    $className = str_replace('fc-event-quick', '', $className);
                    $className .= ' fc-event-recurrent';
                }
            }

            $events[] = [
                'id' => $reserva->id,
                'title' => $title,
                'start' => $startDateTime->toDateTimeString(),
                'end' => $endDateTime->toDateTimeString(),
                'backgroundColor' => $color,
                'borderColor' => $color,
                'classNames' => [trim($className)],
                'extendedProps' => [
                    'status' => $reserva->status,
                    'price' => (float)$reserva->price,
                    'final_price' => (float)$reserva->final_price,
                    'signal_value' => (float)$reserva->signal_value,
                    'total_paid' => (float)$reserva->total_paid,
                    'retained_amount' => (float)$retainedAmount,
                    'payment_status' => $reserva->payment_status,
                    'is_recurrent' => (bool)$reserva->is_recurrent,
                    'is_paid' => $isTotalPaid,
                    'is_finalized' => $isFinalized,
                    'is_fixed' => (bool)$reserva->is_fixed,
                ],
            ];
        }

        return $events;
    }


    /**
     * Finaliza o pagamento de uma reserva e, opcionalmente, atualiza o preço de reservas futuras da série.
     */
    public function finalizarPagamento(Request $request, $reservaId)
    {
        // 1. Busca a Reserva manualmente
        $reserva = Reserva::find($reservaId);

        if (!$reserva) {
            Log::error("Reserva não encontrada para o ID {$reservaId} durante finalizarPagamento.");
            return response()->json(['success' => false, 'message' => 'Reserva não encontrada.'], 404);
        }

        // 🎯 VALIDAÇÃO DE SEGURANÇA AJUSTADA: CAIXA FECHADO POR ARENA
        $financeiroController = app(FinanceiroController::class);
        $reservaDate = Carbon::parse($reserva->date)->toDateString();

        // ✅ CORREÇÃO: Passamos o arena_id da reserva para isolar o bloqueio
        if ($financeiroController->isCashClosed($reservaDate, $reserva->arena_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Erro: Não é possível finalizar o pagamento. O caixa desta quadra específica para o dia ' . Carbon::parse($reservaDate)->format('d/m/Y') . ' está fechado. Reabra o caixa da arena para continuar.',
            ], 403);
        }

        // 2. Validação dos dados de entrada
        $request->validate([
            'final_price' => 'required|numeric|min:0',
            'amount_paid' => 'required|numeric|min:0',
            'payment_method' => 'required|string|max:50',
            'apply_to_series' => 'sometimes|boolean',
        ], [
            'final_price.required' => 'O preço final é obrigatório.',
            'amount_paid.required' => 'O valor recebido é obrigatório.',
            'payment_method.required' => 'O método de pagamento é obrigatório.',
        ]);

        DB::beginTransaction();
        try {
            $finalPrice = (float) $request->final_price;
            $amountPaidNow = (float) $request->amount_paid;
            $signalAmount = (float) ($reserva->total_paid ?? 0);

            // Total pago após esta transação
            $newTotalPaid = $signalAmount + $amountPaidNow;

            // Define o novo status de pagamento
            $paymentStatus = 'partial';
            if (abs($newTotalPaid - $finalPrice) < 0.01 || $newTotalPaid > $finalPrice) {
                $paymentStatus = 'paid';
            } elseif ($newTotalPaid == 0) {
                $paymentStatus = 'unpaid';
            }

            $newReservaStatus = $paymentStatus === 'paid'
                ? Reserva::STATUS_CONCLUIDA
                : Reserva::STATUS_CONFIRMADA;

            if ($reserva->status === Reserva::STATUS_NO_SHOW) {
                $newReservaStatus = Reserva::STATUS_NO_SHOW;
            }

            // --- 2. Atualiza a Reserva Atual ---
            $reserva->update([
                'final_price' => $finalPrice,
                'total_paid' => $newTotalPaid,
                'payment_status' => $paymentStatus,
                'manager_id' => Auth::id(),
                'status' => $newReservaStatus,
            ]);

            // 2.1. GERA TRANSAÇÃO FINANCEIRA (PAGAMENTO DO RESTANTE)
            if ($amountPaidNow > 0) {
                FinancialTransaction::create([
                    'reserva_id'     => $reserva->id,
                    'arena_id'       => $reserva->arena_id, // ✅ Vínculo correto com a arena
                    'user_id'        => $reserva->user_id,
                    'manager_id'     => Auth::id(),
                    'amount'         => $amountPaidNow,
                    'type'           => FinancialTransaction::TYPE_PAYMENT,
                    'payment_method' => $request->payment_method,
                    'description'    => 'Pagamento final/parcial da reserva',
                    'paid_at'        => Carbon::now(),
                ]);
            }

            // --- 3. Lógica para Recorrência: PROPAGAÇÃO DE PREÇO ---
            if ($request->boolean('apply_to_series') && $reserva->is_recurrent) {
                $newPriceForSeries = $finalPrice;
                $masterId = $reserva->recurrent_series_id ?? $reserva->id;
                $reservaDateStr = Carbon::parse($reserva->date)->toDateString();

                try {
                    $updatedCount = Reserva::where(function ($query) use ($masterId) {
                        $query->where('recurrent_series_id', $masterId)
                            ->orWhere('id', $masterId);
                    })
                        ->whereDate('date', '>', $reservaDateStr)
                        ->where('start_time', $reserva->start_time)
                        ->where('end_time', $reserva->end_time)
                        ->where('is_fixed', false)
                        ->where('status', Reserva::STATUS_CONFIRMADA)
                        ->where('price', '!=', $newPriceForSeries)
                        ->update([
                            'price' => $newPriceForSeries,
                            'final_price' => $newPriceForSeries,
                            'manager_id' => Auth::id(),
                        ]);

                    if ($updatedCount > 0) {
                        $message = "Pagamento finalizado e preço da série atualizado com sucesso! ({$updatedCount} reservas alteradas)";
                    } else {
                        $message = "Pagamento finalizado. Preço da série recorrente já estava atualizado.";
                    }
                } catch (\Exception $e) {
                    Log::error("Erro na query de propagação de preço para Master ID {$masterId}: " . $e->getMessage());
                    throw $e;
                }
            } else {
                $message = "Pagamento finalizado com sucesso!";
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => $message]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro no processo de finalizarPagamento (ID: {$reservaId}): " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao finalizar pagamento: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * NOVO MÉTODO: Marca uma reserva como Falta (No-Show) e gerencia o estorno/retenção.
     * (Este método é o endpoint original, mas delega o trabalho centralizado a finalizeStatus)
     */
    public function no_show(Request $request, Reserva $reserva)
    {
        // 1. Validação de Dados
        $validated = $request->validate([
            'no_show_reason' => 'required|string|min:5|max:255',
            'should_refund' => 'required|boolean', // Flag se deve devolver o valor pago
            'paid_amount' => 'required|numeric|min:0', // Valor total que o cliente pagou/sinalizou
        ], [
            'no_show_reason.required' => 'O motivo da falta é obrigatório.',
            'no_show_reason.min' => 'O motivo deve ter pelo menos 5 caracteres.',
            'paid_amount.required' => 'O valor pago é obrigatório para o gerenciamento de estorno.',
        ]);

        // 🎯 VALIDAÇÃO DE SEGURANÇA AJUSTADA: CAIXA FECHADO POR ARENA
        $financeiroController = app(FinanceiroController::class);
        $reservaDate = Carbon::parse($reserva->date)->toDateString();

        // ✅ CORREÇÃO: Passamos o arena_id da reserva.
        // Se o Vôlei estiver aberto e o Futebol fechado, esta verificação agora permite o No-Show no Vôlei.
        if ($financeiroController->isCashClosed($reservaDate, $reserva->arena_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Erro: Não é possível registrar a falta/estorno. O caixa desta quadra específica para o dia ' . Carbon::parse($reservaDate)->format('d/m/Y') . ' está fechado.'
            ], 403);
        }
        // FIM DA VALIDAÇÃO DE SEGURANÇA

        if ($reserva->status === Reserva::STATUS_NO_SHOW) {
            return response()->json(['success' => false, 'message' => 'Esta reserva já foi marcada como falta.'], 400);
        }

        DB::beginTransaction();
        try {
            // Delega a lógica centralizada para finalizeStatus.
            // Como o finalizeStatus usa FinancialTransaction::create, os ajustes que fizemos
            // no boot do Model garantirão que a transação de estorno ou retenção seja aceita.
            $result = $this->finalizeStatus(
                $reserva,
                Reserva::STATUS_NO_SHOW,
                '[Gestor] ' . $validated['no_show_reason'],
                $validated['should_refund'],
                (float) $validated['paid_amount']
            );

            DB::commit();

            $message = "Reserva ID {$reserva->id} marcada como FALTA. " . $result['message_finance'];
            return response()->json(['success' => true, 'message' => $message], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            $logMessage = "Erro fatal ao marcar falta (No-Show) ID: {$reserva->id}: " . $e->getMessage();
            Log::error($logMessage, ['exception' => $e]);

            // Se o erro vier do bloqueio do Model, ele será capturado aqui
            return response()->json([
                'success' => false,
                'message' => 'Erro ao registrar a falta: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Confirma uma reserva pendente, cria a série (se recorrente) e registra o sinal.
     */
    public function confirmar(Request $request, Reserva $reserva)
    {
        // 1. Validação de Segurança AJUSTADA: Caixa Fechado por Arena
        $financeiroController = app(FinanceiroController::class);
        $reservaDateStr = $reserva->date->toDateString();

        // ✅ CORREÇÃO: Passamos o arena_id da reserva para validar apenas esta quadra
        if ($financeiroController->isCashClosed($reservaDateStr, $reserva->arena_id)) {
            return redirect()->back()->with('error', 'Erro: Não é possível confirmar. O caixa desta quadra específica para o dia ' . $reserva->date->format('d/m/Y') . ' está fechado. Reabra o caixa da arena para continuar.');
        }

        // 2. Validação dos dados do Modal
        $validated = $request->validate([
            'signal_value' => 'nullable|numeric|min:0',
            'is_recurrent' => 'nullable|boolean',
            'payment_method' => 'required|string',
        ]);

        $isRecurrent = $request->boolean('is_recurrent');
        $signalValue = (float)($validated['signal_value'] ?? 0.00);

        DB::beginTransaction();
        try {
            // 3. Atualiza a Reserva Mestra
            $isTotalPaid = (abs($signalValue - $reserva->price) < 0.01 || $signalValue > $reserva->price);

            $reserva->update([
                'status' => $isTotalPaid ? Reserva::STATUS_CONCLUIDA : Reserva::STATUS_CONFIRMADA,
                'signal_value' => $signalValue,
                'total_paid' => $signalValue,
                'is_recurrent' => $isRecurrent,
                'manager_id' => Auth::id(),
                'final_price' => $reserva->price,
                'payment_status' => $isTotalPaid ? 'paid' : ($signalValue > 0 ? 'partial' : 'pending'),
                'recurrent_series_id' => $isRecurrent ? $reserva->id : null,
            ]);

            // 4. Consome o slot fixo
            $this->consumeFixedSlot($reserva);

            // 5. Registra o Sinal no Financeiro
            if ($signalValue > 0) {
                FinancialTransaction::create([
                    'reserva_id' => $reserva->id,
                    'arena_id'   => $reserva->arena_id,
                    'user_id'    => $reserva->user_id,
                    'manager_id' => Auth::id(),
                    'amount'     => $signalValue,
                    'type'       => FinancialTransaction::TYPE_SIGNAL,
                    'payment_method' => $validated['payment_method'],
                    'description' => "Sinal recebido na confirmação da reserva (#{$reserva->id}).",
                    'paid_at'    => now(),
                ]);
            }

            // 6. LÓGICA DE RECORRÊNCIA
            $criadasCount = 0;
            if ($isRecurrent) {
                $startDate = $reserva->date->copy()->addWeek();
                $endDate = $reserva->date->copy()->addMonths(6);

                while ($startDate->lte($endDate)) {
                    $dateString = $startDate->toDateString();

                    // Verifica se o horário está livre NA MESMA QUADRA
                    $hasConflict = Reserva::where('date', $dateString)
                        ->where('arena_id', $reserva->arena_id)
                        ->where('start_time', $reserva->start_time)
                        ->where('is_fixed', false)
                        ->whereIn('status', [Reserva::STATUS_CONFIRMADA, Reserva::STATUS_CONCLUIDA, Reserva::STATUS_PENDENTE])
                        ->exists();

                    if (!$hasConflict) {
                        $novaReserva = $reserva->replicate();
                        $novaReserva->date = $dateString;
                        $novaReserva->status = Reserva::STATUS_CONFIRMADA;
                        $novaReserva->signal_value = 0;
                        $novaReserva->total_paid = 0;
                        $novaReserva->payment_status = 'pending';
                        $novaReserva->recurrent_series_id = $reserva->id;
                        $novaReserva->save();

                        // Remove o slot verde daquela semana específica
                        Reserva::where('is_fixed', true)
                            ->where('arena_id', $reserva->arena_id)
                            ->whereDate('date', $dateString)
                            ->where('start_time', $reserva->start_time)
                            ->delete();

                        $criadasCount++;
                    }
                    $startDate->addWeek();
                }
                Log::info("Série mensal gerada: {$criadasCount} jogos criados para cliente {$reserva->client_name}.");
            }

            // 7. Limpeza: Rejeita outros pendentes "atropelados" nesta quadra e horário
            Reserva::where('date', $reserva->date)
                ->where('arena_id', $reserva->arena_id)
                ->where('start_time', $reserva->start_time)
                ->where('id', '!=', $reserva->id)
                ->where('status', Reserva::STATUS_PENDENTE)
                ->update([
                    'status' => Reserva::STATUS_REJEITADA,
                    'cancellation_reason' => 'Horário ocupado por outra reserva confirmada.',
                    'manager_id' => Auth::id()
                ]);

            DB::commit();

            // --- 📢 RETORNO COM FEEDBACK REAL ---
            if ($isRecurrent) {
                if ($criadasCount > 0) {
                    return redirect()->back()->with('success', "Mensalista confirmado! Jogo de hoje garantido + {$criadasCount} jogos futuros agendados.");
                } else {
                    return redirect()->back()->with('warning', "A reserva de HOJE foi confirmada, mas a série recorrente não pôde ser criada porque os horários futuros já estão ocupados.");
                }
            }

            return redirect()->back()->with('success', "Reserva confirmada com sucesso!");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao confirmar reserva: " . $e->getMessage());
            return redirect()->back()->with('error', 'Erro interno ao processar: ' . $e->getMessage());
        }
    }

    /**
     * NOVO MÉTODO: Rejeita uma reserva pendente.
     */
    public function rejeitar(Request $request, Reserva $reserva)
    {
        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:255',
        ]);

        $financeiroController = app(FinanceiroController::class);
        $reservaDateStr = $reserva->date->toDateString();

        // 1. 🎯 VALIDAÇÃO DE CAIXA AJUSTADA: Agora com arena_id
        // Se o caixa do Futebol estiver fechado, você ainda consegue rejeitar uma pendência no Vôlei.
        if ($financeiroController->isCashClosed($reservaDateStr, $reserva->arena_id)) {
            return redirect()->back()->with('error', 'Erro: O caixa desta arena específica para o dia ' . $reserva->date->format('d/m/Y') . ' está fechado. Reabra-o para processar a rejeição.');
        }

        if ($reserva->status !== Reserva::STATUS_PENDENTE) {
            return redirect()->back()->with('error', 'Esta reserva já foi processada.');
        }

        DB::beginTransaction();
        try {
            // 2. Atualiza para REJEITADA
            $reserva->update([
                'status' => Reserva::STATUS_REJEITADA,
                'cancellation_reason' => $validated['rejection_reason'] ?? 'Rejeitada pela administração.',
                'manager_id' => Auth::id()
            ]);

            // 3. Lógica Inteligente de Inventário 🏟️
            // Já estava filtrando por arena_id, o que é excelente.
            $hasOtherInterests = Reserva::where('date', $reserva->date)
                ->where('arena_id', $reserva->arena_id)
                ->where('start_time', $reserva->start_time)
                ->where('id', '!=', $reserva->id)
                ->whereIn('status', [Reserva::STATUS_PENDENTE, Reserva::STATUS_CONFIRMADA, Reserva::STATUS_CONCLUIDA])
                ->exists();

            if (!$hasOtherInterests) {
                // Ao recriar o slot verde, o Model Reserva validará o arena_id.
                $this->recreateFixedSlot($reserva);
            }

            DB::commit();

            return redirect()->back()->with('success', "Reserva de {$reserva->client_name} rejeitada. " .
                ($hasOtherInterests ? "Existem outros interessados pendentes." : "O horário voltou a ficar livre."));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao rejeitar reserva ID {$reserva->id}: " . $e->getMessage());
            return redirect()->back()->with('error', 'Erro interno ao processar a rejeição: ' . $e->getMessage());
        }
    }


    /**
     * NOVO MÉTODO: Converte uma reserva PONTUAL CONFIRMADA em uma série recorrente (Mestra) e cria as cópias futuras (6 meses).
     */
    public function convertPunctualToRecurrent(Request $request, Reserva $reserva)
    {
        // 1. Checagens iniciais
        if ($reserva->is_fixed || $reserva->is_recurrent) {
            return redirect()->back()->with('error', 'Esta reserva já é um slot fixo ou já faz parte de uma série recorrente.');
        }

        if ($reserva->status !== Reserva::STATUS_CONFIRMADA) {
            return redirect()->back()->with('error', 'Apenas reservas com status CONFIRMADO podem ser convertidas em séries.');
        }

        DB::beginTransaction();
        try {
            // 2. Transforma a reserva atual em Mestra da Série
            $masterId = $reserva->id;
            $arenaId = $reserva->arena_id; // 🏟️ CAPTURA O ID DA QUADRA

            $reserva->is_recurrent = true;
            $reserva->recurrent_series_id = $masterId;
            $reserva->manager_id = Auth::id();
            $reserva->save();

            // 3. Define a janela de agendamento (Da próxima semana até 6 meses)
            $masterDate = $reserva->date;
            $startDate = $masterDate->copy()->addWeek();
            $endDate = $masterDate->copy()->addMonths(6);

            // Parâmetros da série
            $dayOfWeek = $reserva->day_of_week;
            $startTime = $reserva->start_time;
            $endTime = $reserva->end_time;
            $price = $reserva->price;
            $clientName = $reserva->client_name;
            $clientContact = $reserva->client_contact;
            $userId = $reserva->user_id;
            $managerId = Auth::id();

            $newReservasToCreate = [];
            $conflictedOrSkippedCount = 0;
            $currentDate = $startDate->copy();

            while ($currentDate->lessThanOrEqualTo($endDate)) {
                $dateString = $currentDate->toDateString();
                $isConflict = false;

                // 🏟️ CHECAGEM DE CONFLITO: Filtrando por ARENA_ID
                $isOccupiedByOtherCustomer = Reserva::whereDate('date', $dateString)
                    ->where('arena_id', $arenaId) // 🎯 Filtro adicionado
                    ->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime)
                    ->where('is_fixed', false)
                    ->whereIn('status', [Reserva::STATUS_CONFIRMADA, Reserva::STATUS_PENDENTE])
                    ->exists();

                if ($isOccupiedByOtherCustomer) {
                    $isConflict = true;
                }

                // 🏟️ BUSCA SLOT FIXO: Filtrando por ARENA_ID
                $fixedSlot = null;
                if (!$isConflict) {
                    $fixedSlot = Reserva::where('is_fixed', true)
                        ->where('arena_id', $arenaId) // 🎯 Filtro adicionado
                        ->whereDate('date', $dateString)
                        ->where('start_time', $startTime)
                        ->where('end_time', $endTime)
                        ->where('status', Reserva::STATUS_FREE)
                        ->first();
                }

                if (!$isConflict && $fixedSlot) {
                    $newReservasToCreate[] = [
                        'user_id' => $userId,
                        'arena_id' => $arenaId, // 🎯 GARANTE O VÍNCULO COM A QUADRA CERTA
                        'manager_id' => $managerId,
                        'date' => $dateString,
                        'day_of_week' => $dayOfWeek,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'price' => $price,
                        'final_price' => $price,
                        'signal_value' => 0.00,
                        'total_paid' => 0.00,
                        'payment_status' => 'pending',
                        'client_name' => $clientName,
                        'client_contact' => $clientContact,
                        'notes' => $reserva->notes,
                        'status' => Reserva::STATUS_CONFIRMADA,
                        'is_fixed' => false,
                        'is_recurrent' => true,
                        'recurrent_series_id' => $masterId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $fixedSlot->delete();
                } else {
                    $conflictedOrSkippedCount++;
                }

                $currentDate->addWeek();
            }

            if (!empty($newReservasToCreate)) {
                Reserva::insert($newReservasToCreate);
            }

            DB::commit();

            $totalCreated = count($newReservasToCreate) + 1;
            $successMessage = "Conversão concluída! Série criada para a Quadra específica.";

            if ($conflictedOrSkippedCount > 0) {
                $successMessage .= " Nota: {$conflictedOrSkippedCount} semanas puladas por conflito nesta quadra.";
            }

            return redirect()->back()->with('success', $successMessage);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro na conversão: " . $e->getMessage());
            return redirect()->back()->with('error', 'Erro interno: ' . $e->getMessage());
        }
    }


    /**
     * Atualiza o status de um slot fixo de inventário (usado na view de Todas as Reservas).
     */
    public function toggleFixedReservaStatus(Request $request, Reserva $reserva)
    {
        // 1. Validação básica para garantir que é um slot fixo
        if (!$reserva->is_fixed) {
            return response()->json(['success' => false, 'message' => 'Esta não é uma reserva de inventário fixo.'], 400);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in([Reserva::STATUS_FREE, Reserva::STATUS_MAINTENANCE])],
        ]);

        // 2. Checa se o status atual já é o solicitado (evita escrita desnecessária)
        if ($reserva->status === $validated['status']) {
            $message = 'O status já está definido como ' . $validated['status'];
            return response()->json(['success' => false, 'message' => $message], 400);
        }

        // 3. Checagem de integridade (Não pode sair de maintenance/free se houver conflito de cliente)
        if ($validated['status'] === Reserva::STATUS_FREE) {
            // Ao tentar retornar para FREE, verifica se há algum cliente com pending/confirmed
            $overlap = Reserva::where('date', $reserva->date)
                ->where('start_time', $reserva->start_time)
                ->where('end_time', $reserva->end_time)
                ->where('is_fixed', false)
                ->whereIn('status', [Reserva::STATUS_CONFIRMADA, Reserva::STATUS_PENDENTE])
                ->exists();

            if ($overlap) {
                return response()->json(['success' => false, 'message' => 'Impossível reverter para LIVRE. Há uma reserva de cliente (confirmada/pendente) ocupando este horário.'], 400);
            }
        }


        DB::beginTransaction();
        try {
            $reserva->status = $validated['status'];
            $reserva->manager_id = Auth::id(); // Registra quem mudou o status
            $reserva->save();

            DB::commit();

            $message = $reserva->status === Reserva::STATUS_FREE ? 'Slot fixo disponibilizado (Livre) com sucesso.' : 'Slot fixo marcado como Manutenção (Indisponível) com sucesso.';
            return response()->json(['success' => true, 'message' => $message], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao alterar status do slot fixo ID: {$reserva->id}.", ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Erro interno ao alterar status: ' . $e->getMessage()], 500);
        }
    }


    // -------------------------------------------------------------------------
    // LÓGICA DE RENOVAÇÃO
    // -------------------------------------------------------------------------

    /**
     * Encontra a data máxima de uma série recorrente (que não seja um slot fixo).
     */
    protected function getSeriesMaxDate(int $masterId): ?Carbon
    {
        $maxDate = Reserva::where(function ($query) use ($masterId) {
            $query->where('recurrent_series_id', $masterId)
                ->orWhere('id', $masterId);
        })
            ->where('is_recurrent', true)
            ->where('is_fixed', false)
            ->where('status', Reserva::STATUS_CONFIRMADA)
            ->max('date');

        return $maxDate ? Carbon::parse($maxDate) : null;
    }

    /**
     * Identifica as séries recorrentes ativas que estão terminando nos próximos 60 dias.
     */
    public function getEndingRecurrentSeries(): array
    {
        $cutoffDate = Carbon::now()->addDays(60)->endOfDay();
        $today = Carbon::now()->startOfDay();

        $latestReservations = Reserva::selectRaw('recurrent_series_id, MAX(date) as last_date, MIN(date) as first_date, MIN(start_time) as slot_time, MAX(price) as slot_price, day_of_week, client_name')
            ->where('is_recurrent', true)
            ->where('is_fixed', false)
            ->where('status', Reserva::STATUS_CONFIRMADA)
            ->groupBy('recurrent_series_id', 'day_of_week', 'client_name')
            ->get();

        $expiringSeries = [];

        foreach ($latestReservations as $latest) {
            if ($latest->recurrent_series_id === null) {
                continue;
            }

            $lastDate = Carbon::parse($latest->last_date);
            $firstDate = Carbon::parse($latest->first_date);

            // 1. Condição principal: A série está no período de expiração (próximos 60 dias)?
            if ($lastDate->greaterThanOrEqualTo($today) && $lastDate->lessThanOrEqualTo($cutoffDate)) {

                // 2. FILTRO DE SEGURANÇA: Se a série tem menos de 7 dias de duração, ignora.
                if ($lastDate->diffInDays($firstDate) <= 7) {
                    continue;
                }

                $masterReserva = Reserva::find($latest->recurrent_series_id);

                if ($masterReserva) {
                    $expiringSeries[] = [
                        'master_id' => $masterReserva->id,
                        'client_name' => $masterReserva->client_name,
                        'slot_time' => Carbon::parse($latest->slot_time)->format('H:i'),
                        'slot_price' => $latest->slot_price,
                        'day_of_week' => $masterReserva->day_of_week,
                        'last_date' => $lastDate->format('Y-m-d'),
                    ];
                }
            }
        }

        return $expiringSeries;
    }


    /**
     * API: Estende uma série de reservas recorrentes por mais 6 meses.
     */
    public function renewRecurrentSeries(Request $request, Reserva $masterReserva)
    {
        if (!$masterReserva->is_recurrent || $masterReserva->id !== $masterReserva->recurrent_series_id) {
            return response()->json(['success' => false, 'message' => 'A reserva fornecida não é a mestra de uma série recorrente.'], 400);
        }

        // 1. Encontrar a data de expiração ATUAL (última data na série)
        $currentMaxDate = $this->getSeriesMaxDate($masterReserva->id);

        if (!$currentMaxDate) {
            return response()->json(['success' => false, 'message' => 'Nenhuma reserva confirmada encontrada para esta série.'], 404);
        }

        // 2. Definir a janela de renovação
        $startDate = $currentMaxDate->copy()->addWeek();
        $endDate = $currentMaxDate->copy()->addMonths(6);

        if ($startDate->greaterThan($endDate)) {
            return response()->json(['success' => false, 'message' => 'A série já está totalmente coberta até ' . $endDate->format('d/m/Y') . '.'], 400);
        }

        // Parâmetros da série
        $dayOfWeek = $masterReserva->day_of_week;
        $startTime = $masterReserva->start_time;
        $endTime = $masterReserva->end_time;
        $price = $masterReserva->price;
        $clientName = $masterReserva->client_name;
        $userId = $masterReserva->user_id;
        $masterId = $masterReserva->id;
        $managerId = Auth::id();

        $newReservasCount = 0;

        DB::beginTransaction();
        try {
            // 3. Loop de renovação: Avança de semana em semana
            $currentDate = $startDate->copy();
            $conflictedOrSkippedCount = 0;

            while ($currentDate->lessThanOrEqualTo($endDate)) {
                $dateString = $currentDate->toDateString();
                $isConflict = false;

                // 3.1. Checagem de Duplicação (dentro da própria série)
                $isDuplicate = Reserva::whereDate('date', $dateString)
                    ->where('start_time', $startTime)
                    ->where('end_time', $endTime)
                    ->where('recurrent_series_id', $masterId)
                    ->where('is_fixed', false)
                    ->whereIn('status', [Reserva::STATUS_CONFIRMADA, Reserva::STATUS_PENDENTE])
                    ->exists();

                if ($isDuplicate) {
                    $isConflict = true;
                }

                // 3.2. Checagem de Conflito (Outros Clientes)
                if (!$isConflict) {
                    $isOccupiedByRealCustomer = Reserva::whereDate('date', $dateString)
                        ->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $startTime)
                        ->where('is_fixed', false)
                        ->where('recurrent_series_id', '!=', $masterId)
                        ->whereIn('status', [Reserva::STATUS_CONFIRMADA, Reserva::STATUS_PENDENTE])
                        ->exists();

                    if ($isOccupiedByRealCustomer) {
                        $isConflict = true;
                    }
                }

                // 3.3. Busca o slot fixo, se existir, para DELETAR (consumir)
                $fixedSlot = null;
                if (!$isConflict) {
                    $fixedSlot = Reserva::where('is_fixed', true)
                        ->whereDate('date', $dateString)
                        ->where('start_time', $startTime)
                        ->where('end_time', $endTime)
                        ->where('status', Reserva::STATUS_FREE)
                        ->first();
                }

                // 3.4. Cria a nova reserva se não houver conflito REAL nem duplicação
                if (!$isConflict && $fixedSlot) { // Adicionado check for $fixedSlot
                    Reserva::create([
                        'user_id' => $userId,
                        'manager_id' => $managerId,
                        'date' => $dateString,
                        'day_of_week' => $dayOfWeek,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'price' => $price,
                        'final_price' => $price,
                        'signal_value' => 0.00,
                        'total_paid' => 0.00,
                        'payment_status' => 'pending',
                        'client_name' => $clientName,
                        'client_contact' => $masterReserva->client_contact,
                        'status' => Reserva::STATUS_CONFIRMADA,
                        'is_fixed' => false,
                        'is_recurrent' => true,
                        'recurrent_series_id' => $masterId,
                    ]);
                    $newReservasCount++;

                    $fixedSlot->delete(); // Consome o slot verde/FREE
                } else {
                    $conflictedOrSkippedCount++;
                }

                $currentDate->addWeek();
            }

            DB::commit();

            if ($newReservasCount > 0) {
                $message = "Série #{$masterId} de '{$clientName}' renovada com sucesso! Foram adicionadas {$newReservasCount} novas reservas, estendendo o prazo até " . $endDate->format('d/m/Y') . ".";

                if ($conflictedOrSkippedCount > 0) {
                    $message .= " Atenção: {$conflictedOrSkippedCount} slots foram pulados devido a conflitos, duplicações anteriores ou ausência de slot fixo.";
                }

                return response()->json([
                    'success' => true,
                    'message' => $message,
                ], 200);
            } else {
                $message = "Falha na renovação: Nenhuma nova reserva foi adicionada. Total de slots pulados: {$conflictedOrSkippedCount}.";
                return response()->json([
                    'success' => false,
                    'message' => $message
                ], 400);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro fatal na renovação de série #{$masterId}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro interno do servidor ao renovar a série: ' . $e->getMessage()], 500);
        }
    }


    /**
     * Permite ao cliente cancelar uma reserva pontual ou solicitar o cancelamento de uma série recorrente.
     */
    public function cancelByCustomer(Request $request, Reserva $reserva)
    {
        $user = Auth::user();

        // 1. Bloqueio de Segurança: A reserva pertence ao usuário logado?
        if (!$user || $reserva->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Não autorizado.'], 403);
        }

        // 2. Validação dos dados
        $validated = $request->validate([
            'cancellation_reason' => 'required|string|min:5|max:255',
            'is_series_cancellation' => 'nullable|boolean',
        ]);

        $isSeriesRequest = (bool)($request->input('is_series_cancellation') ?? false);
        $reason = $validated['cancellation_reason'];

        // 3. Regra de Tempo: Cancelamento permitido até 24h antes (Exemplo de regra de negócio)
        $reservaDateTime = Carbon::parse($reserva->date->format('Y-m-d') . ' ' . $reserva->start_time);

        /* Descomente se quiser aplicar a trava de 24h
    if ($reservaDateTime->diffInHours(now()) < 24 && $reservaDateTime->isFuture()) {
        return response()->json(['message' => 'Cancelamentos pelo portal só são permitidos com 24h de antecedência. Entre em contato com o suporte.'], 400);
    }
    */

        if ($reservaDateTime->isPast()) {
            return response()->json(['message' => 'Não é possível cancelar uma reserva que já aconteceu.'], 400);
        }

        if (in_array($reserva->status, [Reserva::STATUS_CANCELADA, Reserva::STATUS_REJEITADA])) {
            return response()->json(['message' => 'Esta reserva já não está ativa.'], 400);
        }

        // =====================================================================
        // FLUXO 1: SOLICITAÇÃO DE CANCELAMENTO DE SÉRIE (RECORRENTE)
        // =====================================================================
        if ($reserva->is_recurrent && $isSeriesRequest) {
            $masterReservaId = $reserva->recurrent_series_id ?? $reserva->id;
            $masterReserva = Reserva::find($masterReservaId);

            if (!$masterReserva) {
                return response()->json(['message' => 'Série não encontrada.'], 500);
            }

            if (str_contains($masterReserva->cancellation_reason, '[PENDENTE GESTOR]')) {
                return response()->json(['message' => 'Já existe uma solicitação em análise para esta mensalidade.'], 400);
            }

            DB::beginTransaction();
            try {
                $now = now()->format('d/m/Y H:i');
                // Identifica a Arena na nota para o gestor
                $arenaName = $reserva->arena->name ?? "Arena #{$reserva->arena_id}";

                $newNote = "🚨 [SOLICITAÇÃO DE CANCELAMENTO DE MENSALIDADE]\n";
                $newNote .= "Data: {$now} | Quadra: {$arenaName}\n";
                $newNote .= "Motivo do Cliente: {$reason}\n";
                $newNote .= "-------------------------------------------\n\n" . $masterReserva->notes;

                $masterReserva->update([
                    'notes' => Str::limit($newNote, 5000),
                    'cancellation_reason' => '[PENDENTE GESTOR] Cliente solicitou cancelamento da série.'
                ]);

                DB::commit();
                return response()->json(['success' => true, 'message' => 'Solicitação enviada ao gestor!'], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['message' => 'Erro ao processar solicitação.'], 500);
            }
        }

        // =====================================================================
        // FLUXO 2: CANCELAMENTO DIRETO (PONTUAL)
        // =====================================================================
        if (!$reserva->is_recurrent) {
            DB::beginTransaction();
            try {
                $arenaName = $reserva->arena->name ?? "Arena #{$reserva->arena_id}";

                $reserva->update([
                    'status' => Reserva::STATUS_CANCELADA,
                    'cancellation_reason' => "[Portal Cliente] Motivo: {$reason} (Quadra: {$arenaName})",
                    'manager_id' => null // Indica que foi o cliente quem fez
                ]);

                // 🏟️ Libera o slot verde APENAS na quadra específica
                $this->recreateFixedSlot($reserva);

                DB::commit();
                return response()->json(['success' => true, 'message' => 'Reserva cancelada. O horário foi liberado na ' . $arenaName], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Erro no cancelamento pelo cliente: " . $e->getMessage());
                return response()->json(['message' => 'Erro ao cancelar a reserva.'], 500);
            }
        }

        return response()->json(['message' => 'Para mensalistas, selecione "Cancelar Série".'], 400);
    }


    /**
     * Salva a pré-reserva (Formulário Público) - FLUXO SEM LOGIN.
     */
    public function storePublic(Request $request)
    {
        // 1. Regras de Validação (Formato H:i e Vínculo com Arena)
        $rules = [
            'arena_id' => ['required', 'exists:arenas,id'],
            'data_reserva' => ['required', 'date', "after_or_equal:" . Carbon::today()->format('Y-m-d')],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fim' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'price' => ['required', 'numeric', 'min:0'],
            'schedule_id' => [
                'required',
                'integer',
                \Illuminate\Validation\Rule::exists('reservas', 'id')->where(function ($query) use ($request) {
                    return $query->where('arena_id', $request->arena_id)
                        ->where('is_fixed', 1)
                        ->where('status', \App\Models\Reserva::STATUS_FREE);
                }),
            ],
            'nome_cliente' => 'required|string|max:255',
            'contato_cliente' => 'required|string|regex:/^\d{10,11}$/|max:20',
            'email_cliente' => 'nullable|email|max:255',
            'notes' => 'nullable|string|max:500',
            'signal_value' => 'nullable|numeric|min:0',
        ];

        $validator = Validator::make($request->all(), $rules, [
            'arena_id.required' => 'A identificação da quadra é obrigatória.',
            'schedule_id.exists' => 'O horário selecionado não é válido para esta quadra ou já foi ocupado.',
            'contato_cliente.regex' => 'O WhatsApp deve conter apenas DDD + número (10 ou 11 dígitos).',
            'hora_inicio.date_format' => 'Formato de hora inicial inválido.',
            'hora_fim.date_format' => 'Formato de hora final inválido.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('reserva.index')
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Erro na validação: ' . $validator->errors()->first());
        }

        $validated = $validator->validated();
        $date = $validated['data_reserva'];
        $arenaId = $validated['arena_id'];

        // 2. 🎯 VALIDAÇÃO DE SEGURANÇA AJUSTADA: CAIXA FECHADO POR ARENA
        $financeiroController = app(FinanceiroController::class);

        if ($financeiroController->isCashClosed($date, $arenaId)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'O agendamento para esta quadra no dia ' . Carbon::parse($date)->format('d/m/Y') . ' está temporariamente indisponível.');
        }

        $startTimeRaw = $validated['hora_inicio'];
        $endTimeRaw = $validated['hora_fim'];

        if ($startTimeRaw === '23:00' && ($endTimeRaw === '0:00' || $endTimeRaw === '00:00')) {
            $endTimeRaw = '23:59';
        }

        $startTimeNormalized = Carbon::parse($startTimeRaw)->format('H:i:s');
        $endTimeNormalized = Carbon::parse($endTimeRaw)->format('H:i:s');

        DB::beginTransaction();
        try {
            $clientUser = $this->findOrCreateClient([
                'name' => $validated['nome_cliente'],
                'email' => $validated['email_cliente'],
                'whatsapp_contact' => $validated['contato_cliente'],
            ]);

            // 🚀 2.1 TRAVA DE SEGURANÇA: BLACKLIST (DESCOBERTA VIA DEBUG)
            // Se o cliente for encontrado e o campo is_blocked for 1, impedimos o avanço.
            if ($clientUser && $clientUser->is_blocked) {
                DB::rollBack();
                return redirect()->back()
                    ->withInput()
                    ->with('error', '🚫 Seu acesso está temporariamente restrito para novos agendamentos devido ao histórico de faltas. Por favor, entre em contato com a arena.');
            }

            // 3. Bloqueio de duplicidade para o mesmo cliente
            $existing = \App\Models\Reserva::where('user_id', $clientUser->id)
                ->where('arena_id', $arenaId)
                ->where('date', $date)
                ->where('start_time', $startTimeNormalized)
                ->whereIn('status', [\App\Models\Reserva::STATUS_PENDENTE, \App\Models\Reserva::STATUS_CONFIRMADA])
                ->first();

            if ($existing) {
                DB::rollBack();
                return redirect()->back()->withInput()->with('error', "Você já tem uma solicitação enviada para este horário nesta quadra.");
            }

            // 4. Trava de Segurança: Só bloqueia se houver alguém CONFIRMADO
            if ($this->checkOverlap($date, $startTimeRaw, $endTimeRaw, $arenaId, true)) {
                DB::rollBack();
                return redirect()->back()->withInput()->with('error', 'Este horário acabou de ser fechado com outro cliente.');
            }

            // 5. Criação da Reserva Pendente
            $reserva = \App\Models\Reserva::create([
                'user_id' => $clientUser->id,
                'arena_id' => $arenaId,
                'date' => $date,
                'day_of_week' => Carbon::parse($date)->dayOfWeek,
                'start_time' => $startTimeNormalized,
                'end_time' => $endTimeNormalized,
                'price' => (float)$validated['price'],
                'final_price' => (float)$validated['price'],
                'signal_value' => (float)($validated['signal_value'] ?? 0),
                'total_paid' => (float)($validated['signal_value'] ?? 0),
                'payment_status' => 'pending',
                'client_name' => $clientUser->name,
                'client_contact' => $clientUser->whatsapp_contact,
                'notes' => $validated['notes'] ?? null,
                'status' => \App\Models\Reserva::STATUS_PENDENTE,
                'is_fixed' => false,
                'is_recurrent' => false,
            ]);

            DB::commit();

            // 6. Preparação da Mensagem de WhatsApp
            $company = \App\Models\CompanyInfo::first();
            $whatsappNumber = $company->whatsapp_suporte ?? '91985320997';
            $arenaNomeFantasia = $company->nome_fantasia ?? 'Elite Soccer';

            $dataFmt = \Carbon\Carbon::parse($reserva->date)->format('d/m/Y');
            $horaFmt = \Carbon\Carbon::parse($reserva->start_time)->format('H:i');
            $nomeQuadra = $reserva->arena->name;

            $messageText = "🚨 *PRÉ-RESERVA SOLICITADA*\n\n" .
                "🏟️ *Estabelecimento:* {$arenaNomeFantasia}\n" .
                "👤 *Cliente:* {$reserva->client_name}\n" .
                "⚽ *Quadra:* {$nomeQuadra}\n" .
                "📅 *Data:* {$dataFmt}\n" .
                "⏰ *Horário:* {$horaFmt}\n" .
                "📝 *Status:* AGUARDANDO PAGAMENTO\n\n" .
                "Olá! Acabei de solicitar esta reserva pelo site. Poderia me enviar a *Chave PIX* e o *Valor do Sinal* para que eu possa realizar o pagamento e confirmar meu horário?";

            $whatsappLink = "https://api.whatsapp.com/send?phone=55{$whatsappNumber}&text=" . urlencode($messageText);

            return redirect()->route('reserva.index')
                ->with('success', 'Solicitação enviada! Clique no botão abaixo para falar com o gestor.')
                ->with('whatsapp_link', $whatsappLink);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("[STORE PUBLIC] Erro: " . $e->getMessage());

            if (str_contains(strtolower($e->getMessage()), 'caixa')) {
                return redirect()->back()->withInput()->with('error', 'Não foi possível concluir o agendamento para esta quadra.');
            }

            return redirect()->back()->withInput()->with('error', 'Erro interno ao processar agendamento.');
        }
    }


    /**
     * Retorna a contagem de reservas pendentes para o Dashboard.
     */
    public function countPending()
    {
        $today = Carbon::today()->startOfDay(); // Define hoje à meia-noite

        $futureOrTodayCount = Reserva::where('status', Reserva::STATUS_PENDENTE)
            ->whereDate('date', '>=', $today) // Apenas reservas futuras ou de hoje
            ->count();

        return response()->json(['count' => $futureOrTodayCount], 200);
    }
}
