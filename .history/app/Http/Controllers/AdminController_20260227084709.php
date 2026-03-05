<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Carbon\CarbonPeriod;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon as BaseCarbon;
use App\Models\FinancialTransaction;


class AdminController extends Controller
{
    /**
     * @var ReservaController
     */
    protected $reservaController;

    // 🛑 CRÍTICO: Injeção de Dependência para acessar os helpers do ReservaController
    public function __construct(ReservaController $reservaController)
    {
        $this->reservaController = $reservaController;
    }

    // ------------------------------------------------------------------------
    // MÓDULO: DASHBOARDS E LISTAGENS
    // ------------------------------------------------------------------------

    /**
     * Exibe o Dashboard administrativo (FullCalendar).
     */
    public function dashboard()
    {
        // 🛑 DELEGA para o método do ReservaController
        return $this->reservaController->dashboard();
    }

    /**
     * Exibe o painel de botões de gerenciamento de reservas.
     */
    public function indexReservasDashboard()
    {
        return view('admin.reservas.index-dashboard');
    }

    /**
     * Exibe a lista de Reservas Pendentes (Multiquadra).
     */
    public function indexReservas(Request $request)
    {
        // 1. Captura o ID da arena vindo do filtro da View
        $arenaId = $request->query('arena_id');

        // 2. Inicia a query buscando apenas as pré-reservas pendentes
        $query = Reserva::where('status', Reserva::STATUS_PENDENTE)
            ->where('is_fixed', false)
            ->with('arena')
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc');

        // 3. Aplica o filtro de Arena
        if ($arenaId) {
            $query->where('arena_id', $arenaId);
        }

        // 4. Pagina os resultados
        $reservas = $query->paginate(20)->appends($request->all());

        // 5. Retorna a view enviando as ARENAS para o filtro funcionar
        return view('admin.reservas.index', [
            'reservas' => $reservas,
            'pageTitle' => 'Pré-Reservas Pendentes',
            'arenas' => \App\Models\Arena::all(), // ✨ ADICIONE ESTA LINHA AQUI
        ]);
    }

