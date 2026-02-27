<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $pageTitle }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-2xl sm:rounded-xl p-6 lg:p-10">

                @if (session('success'))
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg shadow-md"
                        role="alert">
                        <p class="font-medium">{{ session('success') }}</p>
                    </div>
                @endif
                @if (session('error'))
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg shadow-md"
                        role="alert">
                        <p class="font-medium">{{ session('error') }}</p>
                    </div>
                @endif
                @if (session('warning'))
                    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded-lg shadow-md"
                        role="alert">
                        <p class="font-medium">{{ session('warning') }}</p>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded" role="alert">
                        <p>Houve um erro na validação dos dados: Verifique se o motivo de cancelamento é válido.</p>
                    </div>
                @endif

                <!-- Botão de Volta para o Dashboard de Reservas -->
                <div class="mb-6">
                    <a href="{{ route('admin.reservas.index') }}"
                        class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-800 uppercase tracking-widest hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Voltar ao Painel de Reservas
                    </a>

                    @php
                        $hoje = \Carbon\Carbon::today()->toDateString();
                        // Verifica se o filtro de "Hoje" já está ativo
                        $isFiltradoHoje = request('start_date') == $hoje && request('end_date') == $hoje;
                    @endphp

                    <a href="{{ $isFiltradoHoje ? route('admin.reservas.confirmadas') : route('admin.reservas.confirmadas', ['start_date' => $hoje, 'end_date' => $hoje]) }}"
                        class="inline-flex items-center px-4 py-2.5 rounded-lg font-bold text-xs uppercase tracking-widest transition duration-150 shadow-md border {{ $isFiltradoHoje ? 'bg-blue-600 text-white border-blue-700 hover:bg-blue-700' : 'bg-white border-blue-500 text-blue-600 hover:bg-blue-50' }}"
                        title="{{ $isFiltradoHoje ? 'Remover filtro e ver tudo' : 'Mostrar apenas reservas de hoje' }}">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                            </path>
                        </svg>
                        {{ $isFiltradoHoje ? 'Ver Todas as Reservas' : 'Agendados para Hoje' }}
                    </a>
                </div>


                <div class="flex flex-col mb-8 space-y-4">
                    {{-- GRUPO DE FILTROS E PESQUISA --}}

                    <div
                        class="flex flex-col md:flex-row items-center md:items-center space-y-4 md:space-y-0 md:space-x-6 ">

                        {{-- 🎯 BOTÃO FILTRO RÁPIDO: HOJE --}}


                        {{-- Formulário de Pesquisa e Datas --}}
                        <form method="GET" action="{{ route('admin.reservas.confirmadas') }}"
                            class="flex flex-col md:flex-row items-end md:items-center space-y-4 md:space-y-0 md:space-x-4 w-full md:justify-start">
                            <input type="hidden" name="only_mine" value="{{ $isOnlyMine ? 'true' : 'false' }}">

                            {{-- FILTROS DE DATA --}}
                            <div class="flex space-x-3 w-full md:w-auto flex-shrink-0">
                                <div class="w-1/2 md:w-32">
                                    <label for="start_date"
                                        class="block text-xs font-semibold text-gray-500 mb-1">De:</label>
                                    <input type="date" name="start_date" id="start_date"
                                        value="{{ $startDate ?? '' }}"
                                        class="px-3 py-2 text-sm border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 w-full">
                                </div>
                                <div class="w-1/2 md:w-32">
                                    <label for="end_date"
                                        class="block text-xs font-semibold text-gray-500 mb-1">Até:</label>
                                    <input type="date" name="end_date" id="end_date" value="{{ $endDate ?? '' }}"
                                        class="px-3 py-2 text-sm border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 w-full">
                                </div>
                            </div>

                            {{-- Pesquisa de Texto, Arena e Botões --}}
                            <div class="flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-2 w-full items-end">

                                {{-- Pesquisa por Nome/Contato --}}
                                <div class="w-full md:flex-grow">
                                    <label for="search"
                                        class="block text-xs font-semibold text-gray-500 mb-1">Pesquisar:</label>
                                    <input type="text" name="search" id="search" value="{{ $search ?? '' }}"
                                        placeholder="Nome, contato..."
                                        class="px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 shadow-sm transition duration-150 w-full">
                                </div>

                                {{-- 🏟️ FILTRO DE ARENA (Adicionado para Multiquadra) --}}
                                <div class="w-full md:w-48">
                                    <label for="arena_id"
                                        class="block text-xs font-semibold text-gray-500 mb-1">Quadra:</label>
                                    <select name="arena_id" id="arena_id"
                                        class="px-3 py-2 text-sm border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 w-full">
                                        <option value="">Todas as Quadras</option>
                                        @foreach (\App\Models\Arena::all() as $arena)
                                            <option value="{{ $arena->id }}"
                                                {{ request('arena_id') == $arena->id ? 'selected' : '' }}>
                                                {{ $arena->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Botões de Ação --}}
                                <div class="flex items-end space-x-1 h-[42px]">
                                    <button type="submit"
                                        class="bg-indigo-600 hover:bg-indigo-700 text-white h-full p-2 rounded-lg shadow-md transition duration-150 flex-shrink-0 flex items-center justify-center"
                                        title="Buscar">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                            fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </button>

                                    @if ((isset($search) && $search) || $startDate || $endDate || request('arena_id'))
                                        <a href="{{ route('admin.reservas.confirmadas', ['only_mine' => $isOnlyMine ? 'true' : 'false']) }}"
                                            class="text-red-500 hover:text-red-700 h-full p-2 transition duration-150 flex-shrink-0 flex items-center justify-center rounded-lg border border-red-200"
                                            title="Limpar Busca e Filtros">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                                fill="currentColor">
                                                <path fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded-xl shadow-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider min-w-[120px]">
                                    Data/Hora</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                    Cliente/Reserva</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                    Quadra</th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider min-w-[90px]">
                                    Preço</th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider min-w-[100px]">
                                    Pagamento</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider min-w-[120px]">
                                    Criada Por</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider min-w-[100px]">
                                    Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse ($reservas as $reserva)
                                @php
                                    $agora = \Carbon\Carbon::now();
                                    $dataHoje = \Carbon\Carbon::today()->toDateString();
                                    $dataReserva = \Carbon\Carbon::parse($reserva->date)->toDateString();
                                    $eHoje = $dataReserva === $dataHoje;
                                    $ePassado = \Carbon\Carbon::parse($reserva->date)->isBefore($dataHoje);

                                    $caixaFechado = \App\Http\Controllers\FinanceiroController::isCashClosed(
                                        $dataReserva,
                                    );
                                    $status = $reserva->payment_status;
                                    $estaPago = $status === 'paid' || $reserva->status === 'completed';

                                    $isOverdue = false;
                                    if (!$estaPago && in_array($status, ['pending', 'unpaid', 'partial'])) {
                                        $reservaEndTime = \Carbon\Carbon::parse($reserva->date)->setTimeFromTimeString(
                                            $reserva->end_time,
                                        );
                                        if ($ePassado || ($eHoje && $reservaEndTime->isPast())) {
                                            $isOverdue = true;
                                        }
                                    }

                                    $badgeClass = $estaPago
                                        ? 'bg-green-100 text-green-800 border-green-200'
                                        : ($isOverdue
                                            ? 'bg-red-700 text-white font-bold animate-pulse'
                                            : 'bg-blue-100 text-blue-800 border-blue-200');
                                    $badgeText = $estaPago ? 'Pago' : ($isOverdue ? 'ATRASADO' : 'Pendente');
                                @endphp

                                <tr
                                    class="{{ $eHoje ? 'bg-blue-50/80 border-l-4 border-blue-600' : 'odd:bg-white even:bg-gray-50' }} hover:bg-indigo-50 transition duration-150">
                                    {{-- DATA/HORA --}}
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="text-sm font-bold text-gray-900">
                                            {{ \Carbon\Carbon::parse($reserva->date)->format('d/m/y') }}</div>
                                        <div class="text-indigo-600 text-xs font-semibold">{{ $reserva->start_time }}
                                            - {{ $reserva->end_time }}</div>
                                    </td>

                                    {{-- CLIENTE E TIPO (DISTINÇÃO) --}}
                                    <td class="px-4 py-3">
                                        <div class="flex items-center space-x-2">
                                            <span
                                                class="text-sm font-bold text-gray-900">{{ $reserva->client_name ?? 'N/D' }}</span>
                                            {{-- 🏷️ DISTINÇÃO PONTUAL / RECORRENTE --}}
                                            @if ($reserva->is_recurrent)
                                                <span
                                                    class="px-1.5 py-0.5 text-[10px] font-black uppercase rounded bg-purple-100 text-purple-700 border border-purple-200"
                                                    title="Reserva Mensalista/Recorrente">Recorrente</span>
                                            @else
                                                <span
                                                    class="px-1.5 py-0.5 text-[10px] font-black uppercase rounded bg-gray-100 text-gray-600 border border-gray-200"
                                                    title="Reserva Única">Pontual</span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-500">{{ $reserva->client_contact }}</div>
                                    </td>

                                    {{-- QUADRA --}}
                                    <td class="px-4 py-3">
                                        <span
                                            class="px-2 py-1 text-xs font-bold rounded bg-indigo-100 text-indigo-800 border border-indigo-200 shadow-sm">
                                            {{ $reserva->arena->name }}
                                        </span>
                                    </td>

                                    {{-- PREÇO --}}
                                    <td class="px-4 py-3 text-right font-bold text-green-700 whitespace-nowrap">
                                        R$ {{ number_format($reserva->price, 2, ',', '.') }}
                                    </td>

                                    {{-- PAGAMENTO --}}
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                                            {{ $badgeText }}
                                        </span>
                                    </td>

                                    {{-- 👤 CRIADA POR --}}
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <div class="flex flex-col">
                                            <span
                                                class="font-semibold text-gray-800">{{ $reserva->manager->name ?? 'Sistema/Web' }}</span>
                                            <span
                                                class="text-[10px] text-gray-400">{{ $reserva->created_at->format('d/m/y H:i') }}</span>
                                        </div>
                                    </td>

                                    {{-- AÇÕES --}}
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col space-y-1.5">
                                            {{-- Botão Detalhes --}}
                                            <a href="{{ route('admin.reservas.show', $reserva) }}"
                                                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-1.5 text-xs font-bold rounded text-center transition duration-150 shadow-sm">
                                                Detalhes
                                            </a>

                                            @if (!$caixaFechado)
                                                @php
                                                    $valorPagoNumerico = (float) ($reserva->total_paid ?? 0);
                                                @endphp

                                                {{-- 🎯 BOTÃO LANÇAR FALTA: Só aparece se houver pagamento --}}
                                                @if ($reserva->status !== 'no_show' && ($estaPago || $valorPagoNumerico > 0))
                                                    <button type="button"
                                                        onclick="openNoShowModal(
                        {{ $reserva->id }},
                        '{{ addslashes($reserva->client_name) }}',
                        {{ $valorPagoNumerico }}
                    )"
                                                        class="w-full bg-orange-500 hover:bg-orange-600 text-white py-1.5 text-xs font-bold rounded text-center transition duration-150 shadow-sm">
                                                        Lançar Falta
                                                    </button>
                                                @endif

                                                {{-- Botão Pagamento --}}
                                                @if (!$estaPago)
                                                    <a href="{{ route('admin.payment.index', ['reserva_id' => $reserva->id]) }}"
                                                        class="w-full bg-green-600 hover:bg-green-700 text-white py-1.5 text-xs font-bold rounded text-center transition duration-150 shadow-sm">
                                                        Lançar Pagto
                                                    </a>
                                                @endif

                                                {{-- Botão Cancelar (Ajustado) --}}
                                                <button type="button"
                                                    onclick="openCancellationModal(
                    {{ $reserva->id }},
                    'PATCH',
                    '{{ route('admin.reservas.cancelar', ':id') }}',
                    'Deseja realmente cancelar este agendamento {{ $reserva->is_recurrent ? '(MENSALISTA)' : '' }}?',
                    'Confirmar Cancelamento',
                    {{ $valorPagoNumerico }},
                    {{ $reserva->is_recurrent ? 'true' : 'false' }}
                )"
                                                    class="w-full {{ $reserva->is_recurrent ? 'bg-red-800' : 'bg-red-600' }} hover:opacity-90 text-white py-1.5 text-xs font-bold rounded text-center transition duration-150 shadow-sm"
                                                    title="{{ $reserva->is_recurrent ? 'Reserva Recorrente' : 'Reserva Pontual' }}">
                                                    Cancelar {{ $reserva->is_recurrent ? 'Série' : '' }}
                                                </button>
                                            @else
                                                {{-- Botão Bloqueado (Caixa Fechado) --}}
                                                <div class="w-full bg-gray-400 text-white py-1.5 text-xs font-bold rounded text-center opacity-75 cursor-not-allowed flex items-center justify-center select-none"
                                                    title="Bloqueado: O caixa desta data ({{ \Carbon\Carbon::parse($reserva->date)->format('d/m/Y') }}) já foi encerrado.">
                                                    <svg class="w-3.5 h-3.5 mr-1" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                    </svg>
                                                    🔒 Bloqueado
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-10 text-center text-gray-500 italic">Nenhuma
                                        reserva encontrada.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-8">
                    {{-- ✅ ATUALIZADO: Inclui filtros de data, busca e only_mine na paginação --}}
                    {{ $reservas->appends(['search' => $search, 'only_mine' => $isOnlyMine ? 'true' : 'false', 'start_date' => $startDate ?? '', 'end_date' => $endDate ?? ''])->links() }}
                </div>

            </div>
        </div>
    </div>


    {{-- MODAL DE CANCELAMENTO ATUALIZADO (MULTIQUADRA + RECORRÊNCIA) --}}
    <div id="cancellation-modal"
        class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden items-center justify-center z-50 transition-opacity duration-300">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 m-4 transform transition-transform duration-300 scale-95 opacity-0"
            id="cancellation-modal-content" onclick="event.stopPropagation()">

            <h3 id="modal-title" class="text-xl font-bold text-red-700 mb-4 border-b pb-2">Confirmação de Cancelamento
            </h3>

            <p id="modal-message" class="text-gray-700 mb-4"></p>

            {{-- 📅 1. SEÇÃO DE RECORRÊNCIA (Aparece apenas para mensalistas) --}}
            <div id="recurrent-options"
                class="hidden mb-6 p-4 bg-indigo-50 border-l-4 border-indigo-500 rounded-r-lg">
                <p class="text-sm font-bold text-indigo-900 mb-3">Esta é uma reserva RECORRENTE:</p>

                <div class="space-y-3">
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="cancel_type" value="pontual" checked
                            class="w-4 h-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                        <div class="ml-3">
                            <span class="block text-sm font-bold text-gray-700">Cancelar apenas este dia</span>
                            <span class="block text-xs text-gray-500">Libera a quadra apenas para esta data
                                específica.</span>
                        </div>
                    </label>

                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="cancel_type" value="serie"
                            class="w-4 h-4 text-red-600 border-gray-300 focus:ring-red-500">
                        <div class="ml-3">
                            <span class="block text-sm font-bold text-gray-700">Cancelar TODA a série</span>
                            <span class="block text-xs text-gray-500">Remove todos os agendamentos futuros desta
                                mensalidade.</span>
                        </div>
                    </label>
                </div>
            </div>

            {{-- 💰 2. SEÇÃO DE ESTORNO (Dinâmica baseado no total_paid) --}}
            <div id="refund-section" class="hidden mb-6 p-4 bg-yellow-50 border-l-4 border-yellow-400 rounded-r-lg">
                <div class="flex items-center mb-2">
                    <svg class="h-5 w-5 text-yellow-600 mr-2" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-sm font-bold text-yellow-800">Pagamento Identificado: R$ <span
                            id="paid-value-display">0,00</span></span>
                </div>

                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="should_refund" name="should_refund" value="1"
                        class="sr-only peer">
                    <div
                        class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600">
                    </div>
                    <span class="ml-3 text-sm font-medium text-gray-700 font-bold">Estornar valor no caixa</span>
                </label>
                <p class="text-xs text-gray-500 mt-2 italic">* Se ativado, será gerado um lançamento de SAÍDA no caixa
                    hoje.</p>
            </div>

            {{-- ✍️ 3. MOTIVO --}}
            <div class="mb-6">
                <label for="cancellation-reason-input" class="block text-sm font-medium text-gray-700 mb-2 font-bold">
                    Motivo do Cancelamento:
                </label>
                <textarea id="cancellation-reason-input" rows="3"
                    class="w-full p-2 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                    placeholder="Descreva obrigatoriamente o motivo..."></textarea>
            </div>

            {{-- 🔘 4. AÇÕES --}}
            <div class="flex justify-end space-x-3 border-t pt-4">
                <button onclick="closeCancellationModal()" type="button"
                    class="px-4 py-2 bg-gray-200 text-gray-800 font-semibold rounded-lg hover:bg-gray-300 transition duration-150">
                    Voltar
                </button>
                <button id="confirm-cancellation-btn" type="button"
                    class="px-4 py-2 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700 transition duration-150 shadow-lg">
                    Confirmar Cancelamento
                </button>
            </div>
        </div>
    </div>

    {{-- NOVO: MODAL DE REGISTRO DE FALTA (NO-SHOW) --}}
    <div id="no-show-modal"
        class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden items-center justify-center z-50 transition-opacity duration-300">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 m-4 transform transition-transform duration-300 scale-95 opacity-0"
            id="no-show-modal-content" onclick="event.stopPropagation()">

            <h3 class="text-xl font-bold text-red-700 mb-4 border-b pb-2 text-center uppercase">Marcar como Falta
                (No-Show)</h3>

            <div id="no-show-refund-area" class="mb-6 p-4 border border-red-300 bg-red-50 rounded-lg hidden">
                <p class="font-bold text-red-700 mb-3 flex items-center">
                    VALOR JÁ PAGO: R$ <span id="no-show-paid-amount-display" class="font-extrabold ml-1">0,00</span>
                </p>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Decisão Financeira:</label>
                    <select id="should_refund_no_show" onchange="toggleNoShowRefundInput()"
                        class="w-full p-2 border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500 font-bold">
                        <option value="false">🔒 Reter Tudo (Multa para Arena)</option>
                        <option value="true">💸 Estornar Valor (Saída do Caixa)</option>
                    </select>
                </div>

                <div id="customNoShowRefundDiv"
                    class="hidden mt-4 p-3 bg-white rounded-lg border border-red-200 shadow-inner">
                    <label class="block text-xs font-bold text-red-600 uppercase mb-1">Valor a Devolver ao Cliente
                        (R$):</label>
                    <input type="number" step="0.01" id="custom_no_show_refund_amount"
                        class="w-full p-2 border-red-300 rounded-md focus:ring-red-500 focus:border-red-500 font-bold text-lg">
                    <span id="no-show-error-span" class="text-[10px] text-red-600 font-bold mt-1 hidden">O estorno não
                        pode ser maior que o valor pago.</span>
                </div>
            </div>

            <div class="mb-4">
                <label for="no-show-reason-input"
                    class="block text-sm font-medium text-gray-700 mb-2 font-bold">Motivo da Falta:</label>
                <textarea id="no-show-reason-input" rows="3"
                    class="w-full p-2 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                    placeholder="Ex: Cliente não compareceu e não atende o telefone..."></textarea>
                <span id="no-show-reason-error" class="text-[10px] text-red-600 font-bold mt-1 hidden">Mínimo 5
                    caracteres.</span>
            </div>

            <div class="flex justify-end space-x-3 border-t pt-4">
                <button onclick="closeNoShowModal()"
                    class="px-4 py-2 bg-gray-200 text-gray-800 font-semibold rounded-lg hover:bg-gray-300 transition">Voltar</button>
                <button id="confirm-no-show-btn"
                    class="px-4 py-2 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700 transition shadow-lg">Confirmar
                    Falta</button>
            </div>
        </div>
    </div>


    <script>
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        const CSRF_TOKEN = metaTag ? metaTag.getAttribute('content') : null;

        // URLs de Cancelamento
        const CANCEL_PONTUAL_URL = '{{ route('admin.reservas.cancelar_pontual', ':id') }}';
        const CANCEL_SERIE_URL = '{{ route('admin.reservas.cancelar_serie', ':id') }}';
        const CANCEL_PADRAO_URL = '{{ route('admin.reservas.cancelar', ':id') }}';

        // 🚩 URL de Falta (No-Show)
        const NO_SHOW_URL = '{{ route('admin.reservas.no_show', ':id') }}';

        // Variáveis de Controle Global
        let currentReservaId = null;
        let currentTotalPaid = 0;
        let currentMethod = null;
        let currentUrlBase = null;

        // --- FUNÇÕES DO MODAL DE CANCELAMENTO ---
        function openCancellationModal(reservaId, method, urlBase, message, buttonText, totalPaid = 0, isRecurrent =
        false) {
            currentReservaId = reservaId;
            currentMethod = method;
            currentUrlBase = urlBase;
            currentTotalPaid = totalPaid;

            document.getElementById('cancellation-reason-input').value = '';
            if (document.getElementById('should_refund')) document.getElementById('should_refund').checked = false;
            document.getElementById('modal-message').textContent = message;
            document.getElementById('confirm-cancellation-btn').textContent = buttonText;

            const refundSection = document.getElementById('refund-section');
            if (totalPaid > 0 && refundSection) {
                refundSection.classList.remove('hidden');
                document.getElementById('paid-value-display').textContent = parseFloat(totalPaid).toLocaleString('pt-BR', {
                    minimumFractionDigits: 2
                });
            } else if (refundSection) {
                refundSection.classList.add('hidden');
            }

            const recurrentSection = document.getElementById('recurrent-options');
            if (isRecurrent && recurrentSection) {
                recurrentSection.classList.remove('hidden');
            } else if (recurrentSection) {
                recurrentSection.classList.add('hidden');
            }

            const modal = document.getElementById('cancellation-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => document.getElementById('cancellation-modal-content').classList.remove('opacity-0',
                'scale-95'), 10);
        }

        function closeCancellationModal() {
            document.getElementById('cancellation-modal-content').classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                const modal = document.getElementById('cancellation-modal');
                modal.classList.remove('flex');
                modal.classList.add('hidden');
            }, 300);
        }

        // --- 🚩 FUNÇÕES DO MODAL DE FALTA (NO-SHOW) ---
        function openNoShowModal(reservaId, clientName, totalPaid) {
            currentReservaId = reservaId;
            currentTotalPaid = parseFloat(totalPaid);

            document.getElementById('no-show-reason-input').value = '';
            document.getElementById('should_refund_no_show').value = 'false';
            document.getElementById('customNoShowRefundDiv').classList.add('hidden');

            const refundArea = document.getElementById('no-show-refund-area');
            if (currentTotalPaid > 0) {
                refundArea.classList.remove('hidden');
                document.getElementById('no-show-paid-amount-display').textContent = currentTotalPaid.toLocaleString(
                    'pt-BR', {
                        minimumFractionDigits: 2
                    });
                document.getElementById('custom_no_show_refund_amount').value = currentTotalPaid.toFixed(2);
            } else {
                refundArea.classList.add('hidden');
            }

            const modal = document.getElementById('no-show-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => document.getElementById('no-show-modal-content').classList.remove('opacity-0', 'scale-95'),
            10);
        }

        function closeNoShowModal() {
            document.getElementById('no-show-modal-content').classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                const modal = document.getElementById('no-show-modal');
                modal.classList.remove('flex');
                modal.classList.add('hidden');
            }, 300);
        }

        function toggleNoShowRefundInput() {
            const shouldRefund = document.getElementById('should_refund_no_show').value === 'true';
            document.getElementById('customNoShowRefundDiv').classList.toggle('hidden', !shouldRefund);
        }

        // --- PROCESSAMENTO DAS REQUISIÇÕES ---
        document.addEventListener('DOMContentLoaded', function() {

            // 1. Clique Confirmar Cancelamento
            const confirmCancelBtn = document.getElementById('confirm-cancellation-btn');
            if (confirmCancelBtn) {
                confirmCancelBtn.addEventListener('click', function() {
                    const reason = document.getElementById('cancellation-reason-input').value.trim();
                    const shouldRefund = document.getElementById('should_refund') ? document.getElementById(
                        'should_refund').checked : false;

                    if (reason.length < 5) {
                        alert("Descreva o motivo (mínimo 5 caracteres).");
                        return;
                    }

                    let url = currentUrlBase;
                    let method = 'POST'; // Forçamos POST para rotas Route::post do Laravel
                    const recurrentSection = document.getElementById('recurrent-options');

                    if (recurrentSection && !recurrentSection.classList.contains('hidden')) {
                        const tipo = document.querySelector('input[name="cancel_type"]:checked').value;
                        if (tipo === 'pontual') {
                            url = CANCEL_PONTUAL_URL;
                            method =
                            'PATCH'; // Aqui usamos spoofing pois a rota de cancelamento pontual geralmente é PATCH
                        } else {
                            url = CANCEL_SERIE_URL;
                            method = 'DELETE'; // Aqui usamos spoofing para DELETE
                        }
                    }

                    executeAjax(url.replace(':id', currentReservaId), method, {
                        cancellation_reason: reason,
                        should_refund: shouldRefund ? 1 : 0,
                        paid_amount_ref: currentTotalPaid
                    }, confirmCancelBtn);
                });
            }

            // 2. Clique Confirmar Falta (No-Show)
            const confirmNoShowBtn = document.getElementById('confirm-no-show-btn');
            if (confirmNoShowBtn) {
                confirmNoShowBtn.addEventListener('click', function() {
                    const reason = document.getElementById('no-show-reason-input').value.trim();
                    const shouldRefund = document.getElementById('should_refund_no_show').value === 'true';
                    const refundAmount = parseFloat(document.getElementById('custom_no_show_refund_amount')
                        .value) || 0;

                    if (reason.length < 5) {
                        alert("Descreva o motivo da falta.");
                        return;
                    }
                    if (shouldRefund && refundAmount > currentTotalPaid) {
                        alert("Valor de estorno inválido.");
                        return;
                    }

                    // CORREÇÃO AQUI: Mudamos de 'PATCH' para 'POST' para bater com seu Route::post do web.php
                    executeAjax(NO_SHOW_URL.replace(':id', currentReservaId), 'POST', {
                        no_show_reason: reason,
                        should_refund: shouldRefund,
                        refund_amount: refundAmount,
                        block_user: true
                    }, confirmNoShowBtn);
                });
            }
        });

        // Função auxiliar para evitar repetição de código Fetch
        async function executeAjax(url, method, data, button) {
            const originalText = button.textContent;
            button.disabled = true;
            button.textContent = 'Processando...';

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        ...data,
                        _token: CSRF_TOKEN,
                        _method: method
                    })
                });

                const result = await response.json();
                if (response.ok) {
                    alert(result.message || "Operação realizada!");
                    window.location.reload();
                } else {
                    alert(result.message || "Erro ao processar.");
                    button.disabled = false;
                    button.textContent = originalText;
                }
            } catch (e) {
                alert("Erro de conexão.");
                button.disabled = false;
                button.textContent = originalText;
            }
        }
    </script>
</x-app-layout>