    /**
     * Exibe a lista de Reservas Confirmadas.
     */
    public function confirmed_index(Request $request)
    {
        $search = $request->input('search');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $isOnlyMine = $request->input('only_mine') === 'true';
        // 🏟️ NOVO: Captura a arena selecionada no filtro da view
        $arenaId = $request->input('arena_id');

        $reservas = Reserva::whereIn('status', [
            Reserva::STATUS_CONFIRMADA,
            Reserva::STATUS_CONCLUIDA,
            Reserva::STATUS_PENDENTE,
            'completed',
            'concluida'
        ])
            ->where('is_fixed', false)
            ->with('arena') // 🏟️ ESSENCIAL: Carrega os dados da quadra (nome, cor, etc.)

            // 🏟️ FILTRO MULTIQUADRA: Só filtra se uma arena for selecionada
            ->when($arenaId, function ($query, $arenaId) {
                return $query->where('arena_id', $arenaId);
            })

            // FILTRO DE BUSCA (Nome ou Contato)
            ->when($search, function ($query, $search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('client_name', 'like', '%' . $search . '%')
                        ->orWhere('client_contact', 'like', '%' . $search . '%');
                });
            })

            // FILTROS DE DATA
            ->when($startDate, function ($query, $startDate) {
                return $query->whereDate('date', '>=', $startDate);
            })
            ->when($endDate, function ($query, $endDate) {
                return $query->whereDate('date', '<=', $endDate);
            })

            // FILTRO "MINHAS RESERVAS"
            ->when($isOnlyMine, function ($query) {
                return $query->where('manager_id', Auth::id());
            })

            // ORDENAÇÃO
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate(20)
            ->appends($request->except('page'));

        return view('admin.reservas.confirmed_index', [
            'reservas' => $reservas,
            'pageTitle' => 'Gerenciamento de Reservas (Geral)',
            'search' => $search,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'isOnlyMine' => $isOnlyMine,
            'arenaId' => $arenaId, // 🏟️ Envia o ID de volta para manter o select selecionado
            'arenas' => \App\Models\Arena::all(), // 🏟️ Envia a lista para o select de filtro
        ]);
    }

    /**
     * ✅ AJUSTADO: Exibe a lista de TODAS as reservas (clientes e slots fixos).
     * Garante o retorno dos filtros para manter os campos preenchidos na View.
     */
    public function indexTodas(Request $request)
    {
        $search = $request->input('search');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $filterStatus = $request->input('filter_status');
        $isOnlyMine = $request->input('only_mine') === 'true';
        $arenaId = $request->input('arena_id');

        // 1. Inicia a query com Eager Loading (Arena e Usuário se houver)
        $query = Reserva::with(['arena', 'user', 'manager']);

        // 2. Filtro de Arena (Multiquadra)
        if ($arenaId) {
            $query->where('arena_id', $arenaId);
        }

        // 3. Filtro de Status
        if ($filterStatus) {
            $query->where('status', $filterStatus);
        }

        // 4. Filtros de Data (Período)
        $query->when($startDate, function ($q, $startDate) {
            return $q->whereDate('date', '>=', $startDate);
        })
            ->when($endDate, function ($q, $endDate) {
                return $q->whereDate('date', '<=', $endDate);
            });

        // 5. Filtro de Busca (Nome ou Contato)
        $query->when($search, function ($q, $search) {
            return $q->where(function ($sub) use ($search) {
                $sub->where('client_name', 'like', '%' . $search . '%')
                    ->orWhere('client_contact', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('name', 'like', '%' . $search . '%');
                    });
            });
        });

        // 6. Filtro "Somente Minhas"
        if ($isOnlyMine) {
            $query->where('manager_id', Auth::id());
        }

        // 7. Ordenação e Paginação
        $reservas = $query->orderBy('date', 'asc') // Mais recentes primeiro costuma ser melhor para "Todas"
            ->orderBy('start_time', 'asc')
            ->paginate(20)
            ->appends($request->all());

        // 8. Retorna a view com TODOS os dados para manter o estado dos filtros
        return view('admin.reservas.todas', [
            'reservas'     => $reservas,
            'pageTitle'    => 'Todas as Reservas (Inventário e Clientes)',
            'search'       => $search,
            'startDate'    => $startDate,   // ✅ Agora volta para a View
            'endDate'      => $endDate,     // ✅ Agora volta para a View
            'filterStatus' => $filterStatus,
            'isOnlyMine'   => $isOnlyMine,
            'arenaId'      => $arenaId,
            'arenas'       => \App\Models\Arena::all(),
        ]);
    }

    /**
     * Exibe o formulário para criação manual de reserva.
     */
    public function createReserva()
    {
        $users = User::where('role', 'cliente')->get();
        return view('admin.reservas.create', compact('users'));
    }

    /**
     * Exibe os detalhes de uma reserva.
     */
    public function showReserva(Reserva $reserva)
    {
        return view('admin.reservas.show', compact('reserva'));
    }

    /**
     * ✅ CORRIGIDO: Cria uma nova reserva manual (Admin) - DELEGADO.
     * Delega a lógica de criação complexa (consumir slot, criar cliente, transação) para ReservaController.
     */
    public function storeReserva(Request $request)
    {
        $validated = $request->validate([
            'arena_id' => 'required|exists:arenas,id', // 🏟️ ADICIONADO: Obrigatório escolher a quadra
            'user_id' => 'nullable|exists:users,id',
            'date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'price' => 'required|numeric|min:0',
            'signal_value' => 'nullable|numeric|min:0',
            'client_name' => 'required|string|max:255',
            'client_contact' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'payment_method' => 'required|string',
            'is_recurrent' => 'nullable|boolean',
        ]);

        $clientContact = $validated['client_contact'];

        DB::beginTransaction();
        try {
            // 1. Encontra/Cria o cliente
            $clientUser = $this->reservaController->findOrCreateClient([
                'name' => $validated['client_name'],
                'whatsapp_contact' => $clientContact,
                'email' => null,
            ]);

            // 2. Normalização dos horários
            $startTimeNormalized = Carbon::createFromFormat('H:i', $validated['start_time'])->format('H:i:s');
            $endTimeNormalized = Carbon::createFromFormat('H:i', $validated['end_time'])->format('H:i:s');

            // 3. Busca slot fixo filtrando por ARENA_ID (Crucial para integridade) 🏟️
            $fixedSlot = Reserva::where('is_fixed', true)
                ->where('arena_id', $validated['arena_id']) // 🎯 FILTRO ADICIONADO
                ->where('date', $validated['date'])
                ->where('start_time', $startTimeNormalized)
                ->where('end_time', $endTimeNormalized)
                ->where('status', Reserva::STATUS_FREE)
                ->first();

            $fixedSlotId = $fixedSlot ? $fixedSlot->id : null;

            // 4. DELEGA A CRIAÇÃO FINAL ao ReservaController
            // O $validated já contém o arena_id agora, então o helper salvará corretamente.
            $newReserva = $this->reservaController->createConfirmedReserva($validated, $clientUser, $fixedSlotId);

            DB::commit();
            return redirect()->route('admin.reservas.confirmadas')->with('success', 'Reserva criada com sucesso na quadra selecionada!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao criar reserva manual.", ['exception' => $e, 'data' => $validated]);
            return redirect()->back()->withInput()->with('error', 'Erro interno ao criar reserva: ' . $e->getMessage());
        }
    }


    // ------------------------------------------------------------------------
    // MÓDULO: AÇÕES DE STATUS E CANCELAMENTO (DELEGADOS)
    // ------------------------------------------------------------------------

    /**
     * Confirma uma reserva pendente e registra o sinal financeiro. (DELEGADO)
     */
    public function confirmarReserva(Request $request, Reserva $reserva)
    {
        // 🛑 DELEGAÇÃO COMPLETA
        return $this->reservaController->confirmar($request, $reserva);
    }

    /**
     * Rejeita uma reserva pendente. (DELEGADO)
     */
    public function rejeitarReserva(Request $request, Reserva $reserva)
    {
        // 🛑 DELEGAÇÃO COMPLETA
        return $this->reservaController->rejeitar($request, $reserva);
    }

    /**
     * ✅ CORRIGIDO: Registra a falta do cliente (No-Show) - DELEGADO.
     * Delega a manipulação de status e transações financeiras.


     *public function registerNoShow(Request $request, Reserva $reserva)
     *{
     *if ($reserva->status !== Reserva::STATUS_CONFIRMADA) {
     *    return response()->json(['success' => false, 'message' => 'A reserva deve estar confirmada para ser marcada como falta.'], 400);
     *}

     *$validated = $request->validate([
     *    'no_show_reason' => 'required|string|min:5|max:255',
     *    'should_refund' => 'required|boolean',
     *    'paid_amount' => 'required|numeric|min:0',
     *]);

     *DB::beginTransaction();
     *try {
     *    // 🛑 DELEGA A LÓGICA CENTRALIZADA
     *    $result = $this->reservaController->finalizeStatus(
     *        $reserva,
     *        Reserva::STATUS_NO_SHOW,
     *        '[Gestor] ' . $validated['no_show_reason'],
     *        $validated['should_refund'],
     *        (float) $validated['paid_amount']
     *    );

     *    DB::commit();
     *    $message = "Reserva marcada como Falta." . $result['message_finance'];
     *    return response()->json(['success' => true, 'message' => $message], 200);
     *} catch (ValidationException $e) {
     *    // Garante que erros de validação sejam tratados corretamente
     *    DB::rollBack();
     *    return response()->json(['success' => false, 'errors' => $e->errors()], 422);
     *} catch (\Exception $e) {
     *    DB::rollBack();
     *    Log::error("Erro ao registrar No-Show para reserva ID: {$reserva->id}.", ['exception' => $e]);
     *    return response()->json([
     *        'success' => false,
     *        'message' => 'Erro interno ao registrar a falta. Detalhe: ' . $e->getMessage()
     *    ], 500);
     *}
     *}
     */



    /**
     * ✅ REVISADO: Reativa uma reserva garantindo compatibilidade de data e timezone.
     */
    public function reativar(Request $request, $id) // Alteramos de Reserva $reserva para apenas $id
    {
        // 1. Buscamos os dados BRUTOS do banco para evitar o erro de conversão automática do Laravel
        $dadosBrutos = DB::table('reservas')->where('id', $id)->first();

        if (!$dadosBrutos) {
            return response()->json(['success' => false, 'message' => 'Reserva não encontrada.'], 404);
        }

        // 2. Validação de Status
        $statusPermitidos = ['cancelled', 'rejected', 'no_show'];
        if (!in_array($dadosBrutos->status, $statusPermitidos)) {
            return response()->json(['success' => false, 'message' => 'Esta reserva não pode ser reativada.'], 400);
        }

        try {
            // 🚀 A LIMPEZA REAL: Pegamos apenas os primeiros 10 caracteres da coluna 'date'
            $dataLimpa = substr((string)$dadosBrutos->date, 0, 10);
            $horaFim = $dadosBrutos->end_time;

            // Montamos a data de verificação
            $dataFimReserva = \Carbon\Carbon::parse($dataLimpa . ' ' . $horaFim);

            if ($dataFimReserva->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => '🛑 Horário encerrado (Fim: ' . $dataFimReserva->format('H:i') . ').'
                ], 400);
            }

            // 3. Checa sobreposição (usando o controller auxiliar)
            if ($this->reservaController->checkOverlap($dataLimpa, $dadosBrutos->start_time, $horaFim, true, $id, $dadosBrutos->arena_id)) {
                return response()->json(['success' => false, 'message' => 'O horário já está ocupado por outra reserva.'], 400);
            }

            DB::beginTransaction();

            // Agora carregamos o model apenas para salvar, desativando o timestamp se necessário
            $reserva = Reserva::find($id);
            $reserva->status = Reserva::STATUS_CONFIRMADA;
            $reserva->manager_id = Auth::id();
            $reserva->cancellation_reason = null;
            $reserva->save();

            $this->reservaController->consumeFixedSlot($reserva);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Reserva reativada com sucesso!'], 200);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Atualiza o preço de uma reserva específica ou de toda a série (PATCH).
     * Ajustado para sincronizar price e final_price para o Caixa.
     */
    public function updatePrice(Request $request, Reserva $reserva)
    {
        $validated = $request->validate([
            'new_price'     => 'required|numeric|min:0',
            'justification' => 'required|string|min:5',
            'scope'         => 'nullable|string|in:single,series',
        ], [
            'new_price.required' => 'O novo preço é obrigatório.',
            'justification.min'  => 'A justificativa deve ter pelo menos 5 caracteres.',
        ]);

        $newPrice = (float) $validated['new_price'];
        $totalPago = (float) ($reserva->total_paid ?? 0);

        // 1. Impedir que o novo preço seja menor que o valor já pago (Evita saldo negativo)
        if ($newPrice < $totalPago) {
            return response()->json([
                'success' => false,
                'message' => "🛑 Operação Negada: O cliente já pagou R$ " . number_format($totalPago, 2, ',', '.') . ". O novo preço total não pode ser menor que o valor já recebido.",
            ], 403);
        }

        // 2. Se estiver 100% PAGO, bloqueamos para não quebrar o fechamento do caixa já realizado
        if ($reserva->payment_status === 'paid' && $newPrice != $reserva->final_price) {
            return response()->json([
                'success' => false,
                'message' => "🛑 Esta reserva já está totalmente paga. Para alterar o valor, estorne o pagamento primeiro.",
            ], 403);
        }

        try {
            $scope = $request->input('scope', 'single');
            $adminName = auth()->user()->name;

            if ($scope === 'series' && $reserva->recurrent_series_id) {
                // 🔄 ATUALIZAÇÃO EM SÉRIE
                // Atualizamos price e final_price de todas as pendentes/parciais futuras
                $affectedCount = \App\Models\Reserva::where('recurrent_series_id', $reserva->recurrent_series_id)
                    ->where('date', '>=', $reserva->date)
                    ->where('payment_status', '!=', 'paid')
                    ->update([
                        'price' => $newPrice,
                        'final_price' => $newPrice
                    ]);

                \Log::info("Preço em SÉRIE (ID: {$reserva->recurrent_series_id}) alterado para R$ {$newPrice} por {$adminName}. Motivo: {$validated['justification']}");

                $msg = "Preço da série atualizado ({$affectedCount} reservas)! O Caixa refletirá o novo saldo.";
            } else {
                // 📍 ATUALIZAÇÃO PONTUAL
                // Sincroniza price e final_price para que o Caixa leia o valor correto
                $reserva->price = $newPrice;
                $reserva->final_price = $newPrice;
                $reserva->save();

                \Log::info("Preço da Reserva #{$reserva->id} alterado para R$ {$newPrice} por {$adminName}. Motivo: {$validated['justification']}");

                $msg = "Preço atualizado com sucesso! O saldo devedor foi recalculado.";
            }

            return response()->json(['success' => true, 'message' => $msg]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erro ao processar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Cria uma nova reserva recorrente para um cliente.
     */
    public function makeRecurrent(Request $request)
    {
        // Limite máximo de 6 meses (26 semanas) a partir da data de início da série
        $maxDate = Carbon::today()->addMonths(6)->toDateString();

        // 1. Validação CRÍTICA: Enforça o limite de 6 meses na data final.
        $validated = $request->validate([
            'reserva_id' => 'required|exists:reservas,id',
            'start_date' => 'required|date|after_or_equal:today',
            // CRÍTICO: Limita a data final para 6 meses no futuro
            'end_date' => 'required|date|before_or_equal:' . $maxDate,
            'fixed_price' => 'required|numeric|min:0',
        ], [
            // Mensagem de erro customizada para o limite
            'end_date.before_or_equal' => "A série recorrente não pode exceder 6 meses (data máxima: {$maxDate}). Por favor, escolha uma data final anterior.",
        ]);

        try {
            // 2. Delega a criação da série de reservas para o ReservaController (Assumindo que este método existe lá)
            $result = $this->reservaController->processRecurrentCreation(
                $validated['reserva_id'],
                $validated['start_date'],
                $validated['end_date'],
                $validated['fixed_price']
            );

            // 3. Retorno de sucesso (usando a mensagem do helper)
            return response()->json([
                'success' => true,
                'message' => $result['message'] ?? 'Série recorrente criada com sucesso (limitada a 6 meses).',
            ]);
        } catch (ValidationException $e) {
            // 4. Exceções de Validação são relançadas para serem tratadas pelo handler do Laravel (ex: erro 422)
            throw $e;
        } catch (\Exception $e) {
            Log::error("Erro ao criar série recorrente (AdminController::makeRecurrent): " . $e->getMessage(), ['request' => $request->all()]);

            // 5. Tratamento de erro geral
            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao criar série recorrente. Verifique as datas e o log: ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * ✅ CORRIGIDO: Cancela uma reserva PONTUAL confirmada - DELEGADO.
     * Delega a manipulação de status e transações financeiras.
     */
    public function cancelarReserva(Request $request, Reserva $reserva)
    {
        if ($reserva->is_recurrent) {
            return response()->json(['success' => false, 'message' => 'Use as rotas de cancelamento de série para reservas recorrentes.'], 400);
        }

        // 🚩 AJUSTE: Permite cancelar tanto as Confirmadas quanto as já Pagas (Completed)
        $statusPermitidos = [
            Reserva::STATUS_CONFIRMADA,
            Reserva::STATUS_CONCLUIDA, // Caso sua model tenha essa constante
            'completed',
            'concluida'
        ];

        if (!in_array($reserva->status, $statusPermitidos)) {
            return response()->json(['success' => false, 'message' => 'A reserva não está em um status que permite cancelamento.'], 400);
        }

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|min:5|max:255',
            'should_refund' => 'required|boolean',
            'paid_amount_ref' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // A lógica delegada ao reservaController já sabe lidar com o estorno
            // se o should_refund for true e o paid_amount_ref for > 0
            $result = $this->reservaController->finalizeStatus(
                $reserva,
                Reserva::STATUS_CANCELADA,
                '[Gestor] ' . $validated['cancellation_reason'],
                $validated['should_refund'],
                (float) $validated['paid_amount_ref']
            );

            DB::commit();
            $message = "Reserva cancelada com sucesso! O horário foi liberado." . $result['message_finance'];
            return response()->json(['success' => true, 'message' => $message], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao cancelar reserva PONTUAL ID: {$reserva->id}.", ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao cancelar a reserva: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * ✅ CORRIGIDO: Cancela UMA reserva de uma série recorrente.
     * Ajustado para zerar o saldo devedor no caixa ao cancelar.
     */
    public function cancelarReservaRecorrente(Request $request, Reserva $reserva)
    {
        if (!$reserva->is_recurrent) {
            return response()->json(['success' => false, 'message' => 'A reserva não é recorrente.'], 400);
        }

        $statusPermitidos = [Reserva::STATUS_CONFIRMADA, Reserva::STATUS_CONCLUIDA, 'completed', 'concluida'];

        if (!in_array($reserva->status, $statusPermitidos)) {
            return response()->json(['success' => false, 'message' => 'A reserva não está em um status cancelável.'], 400);
        }

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|min:5|max:255',
            'should_refund' => 'required|boolean',
            'paid_amount_ref' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // 💰 LÓGICA DE CAIXA: Zerar saldo devedor
            // Se NÃO houver estorno, o valor final da reserva passa a ser o que já foi pago.
            // Se HOUVER estorno (should_refund = true), o finalizeStatus cuidará da saída,
            // mas o final_price deve ser zerado para não haver cobrança futura.

            $pagoAteAgora = (float)($reserva->total_paid ?? 0);

            if ($validated['should_refund']) {
                $reserva->final_price = 0; // Se devolveu o dinheiro, o valor da venda é zero
            } else {
                $reserva->final_price = $pagoAteAgora; // Se reteve o sinal, o valor da venda é o sinal
            }

            $reserva->cancellation_reason = '[Gestor - Pontual Recorrência] ' . $validated['cancellation_reason'];
            $reserva->save();

            // Delega a manipulação financeira (Estorno no caixa, se aplicável)
            $result = $this->reservaController->finalizeStatus(
                $reserva,
                Reserva::STATUS_CANCELADA,
                $reserva->cancellation_reason,
                $validated['should_refund'],
                (float) $validated['paid_amount_ref']
            );

            DB::commit();

            $message = "Reserva cancelada com sucesso! O saldo foi zerado no caixa." . ($result['message_finance'] ?? '');
            return response()->json(['success' => true, 'message' => $message], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao cancelar reserva RECORRENTE PONTUAL ID: {$reserva->id}.", ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao cancelar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ CORRIGIDO: Cancela TODAS as reservas futuras de uma série recorrente com trava de duplicidade.
     */
    public function cancelarSerieRecorrente(Request $request, Reserva $reserva)
    {
        if (!$reserva->is_recurrent) {
            return response()->json(['success' => false, 'message' => 'A reserva não pertence a uma série recorrente.'], 400);
        }

        // 🛡️ TRAVA DE SEGURANÇA: Se a reserva já foi cancelada/rejeitada individualmente,
        // não permitimos que ela dispare um novo estorno financeiro pela série.
        if (in_array($reserva->status, [Reserva::STATUS_CANCELADA, Reserva::STATUS_REJEITADA, 'cancelled', 'rejected'])) {
            return response()->json([
                'success' => false,
                'message' => 'Esta reserva individual já está cancelada. Para cancelar o restante da série, selecione uma reserva que ainda esteja ATIVA.'
            ], 400);
        }

        $statusPermitidos = [Reserva::STATUS_CONFIRMADA, Reserva::STATUS_CONCLUIDA, 'completed', 'concluida'];

        if (!in_array($reserva->status, $statusPermitidos)) {
            return response()->json(['success' => false, 'message' => 'O status atual não permite o cancelamento da série.'], 400);
        }

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|min:5|max:255',
            'should_refund' => 'required|boolean',
            'paid_amount_ref' => 'required|numeric|min:0',
        ]);

        $masterId = $reserva->recurrent_series_id ?? $reserva->id;

        DB::beginTransaction();
        try {
            // 💰 AJUSTE FINANCEIRO DA RESERVA ATUAL
            $pagoHoje = (float)($reserva->total_paid ?? 0);

            // Só ajustamos o final_price se houver saldo pago para evitar bagunça no caixa
            if ($validated['should_refund']) {
                $reserva->final_price = 0;
            } else {
                $reserva->final_price = $pagoHoje;
            }

            $reserva->cancellation_reason = '[Gestor - Cancelamento Série] ' . $validated['cancellation_reason'];
            $reserva->save();

            // 🛑 DELEGAÇÃO: Chama o método que limpa as reservas FUTURAS
            $result = $this->reservaController->cancelSeries(
                $masterId,
                $reserva->cancellation_reason,
                $validated['should_refund'],
                (float) $validated['paid_amount_ref']
            );

            DB::commit();

            $message = "Série cancelada ({$result['cancelled_count']} slots liberados). " .
                "Saldos ajustados para evitar pendências no caixa. " .
                ($result['message_finance'] ?? '');

            return response()->json(['success' => true, 'message' => $message], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao cancelar série recorrente ID: {$masterId}.", ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()], 500);
        }
    }


    /**
     * Exclui permanentemente uma reserva (Admin).
     */
    public function destroyReserva(Reserva $reserva)
    {
        DB::beginTransaction();
        try {
            // Se a reserva era ativa (confirmada/pendente) antes da exclusão
            if ($reserva->status === Reserva::STATUS_CONFIRMADA || $reserva->status === Reserva::STATUS_PENDENTE) {
                // Se for uma reserva de cliente, recria o slot
                if (!$reserva->is_fixed) {
                    $this->reservaController->recreateFixedSlot($reserva);
                }
            }

            // CRÍTICO: Aqui mantemos o delete, pois o propósito deste método é a exclusão PERMANENTE.
            $reserva->delete();

            DB::commit();
            Log::warning("Reserva ID: {$reserva->id} excluída permanentemente pelo gestor ID: " . auth()->user()->id);
            return redirect()->route('admin.reservas.confirmadas')->with('success', 'Reserva excluída permanentemente.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao excluir reserva ID: {$reserva->id}.", ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Erro ao excluir reserva: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Exibe a lista de Reservas Rejeitadas com suporte a filtros e multiquadras.
     */
    public function indexReservasRejeitadas(Request $request)
    {
        $search = $request->input('search');
        $arenaId = $request->input('arena_id');

        // 🎯 O valor padrão agora vem da constante da Model
        $statusFilter = $request->input('status_filter', Reserva::STATUS_REJEITADA);

        $query = Reserva::where('is_fixed', false)
            ->with(['arena', 'manager']);

        // 🔄 Lógica de Intercalação usando as Constantes da Model
        if ($statusFilter === 'all') {
            $query->whereIn('status', [Reserva::STATUS_REJEITADA, Reserva::STATUS_CANCELADA]);
        } else {
            // Se o usuário selecionou 'canceled' no HTML, o Laravel converterá
            // mas para garantir, vamos aceitar o que vier do request de forma dinâmica
            $query->where('status', $statusFilter);
        }

        if ($arenaId) {
            $query->where('arena_id', $arenaId);
        }

        if ($search) {
            $query->where(function ($sub) use ($search) {
                $sub->where('client_name', 'like', '%' . $search . '%')
                    ->orWhere('client_contact', 'like', '%' . $search . '%');
            });
        }

        $reservas = $query->orderBy('updated_at', 'desc')
            ->paginate(15)
            ->appends($request->all());

        return view('admin.reservas.rejeitadas', [
            'reservas' => $reservas,
            'pageTitle' => 'Histórico de Insucessos',
            'arenas' => \App\Models\Arena::all(),
            'statusFilter' => $statusFilter
        ]);
    }

    // ------------------------------------------------------------------------
    // ✅ MÓDULO: RELATÓRIO DE PAGAMENTOS/CAIXA (Backend da sua view)
    // ------------------------------------------------------------------------

    /**
     * Calcula o saldo total de todas as transações financeiras.
     * @return float
     */
    private function calculateTotalBalance()
    {
        // Esta consulta deve somar TODOS os valores na coluna 'amount'.
        $total = FinancialTransaction::sum('amount');
        Log::info("DEBUG FINANCEIRO: Saldo total do caixa calculado: R$ " . number_format($total, 2, ',', '.'));
        return (float) $total;
    }

    /**
     * Exibe a lista de transações financeiras e o saldo.
     */
    public function indexFinancialDashboard(Request $request)
    {
        // 1. Definição da data e da ARENA
        $selectedDate = $request->input('date', Carbon::today()->toDateString());
        $date = Carbon::parse($selectedDate)->toDateString();
        $arenaId = $request->input('arena_id');
        $search = $request->input('search');
        $reservaId = $request->input('reserva_id');

        // 2. Consulta de Reservas do Dia (Tabela principal)
        $reservasQuery = Reserva::where('is_fixed', false)
            ->with('arena')
            ->whereDate('date', $date)
            ->whereIn('status', [Reserva::STATUS_CONFIRMADA, Reserva::STATUS_PENDENTE, 'completed', 'no_show', 'canceled'])
            ->when($arenaId, function ($query, $arenaId) {
                return $query->where('arena_id', $arenaId);
            })
            ->when($reservaId, function ($query, $reservaId) {
                return $query->where('id', $reservaId);
            })
            ->when($search, function ($query, $search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('client_name', 'like', '%' . $search . '%')
                        ->orWhere('client_contact', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('start_time')
            ->get();

        // 3. 💰 CÁLCULOS FINANCEIROS BLINDADOS 💰

        // 3.1 Total Real em Caixa (Tudo que entrou de fato hoje, incluindo multas)
        $totalReceived = FinancialTransaction::whereDate('paid_at', $date)
            ->when($arenaId, function ($query, $arenaId) {
                return $query->where('arena_id', $arenaId);
            })
            ->sum('amount');

        // 3.2 Receita Total Exibida (Soma de jogos confirmados + multas de quem faltou)
        // Isso evita que o gráfico caia quando você marca No-Show retendo o valor.
        $receitaMultas = FinancialTransaction::whereDate('paid_at', $date)
            ->where('type', 'no_show_penalty')
            ->when($arenaId, function ($query, $arenaId) {
                return $query->where('arena_id', $arenaId);
            })
            ->sum('amount');

        // 3.3 Transações para a tabela de auditoria
        $financialTransactions = FinancialTransaction::whereDate('paid_at', $date)
            ->when($arenaId, function ($query, $arenaId) {
                return $query->where('arena_id', $arenaId);
            })
            ->orderBy('paid_at', 'desc')
            ->get();

        // 3.4 Pendências (Somente de quem ainda vai jogar)
        $activeReservas = Reserva::where('is_fixed', false)
            ->whereDate('date', $date)
            ->whereIn('status', [Reserva::STATUS_CONFIRMADA, Reserva::STATUS_PENDENTE])
            ->when($arenaId, function ($query, $arenaId) {
                return $query->where('arena_id', $arenaId);
            })
            ->get();

        $totalExpected = $activeReservas->sum('price');
        $totalPaidBySignals = $activeReservas->sum('total_paid');
        $totalPending = $totalExpected - $totalPaidBySignals;

        // 3.5 Contador de Faltas (Busca no financeiro para não perder o dado se a reserva for deletada)
        $noShowCount = FinancialTransaction::whereDate('paid_at', $date)
            ->where('type', 'no_show_penalty')
            ->where('amount', '>', 0) // Garante que foi uma retenção
            ->when($arenaId, function ($query, $arenaId) {
                return $query->where('arena_id', $arenaId);
            })
            ->count();

        return view('admin.financial.index', [
            'reservas' => $reservasQuery,
            'financialTransactions' => $financialTransactions,
            'selectedDate' => $selectedDate,
            'arenaId' => $arenaId,
            'arenas' => \App\Models\Arena::all(),
            'highlightReservaId' => $reservaId,
            'totalReceived' => $totalReceived, // Saldo Real
            'totalPending' => max(0, $totalPending),
            'totalExpected' => $totalExpected + $receitaMultas, // Receita total (Jogos + Multas)
            'noShowCount' => $noShowCount,
            'pageTitle' => 'Gerenciamento de Caixa & Pagamentos',
            'search' => $search,
            'totalGlobalBalance' => $this->calculateTotalBalance(),
        ]);
    }

    /**
     * 🛠️ Move para MANUTENÇÃO com Fila de Crédito Inteligente
     * Protege valores já pagos e distribui o saldo por múltiplas datas se necessário.
     */
    public function moverManutencao(Request $request, $id)
    {
        return DB::transaction(function () use ($request, $id) {
            try {
                $reserva = Reserva::findOrFail($id);
                $action = $request->input('finance_action');
                $motivo = $request->input('reason', 'Manutenção');

                $valorOriginal = (float) $reserva->total_paid;
                $nomeOriginal = $reserva->client_name;
                $contatoOriginal = $reserva->client_contact;
                $userIdOriginal = $reserva->user_id;

                $dataReserva = date('d/m', strtotime($reserva->date));
                $horaReserva = date('H:i', strtotime($reserva->start_time));

                // 1. LÓGICA DE MOVIMENTAÇÃO DE CRÉDITO COM PROTEÇÃO DE TRANSBORDO
                $transferenciaSucesso = false;
                $datasDestino = [];

                if ($valorOriginal > 0 && ($action === 'transfer' || $action === 'credit')) {
                    $idDaSerie = $reserva->recurrent_series_id ?? $reserva->id;

                    // Busca todas as futuras reservas da série que não estão canceladas
                    $proximasReservas = Reserva::where(function ($q) use ($idDaSerie) {
                        $q->where('recurrent_series_id', $idDaSerie)->orWhere('id', $idDaSerie);
                    })
                        ->where('id', '!=', $reserva->id)
                        ->where('date', '>', $reserva->date)
                        ->whereNotIn('status', ['cancelled', 'rejected'])
                        ->orderBy('date', 'asc')
                        ->get();

                    $montanteParaDistribuir = $valorOriginal;

                    foreach ($proximasReservas as $proxima) {
                        if ($montanteParaDistribuir <= 0) break;

                        $precoTotal = (float)$proxima->price;
                        $jaPago = (float)$proxima->total_paid;
                        $saldoDevedor = $precoTotal - $jaPago;

                        // Se a reserva destino ainda tem saldo a pagar
                        if ($saldoDevedor > 0) {
                            $valorInjetado = min($montanteParaDistribuir, $saldoDevedor);
                            $novoTotalPago = $jaPago + $valorInjetado;

                            DB::table('reservas')->where('id', $proxima->id)->update([
                                'total_paid'     => $novoTotalPago,
                                'signal_value'   => $novoTotalPago,
                                'payment_status' => ($novoTotalPago >= $precoTotal) ? 'paid' : 'partial',
                                'user_id'        => $userIdOriginal,
                                'client_name'    => $nomeOriginal,
                                'status'         => 'confirmed'
                            ]);

                            // Vincula o rastro financeiro à reserva que recebeu o crédito
                            DB::table('financial_transactions')
                                ->where('reserva_id', $reserva->id)
                                ->update(['reserva_id' => $proxima->id]);

                            $montanteParaDistribuir -= $valorInjetado;
                            $transferenciaSucesso = true;
                            $datasDestino[] = date('d/m', strtotime($proxima->date));
                        }
                    }
                }

                // 2. BACKUP (Marcação para reativação inteligente)
                $reservaDestinoData = !empty($datasDestino) ? implode(', ', array_unique($datasDestino)) : null;

                $backupData = [
                    'name' => $nomeOriginal,
                    'contact' => $contatoOriginal,
                    'status' => $reserva->status,
                    'user_id' => $userIdOriginal,
                    'total_paid_orig' => $valorOriginal,
                    'finance_action' => $transferenciaSucesso ? 'credit' : 'refund',
                    'dest_date' => $reservaDestinoData
                ];
                $backupString = "###BACKUP###" . json_encode($backupData) . "###END###";

                // 3. 🎯 SE NÃO TRANSFERIU (OU OPÇÃO ESTORNO), REGISTRA O ESTORNO
                // AJUSTE: Passamos o arena_id explicitamente para validar o caixa correto
                if ($valorOriginal > 0 && !$transferenciaSucesso) {
                    FinancialTransaction::create([
                        'reserva_id'     => $reserva->id,
                        'arena_id'       => $reserva->arena_id, // Identificação crucial da quadra
                        'amount'         => -$valorOriginal,
                        'type'           => 'refund',
                        'payment_method' => 'outro',
                        'description'    => "ESTORNO AUTOMÁTICO (Manutenção): " . $motivo,
                        'paid_at'        => now(),
                    ]);
                }

                // 4. ATUALIZA A RESERVA ATUAL PARA MANUTENÇÃO
                DB::table('reservas')->where('id', $id)->update([
                    'status' => 'maintenance',
                    'client_name' => "🛠️ MANUTENÇÃO ({$nomeOriginal})",
                    'total_paid' => 0,
                    'signal_value' => 0,
                    'is_fixed' => 1,
                    'notes' => $backupString . "\n" . ($reserva->notes ?? '')
                ]);

                // --- 🚀 MENSAGEM WHATSAPP ---
                $msg = "Olá {$nomeOriginal}! 👋\n\n";
                $msg .= "Informamos que o seu horário do dia {$dataReserva} às {$horaReserva} precisou ser interrompido para MANUTENÇÃO DE EMERGÊNCIA na quadra ({$motivo}).";

                if ($valorOriginal > 0) {
                    $valorFormatado = number_format($valorOriginal, 2, ',', '.');

                    if ($transferenciaSucesso) {
                        $msg .= "\n\n⭐ *SOBRE O SEU PAGAMENTO:* Como seu horário é recorrente, o valor de R$ {$valorFormatado} foi TRANSFERIDO para cobrir saldo(s) no(s) jogo(s) de: {$reservaDestinoData}.";
                        $msg .= "\n\nAssim que a manutenção for concluída, avisaremos você!";
                    } else {
                        $msg .= "\n\n💰 *SOBRE O SEU PAGAMENTO:* Como o horário foi cancelado, já retiramos o valor de R$ {$valorFormatado} do nosso caixa para estorno.";
                        $msg .= "\n\nPor favor, envie sua *CHAVE PIX* agora para realizarmos a devolução imediata do seu dinheiro.";
                    }
                }

                $waLink = "https://wa.me/55" . preg_replace('/\D/', '', $contatoOriginal) . "?text=" . urlencode($msg);

                return response()->json([
                    'success' => true,
                    'message' => $transferenciaSucesso ? 'Crédito transferido!' : 'Manutenção aplicada!',
                    'whatsapp_link' => $waLink
                ]);
            } catch (\Exception $e) {
                \Log::error("Erro moverManutencao: " . $e->getMessage());
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        });
    }


    /**
     * 🔄 Reativação Inteligente de Horário em Manutenção via Backup
     * Ajustado para resetar o status financeiro após estorno/transferência.
     */
    public function reativarManutencao(\App\Http\Requests\UpdateReservaStatusRequest $request, $id)
    {
        return DB::transaction(function () use ($request, $id) {
            try {
                $reserva = Reserva::findOrFail($id);
                $decisao = $request->input('action');

                // --- CASO 1: LIBERAR O SLOT (VOLTAR A SER VERDE/LIVRE) ---
                if ($decisao === 'release_slot' || empty($decisao)) {
                    $reserva->update([
                        'status'         => 'free',
                        'is_fixed'       => true,
                        'client_name'    => 'Slot Livre',
                        'client_contact' => 'N/A',
                        'user_id'        => null,
                        'total_paid'     => 0,
                        'signal_value'   => 0,
                        'payment_status' => 'pending',
                        'notes'          => null,
                    ]);

                    return redirect()->back()->with('success', '✅ Agenda liberada com sucesso! O horário agora está vago.');
                }

                // --- CASO 2: RESTAURAR O CLIENTE ORIGINAL USANDO O BACKUP ---
                if ($decisao === 'restore_client') {
                    if (preg_match('/###BACKUP###(.*?)###END###/s', $reserva->notes, $matches)) {
                        $dados = json_decode($matches[1], true);

                        $valorOriginal = (float) ($dados['total_paid_orig'] ?? 0);
                        $acaoRealizada = $dados['finance_action'] ?? 'refund';
                        $nomeCliente   = $dados['name'] ?? 'Cliente';
                        $dataDestino   = $dados['dest_date'] ?? null;

                        // 🧠 CORREÇÃO FINANCEIRA:
                        // Se estamos reativando, significa que o dinheiro que existia ou foi estornado
                        // para o bolso do cliente ou foi para outra data. Portanto, esta reserva
                        // reativada começa com SALDO ZERO e status PENDENTE.
                        $reserva->update([
                            'client_name'    => $nomeCliente,
                            'status'         => 'confirmed',
                            'user_id'        => $dados['user_id'] ?? $reserva->user_id,
                            'is_fixed'       => false,
                            'total_paid'     => 0,         // Zera o financeiro
                            'signal_value'   => 0,         // Zera o sinal
                            'payment_status' => 'pending',   // Força status PENDENTE no caixa
                            'notes'          => trim(preg_replace('/###BACKUP###.*?###END###/s', '', $reserva->notes))
                        ]);

                        $dataReserva = date('d/m', strtotime($reserva->date));
                        $horaReserva = date('H:i', strtotime($reserva->start_time));
                        $valorIntegral = number_format($reserva->price, 2, ',', '.');
                        $valorPagoFormatado = number_format($valorOriginal, 2, ',', '.');

                        // --- 🚀 CONSTRUÇÃO DA MENSAGEM ---
                        $msg = "Boas notícias {$nomeCliente}! 👋\n\n";
                        $msg .= "A manutenção técnica foi concluída e seu horário para {$dataReserva} às {$horaReserva} foi REATIVADO! 🏟️";

                        if ($valorOriginal > 0.01) {
                            if ($reserva->is_recurrent && $acaoRealizada === 'credit') {
                                $dataExibicao = $dataDestino ?? \Carbon\Carbon::parse($reserva->date)->addWeek()->format('d/m');
                                $msg .= "\n\n⭐ Como seu horário é recorrente, o valor que deu de R$ {$valorPagoFormatado} ficou para o seu próximo jogo dia {$dataExibicao}.";
                                $msg .= "\nNo jogo do dia {$dataReserva} você terá de pagar o valor integral do seu horário.";
                            } else {
                                $msg .= "\n\n💰 Como realizamos o estorno do valor anterior, o pagamento integral de R$ {$valorIntegral} fica pendente para o momento do jogo. Te esperamos!";
                            }
                        } else {
                            $msg .= "\n\nTe aguardamos para a partida!";
                        }

                        $telefoneLimpo = preg_replace('/\D/', '', $dados['contact'] ?? '');
                        $waLink = "https://wa.me/55{$telefoneLimpo}?text=" . urlencode($msg);

                        return redirect()->route('admin.reservas.show', $reserva->id)->with([
                            'success'       => '👤 Cliente restaurado com sucesso!',
                            'whatsapp_link' => $waLink
                        ]);
                    }

                    return redirect()->back()->with('error', '⚠️ Falha: Dados de backup não encontrados nas notas.');
                }

                return redirect()->back();
            } catch (\Exception $e) {
                \Log::error("Erro na reativação de manutenção: " . $e->getMessage());
                return redirect()->back()->with('error', '❌ Erro interno: ' . $e->getMessage());
            }
        });
    }

    public function sincronizarDadosUsuario($id)
    {
        try {
            $reserva = Reserva::findOrFail($id);

            if (!$reserva->user_id) {
                return redirect()->back()->with('error', '⚠️ Esta reserva não está vinculada a um usuário cadastrado.');
            }

            $usuario = $reserva->user; // Assume que você tem a relation 'user' no model Reserva

            // 1. Atualiza os campos básicos
            $reserva->client_name = $usuario->name;
            $reserva->client_contact = $usuario->whatsapp_contact; // ou o campo que você usa para telefone

            // 2. Se estiver em MANUTENÇÃO, precisamos atualizar o JSON dentro das notas
            if ($reserva->status === 'maintenance' && !empty($reserva->notes)) {
                if (preg_match('/###BACKUP###(.*?)###END###/s', $reserva->notes, $matches)) {
                    $backupData = json_decode($matches[1], true);

                    // Atualiza os dados dentro do backup
                    $backupData['name'] = $usuario->name;
                    $backupData['contact'] = $usuario->whatsapp_contact;

                    $novoBackupString = "###BACKUP###" . json_encode($backupData) . "###END###";

                    // Substitui o backup antigo pelo novo nas notas
                    $reserva->notes = preg_replace('/###BACKUP###.*?###END###/s', $novoBackupString, $reserva->notes);
                }
            }

            $reserva->save();

            return redirect()->back()->with('success', '🔄 Dados sincronizados com o cadastro do usuário!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', '❌ Erro ao sincronizar: ' . $e->getMessage());
        }
    }
}
