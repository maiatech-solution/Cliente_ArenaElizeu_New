<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $pageTitle }}
        </h2>
    </x-slot>

    <style>
        /* Estilos para badges de Status na tabela */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
        }

        /* Status do Cliente */
        .status-confirmed {
            background-color: #d1fae5;
            color: #065f46;
        }

        /* Verde - Confirmado */
        .status-pending {
            background-color: #ffedd5;
            color: #9a3412;
        }

        /* Laranja - Pendente */
        .status-cancelled {
            background-color: #bfdbfe;
            color: #1e40af;
        }

        /* Azul - Cancelado */
        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }

        /* Vermelho - Rejeitado */
        .status-noshow {
            background-color: #fca5a5;
            color: #b91c1c;
        }

        /* Vermelho Claro - Falta (No Show) */
        /* Status de Inventário (Slots Fixos) */
        .status-free {
            background-color: #e0f2fe;
            color: #075985;
        }

        /* Azul Claro - Livre */
        .status-maintenance {
            background-color: #fce7f3;
            color: #9d174d;
        }

        /* Rosa/Roxo - Manutenção */
    </style>

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

                <div class="mb-6 flex flex-wrap gap-3">

                    {{-- Botão Voltar --}}
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
                        // Verifica se o filtro de "Hoje" já está aplicado na URL
                        $isFiltradoHoje = request('start_date') == $hoje && request('end_date') == $hoje;
                    @endphp

                    {{-- Botão "Agendados para Hoje" (Adaptado para a rota 'todas') --}}
                    <a href="{{ $isFiltradoHoje ? route('admin.reservas.todas') : route('admin.reservas.todas', ['start_date' => $hoje, 'end_date' => $hoje]) }}"
                        class="inline-flex items-center px-4 py-2.5 rounded-lg font-bold text-xs uppercase tracking-widest transition duration-150 shadow-md border {{ $isFiltradoHoje ? 'bg-blue-600 text-white border-blue-700 hover:bg-blue-700' : 'bg-white border-blue-500 text-blue-600 hover:bg-blue-50' }}"
                        title="{{ $isFiltradoHoje ? 'Remover filtro e ver tudo' : 'Mostrar apenas registros de hoje' }}">

                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                            </path>
                        </svg>
                        {{ $isFiltradoHoje ? 'Ver Todo o Histórico' : 'Agendados para Hoje' }}
                    </a>
                </div>

                <div class="flex flex-col mb-8 space-y-4">
                    <div class="flex flex-col md:flex-row items-center space-y-4 md:space-y-0 md:space-x-6 w-full">

                        <form method="GET" action="{{ route('admin.reservas.todas') }}"
                            class="flex flex-col md:flex-row items-end md:items-center space-y-4 md:space-y-0 md:space-x-4 w-full">
                            <input type="hidden" name="only_mine" value="{{ $isOnlyMine ? 'true' : 'false' }}">

                            {{-- 1. Filtro de Status --}}
                            <div class="w-full md:w-36 flex-shrink-0">
                                <label for="filter_status"
                                    class="block text-xs font-semibold text-gray-500 mb-1">Status:</label>
                                <select name="filter_status" id="filter_status"
                                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 w-full">
                                    <option value="">Todos os Status</option>
                                    <option value="confirmed"
                                        {{ ($filterStatus ?? '') === 'confirmed' ? 'selected' : '' }}>Confirmadas
                                    </option>
                                    <option value="pending"
                                        {{ ($filterStatus ?? '') === 'pending' ? 'selected' : '' }}>
                                        Pendentes</option>
                                    <option value="cancelled"
                                        {{ ($filterStatus ?? '') === 'cancelled' ? 'selected' : '' }}>Canceladas
                                    </option>
                                    <option value="rejected"
                                        {{ ($filterStatus ?? '') === 'rejected' ? 'selected' : '' }}>Rejeitadas
                                    </option>
                                    <option value="no_show"
                                        {{ ($filterStatus ?? '') === 'no_show' ? 'selected' : '' }}>Falta (No Show)
                                    </option>
                                    <option value="free" {{ ($filterStatus ?? '') === 'free' ? 'selected' : '' }}>
                                        Livre (Slots)</option>
                                    <option value="maintenance"
                                        {{ ($filterStatus ?? '') === 'maintenance' ? 'selected' : '' }}>Manutenção
                                    </option>
                                </select>
                            </div>

                            {{-- ✅ 2. NOVO: Filtro de Arena (Multiquadra) --}}
                            <div class="w-full md:w-40 flex-shrink-0">
                                <label for="arena_id"
                                    class="block text-xs font-semibold text-gray-500 mb-1">Arena/Quadra:</label>
                                <select name="arena_id" id="arena_id"
                                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 w-full">
                                    <option value="">Todas as Quadras</option>
                                    @foreach (\App\Models\Arena::all() as $arena)
                                        <option value="{{ $arena->id }}"
                                            {{ ($arenaId ?? '') == $arena->id ? 'selected' : '' }}>
                                            {{ $arena->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- 3. Filtros de Data --}}
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

                            {{-- 4. Pesquisa e Botões --}}
                            <div class="flex space-x-2 w-full md:w-auto items-end flex-grow md:flex-grow-0">
                                <div class="flex-grow">
                                    <label for="search"
                                        class="block text-xs font-semibold text-gray-500 mb-1">Pesquisar:</label>
                                    <input type="text" name="search" id="search" value="{{ $search ?? '' }}"
                                        placeholder="Nome, contato..."
                                        class="px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 shadow-sm transition duration-150 w-full">
                                </div>

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

                                    @if ((isset($search) && $search) || $startDate || $endDate || $filterStatus || ($arenaId ?? ''))
                                        <a href="{{ route('admin.reservas.todas', ['only_mine' => $isOnlyMine ? 'true' : 'false']) }}"
                                            class="text-red-500 hover:text-red-700 h-full p-2 transition duration-150 flex-shrink-0 flex items-center justify-center rounded-lg border border-red-200"
                                            title="Limpar Busca e Filtros">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                                viewBox="0 0 20 20" fill="currentColor">
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
                                    class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider min-w-[100px]">
                                    Arena</th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider min-w-[90px]">
                                    Preço</th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider min-w-[100px]">
                                    Status</th>
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
                                    // 1. Definição da trava de segurança (Colaborador precisa de autorização)
                                    $isColaborador = auth()->user()->role === 'colaborador';

                                    // 2. Dados de ocupação para o modal de manutenção
                                    $isOccupied = ($reserva->client_name || $reserva->user_id) && !$reserva->is_fixed;
                                    $client = addslashes($reserva->client_name ?? ($reserva->user->name ?? 'Externo'));
                                @endphp

                                <tr class="odd:bg-white even:bg-gray-50 hover:bg-indigo-50 transition duration-150">

                                    {{-- 1. DATA/HORA (Exatamente como solicitado) --}}
                                    <td class="px-4 py-3 whitespace-nowrap min-w-[120px]">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ \Carbon\Carbon::parse($reserva->date)->format('d/m/y') }}
                                        </div>
                                        <div class="text-indigo-600 text-xs font-semibold">
                                            {{ \Carbon\Carbon::parse($reserva->start_time)->format('H:i') }} -
                                            {{ \Carbon\Carbon::parse($reserva->end_time)->format('H:i') }}
                                        </div>
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            <span
                                                class="text-[9px] font-bold {{ $reserva->is_recurrent ? 'text-indigo-700 bg-indigo-100' : 'text-blue-700 bg-blue-100' }} px-1 rounded uppercase">
                                                {{ $reserva->is_recurrent ? 'Recorrente' : 'Pontual' }}
                                            </span>
                                        </div>
                                    </td>

                                    {{-- 2. CLIENTE/RESERVA --}}
                                    <td class="px-4 py-3 text-left">
                                        <div class="text-sm font-semibold text-gray-900">
                                            {{ $reserva->user->name ?? $reserva->client_name }}</div>
                                        <div class="text-[10px] text-green-600 font-bold uppercase">Cliente Web</div>
                                    </td>

                                    {{-- 3. ARENA --}}
                                    <td class="px-4 py-3 text-left">
                                        <span
                                            class="inline-flex items-center px-2 py-1 rounded-lg text-[10px] font-black bg-indigo-50 text-indigo-700 border border-indigo-100 uppercase whitespace-nowrap">
                                            {{ $reserva->arena->name ?? 'Fut' }}
                                        </span>
                                    </td>

                                    {{-- 4. PREÇO --}}
                                    <td
                                        class="px-4 py-3 whitespace-nowrap min-w-[90px] text-sm font-bold text-green-700 text-right">
                                        R$ {{ number_format($reserva->price ?? 0, 2, ',', '.') }}
                                    </td>

                                    {{-- 5. STATUS DA RESERVA --}}
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <span class="status-badge status-{{ $reserva->status }}">
                                            AGUARDANDO
                                        </span>
                                    </td>

                                    {{-- 6. PAGAMENTO (Omitido por brevidade, manter original) --}}
                                    <td class="px-4 py-3 text-center whitespace-nowrap">Maia</td>

                                    {{-- 7. CRIADA POR --}}
                                    <td class="px-4 py-3 text-left min-w-[120px]">
                                        <span
                                            class="text-gray-400 text-[10px] uppercase font-bold italic">Automático</span>
                                    </td>

                                    {{-- 8. AÇÕES (COM A TRAVA DO COLABORADOR APLICADA) --}}
                                    <td class="px-4 py-3 text-sm font-medium min-w-[120px]">
                                        <div class="flex flex-col space-y-1.5">

                                            {{-- Botão Detalhes --}}
                                            <a href="{{ route('admin.reservas.show', $reserva) }}"
                                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 text-[10px] font-bold rounded shadow uppercase text-center transition">
                                                Detalhes
                                            </a>

                                            {{-- Botão Manutenção (Protegido) --}}
                                            @if ($reserva->status === 'maintenance')
                                                <button type="button"
                                                    onclick="{{ $isColaborador ? "window.requisitarAutorizacao(token => { if(token) handleFixedSlotToggle($reserva->id, 'free'); })" : "handleFixedSlotToggle($reserva->id, 'free')" }}"
                                                    class="bg-emerald-500 hover:bg-emerald-600 text-white px-3 py-1 text-[10px] font-bold rounded shadow uppercase text-center transition">
                                                    Liberar Agenda
                                                </button>
                                            @else
                                                <button type="button"
                                                    onclick="{{ $isColaborador ? "window.requisitarAutorizacao(token => { if(token) handleFixedSlotToggle($reserva->id, 'maintenance', " . ($isOccupied ? 'true' : 'false') . ", '$client'); })" : "handleFixedSlotToggle($reserva->id, 'maintenance', " . ($isOccupied ? 'true' : 'false') . ", '$client')" }}"
                                                    class="bg-pink-600 hover:bg-pink-700 text-white px-3 py-1 text-[10px] font-bold rounded shadow uppercase text-center transition">
                                                    Manutenção
                                                </button>
                                            @endif

                                            {{-- Botão Ajustar Valor (Protegido) --}}
                                            @if (!$reserva->is_fixed && in_array($reserva->status, ['confirmed', 'pending']))
                                                <button type="button"
                                                    onclick="{{ $isColaborador ? "window.requisitarAutorizacao(token => { if(token) openPriceUpdateModal($reserva->id, " . ($reserva->price ?? 0) . ", '$client', " . ($reserva->is_recurrent ? 'true' : 'false') . '); })' : "openPriceUpdateModal($reserva->id, " . ($reserva->price ?? 0) . ", '$client', " . ($reserva->is_recurrent ? 'true' : 'false') . ')' }}"
                                                    class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 text-[10px] font-bold rounded shadow uppercase transition text-center">
                                                    Ajustar Valor
                                                </button>
                                            @endif

                                        </div>
                                    </td>
                                </tr>
                            @empty
                                {{-- ... manter empty state original ... --}}
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-8">
                    {{-- Paginação com todos os filtros --}}
                    {{ $reservas->appends(['search' => $search, 'only_mine' => $isOnlyMine ? 'true' : 'false', 'start_date' => $startDate ?? '', 'end_date' => $endDate ?? '', 'filter_status' => $filterStatus ?? ''])->links() }}
                </div>

            </div>
        </div>
    </div>


    {{-- 🆕 NOVO MODAL DE ALTERAÇÃO DE PREÇO REFINADO COM ESCOPO --}}
    <div id="price-update-modal"
        class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden items-center justify-center z-50 transition-opacity duration-300">
        <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-lg p-8 m-4 transform transition-transform duration-300 scale-95 opacity-0"
            id="price-update-modal-content" onclick="event.stopPropagation()">

            <div class="flex items-center gap-3 mb-6 border-b pb-4">
                <div class="bg-blue-100 p-2 rounded-full">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                        </path>
                    </svg>
                </div>
                <h3 class="text-xl font-black text-gray-900 uppercase tracking-tighter">Ajustar Valor</h3>
            </div>

            <div class="bg-gray-50 rounded-2xl p-5 mb-6 border border-gray-100">
                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest block mb-1">Alvo da
                    Alteração</label>
                <p class="text-sm text-gray-700 font-bold leading-tight">
                    Reserva: <span id="price-update-target-name" class="text-blue-600 uppercase"></span>
                </p>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100">
                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest block mb-1">Preço
                        Atual</label>
                    <span id="current-price-display" class="text-lg font-black text-gray-700 font-mono">R$ 0,00</span>
                </div>
                <div class="bg-blue-50 p-4 rounded-2xl border border-blue-100">
                    <label for="new-price-input"
                        class="text-[9px] font-black text-blue-500 uppercase tracking-widest block mb-1">Novo Preço
                        (R$)</label>
                    <input type="number" step="0.01" min="0" id="new-price-input"
                        class="w-full bg-transparent border-none p-0 text-lg font-black text-blue-700 font-mono focus:ring-0"
                        placeholder="0.00">
                </div>
            </div>

            {{-- 🔄 ESCOPO DA ALTERAÇÃO (Apenas para Recorrentes) --}}
            <div id="price-scope-container" class="mb-6 hidden">
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Aplicar esta
                    mudança em:</label>
                <div class="grid grid-cols-2 gap-3">
                    <label
                        class="flex items-center p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-blue-50 transition">
                        <input type="radio" name="price_scope" value="single" checked
                            class="text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-xs font-bold text-gray-700 uppercase">Apenas hoje</span>
                    </label>
                    <label
                        class="flex items-center p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-blue-50 transition">
                        <input type="radio" name="price_scope" value="series"
                            class="text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-xs font-bold text-gray-700 uppercase">Toda a série</span>
                    </label>
                </div>
            </div>

            <div class="mb-8">
                <label for="price-justification-input"
                    class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">
                    Justificativa da Mudança: <span class="text-red-500">*</span>
                </label>
                <textarea id="price-justification-input" rows="2"
                    class="w-full p-4 bg-gray-50 border-gray-100 rounded-2xl text-sm focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Ex: Desconto fidelidade, ajuste de feriado..."></textarea>
                <p id="price-justification-error"
                    class="text-[10px] font-bold text-red-500 mt-2 hidden uppercase tracking-tight"></p>
            </div>

            <div class="flex gap-3">
                <button onclick="closePriceUpdateModal()" type="button"
                    class="flex-1 px-4 py-4 bg-gray-100 text-gray-500 font-black text-[10px] uppercase rounded-2xl hover:bg-gray-200 transition">
                    Voltar
                </button>
                <button id="confirm-price-update-btn" type="button"
                    class="flex-1 px-4 py-4 bg-blue-600 text-white font-black text-[10px] uppercase rounded-2xl hover:bg-blue-700 transition shadow-lg shadow-blue-100">
                    Confirmar Preço
                </button>
            </div>
        </div>
    </div>


    {{-- SCRIPTS DE AÇÃO AJAX --}}
    <script>
        // Variáveis de Rota e Token
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        const CSRF_TOKEN = metaTag ? metaTag.getAttribute('content') : null;
        // Usamos as rotas do web.php.
        const CANCEL_PONTUAL_URL = '{{ route('admin.reservas.cancelar_pontual', ':id') }}';
        const CANCEL_SERIE_URL = '{{ route('admin.reservas.cancelar_serie', ':id') }}';
        const CANCEL_PADRAO_URL = '{{ route('admin.reservas.cancelar', ':id') }}';
        const REACTIVATE_URL = '{{ route('admin.reservas.reativar', ':id') }}';
        const UPDATE_SLOT_STATUS_URL = '{{ route('admin.config.update_status', ':id') }}';
        const REGISTER_NOSHOW_URL = '{{ route('admin.reservas.no_show', ':id') }}';
        // 🆕 ROTA DE ATUALIZAÇÃO DE PREÇO
        const UPDATE_PRICE_URL = '{{ route('admin.reservas.update_price', ':id') }}';


        let currentReservaId = null;
        let currentMethod = null; // PATCH ou DELETE (Método Lógico)
        let currentUrlBase = null;

        // Adicione esta variável no topo do seu <script>
        let currentReservaPaidAmount = 0;

        function openCancellationModal(reservaId, method, urlBase, message, buttonText, hasPayment = false, totalPaid = 0) {
            currentReservaId = reservaId;
            currentMethod = method;
            currentUrlBase = urlBase;
            currentReservaPaidAmount = totalPaid; // Armazena o valor vindo da tabela

            document.getElementById('cancellation-reason-input').value = '';
            const estornoCheckbox = document.getElementById('estornar-credito-checkbox');
            if (estornoCheckbox) estornoCheckbox.checked = false;

            const estornoContainer = document.getElementById('estorno-container');
            if (estornoContainer) {
                hasPayment ? estornoContainer.classList.remove('hidden') : estornoContainer.classList.add('hidden');
            }

            document.getElementById('modal-title').textContent = buttonText;
            document.getElementById('modal-message').textContent = message;

            const modal = document.getElementById('cancellation-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            setTimeout(() => {
                document.getElementById('cancellation-modal-content').classList.remove('opacity-0', 'scale-95');
            }, 10);
        }

        /**
         * Fecha o modal de cancelamento.
         */
        function closeCancellationModal() {
            document.getElementById('cancellation-modal-content').classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                document.getElementById('cancellation-modal').classList.remove('flex');
                document.getElementById('cancellation-modal').classList.add('hidden');
            }, 300);
        }

        // --- LÓGICA DE REGISTRO DE FALTA (NO SHOW) ---

        /**
         * Abre o modal de registro de falta.
         */
        function openNoShowModal(reservaId, title, message, urlBase) {
            currentReservaId = reservaId;
            currentMethod =
                'POST'; // A ação é um POST ou PATCH, dependendo da sua rota de controller. Usaremos POST para simplicidade.
            currentUrlBase = urlBase;

            document.getElementById('noshow-modal-title').textContent = title;
            document.getElementById('noshow-modal-message').textContent = message;

            document.getElementById('noshow-modal').classList.remove('hidden');
            document.getElementById('noshow-modal').classList.add('flex');

            setTimeout(() => {
                document.getElementById('noshow-modal-content').classList.remove('opacity-0', 'scale-95');
            }, 10);
        }

        /**
         * Fecha o modal de registro de falta.
         */
        function closeNoShowModal() {
            document.getElementById('noshow-modal-content').classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                document.getElementById('noshow-modal').classList.remove('flex');
                document.getElementById('noshow-modal').classList.add('hidden');
            }, 300);
        }

        /**
         * Listener para Registro de Falta (No Show)
         */
        document.getElementById('confirm-noshow-btn').addEventListener('click', function() {
            if (currentReservaId) {
                // Ao registrar falta, enviamos o status 'noshow'
                sendAjaxRequest(currentReservaId, 'POST', REGISTER_NOSHOW_URL, null, {
                    status: 'no_show'
                });
            } else {
                alert("Erro: Dados da reserva para registrar falta não configurados corretamente.");
            }
        });

        // --- LÓGICA DE REATIVAÇÃO ---

        /**
         * Abre o modal de reativação.
         */
        function openReactivationModal(reservaId, title, message, urlBase) {
            currentReservaId = reservaId;
            currentMethod = 'PATCH'; // A reativação é sempre um PATCH no status
            currentUrlBase = urlBase;

            document.getElementById('reactivation-modal-title').textContent = title;
            document.getElementById('reactivation-modal-message').textContent = message;

            document.getElementById('reactivation-modal').classList.remove('hidden');
            document.getElementById('reactivation-modal').classList.add('flex');

            setTimeout(() => {
                document.getElementById('reactivation-modal-content').classList.remove('opacity-0', 'scale-95');
            }, 10);
        }

        /**
         * Fecha o modal de reativação.
         */
        function closeReactivationModal() {
            document.getElementById('reactivation-modal-content').classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                document.getElementById('reactivation-modal').classList.remove('flex');
                document.getElementById('reactivation-modal').classList.add('hidden');
            }, 300);
        }

        /**
         * Listener para Reativação
         */
        document.getElementById('confirm-reactivation-btn').addEventListener('click', function() {
            if (currentReservaId && currentUrlBase) {
                // Ao reativar, enviamos o status 'confirmed' e NÃO precisamos de justificativa.
                sendAjaxRequest(currentReservaId, 'PATCH', currentUrlBase, null, {
                    status: 'confirmed'
                });
            } else {
                alert("Erro: Dados da reserva para reativação não configurados corretamente.");
            }
        });


        // --- LÓGICA DE ALTERAÇÃO DE PREÇO ---

        // Variável global para controle do estado de recorrência
        let isCurrentReservaRecurrent = false;

        /**
         * Abre o modal de alteração de preço com suporte a recorrência.
         */
        function openPriceUpdateModal(reservaId, currentPrice, targetName, isRecurrent) {
            currentReservaId = reservaId;
            currentMethod = 'PATCH';
            currentUrlBase = UPDATE_PRICE_URL;
            isCurrentReservaRecurrent = isRecurrent; // Armazena o estado

            // Limpa e popula dados básicos
            document.getElementById('price-update-target-name').textContent = targetName;
            document.getElementById('current-price-display').textContent =
                `R$ ${parseFloat(currentPrice).toFixed(2).replace('.', ',')}`;
            document.getElementById('new-price-input').value = parseFloat(currentPrice).toFixed(2);
            document.getElementById('price-justification-input').value = '';
            document.getElementById('price-justification-error').classList.add('hidden');

            // Lógica para mostrar/esconder opções de recorrência
            const scopeContainer = document.getElementById('price-scope-container');
            if (scopeContainer) {
                if (isRecurrent) {
                    scopeContainer.classList.remove('hidden');
                } else {
                    scopeContainer.classList.add('hidden');
                }
            }

            // Exibe o modal
            document.getElementById('price-update-modal').classList.remove('hidden');
            document.getElementById('price-update-modal').classList.add('flex');

            setTimeout(() => {
                document.getElementById('price-update-modal-content').classList.remove('opacity-0', 'scale-95');
                document.getElementById('new-price-input').focus();
            }, 10);
        }

        /**
         * Fecha o modal de alteração de preço.
         */
        function closePriceUpdateModal() {
            document.getElementById('price-update-modal-content').classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                document.getElementById('price-update-modal').classList.remove('flex');
                document.getElementById('price-update-modal').classList.add('hidden');
            }, 300);
        }

        /**
         * Listener para Confirmação da Alteração de Preço (Versão com Escopo)
         */
        document.getElementById('confirm-price-update-btn').addEventListener('click', function() {
            const newPrice = parseFloat(document.getElementById('new-price-input').value);
            const justification = document.getElementById('price-justification-input').value.trim();
            const justificationError = document.getElementById('price-justification-error');

            // 1. Validação de Preço
            if (isNaN(newPrice) || newPrice < 0) {
                alert("Por favor, insira um preço válido (número maior ou igual a zero).");
                document.getElementById('new-price-input').focus();
                return;
            }

            // 2. Validação de Justificativa
            if (justification.length < 5) {
                justificationError.textContent =
                    'Por favor, forneça um motivo de alteração com pelo menos 5 caracteres.';
                justificationError.classList.remove('hidden');
                document.getElementById('price-justification-input').focus();
                return;
            }
            justificationError.classList.add('hidden');

            // 3. CAPTURA O ESCOPO (O pulo do gato)
            // Se a reserva for recorrente, pegamos o valor do rádio. Se não for, enviamos 'single' por padrão.
            let scope = 'single';
            if (isCurrentReservaRecurrent) {
                const selectedRadio = document.querySelector('input[name="price_scope"]:checked');
                scope = selectedRadio ? selectedRadio.value : 'single';
            }

            if (currentReservaId) {
                // 4. Envia a requisição AJAX incluindo o campo 'scope'
                sendAjaxRequest(currentReservaId, 'PATCH', UPDATE_PRICE_URL, justification, {
                    new_price: newPrice,
                    scope: scope // O seu Controller Laravel vai usar isso para saber se faz o update em massa
                });
            } else {
                alert("Erro: Dados da reserva para alteração de preço não configurados corretamente.");
            }
        });


        // --- FUNÇÕES GERAIS ---

        /**
         * FUNÇÃO PARA ALTERNAR STATUS DE SLOT FIXO (Manutenção <-> Livre)
         * Com verificação de ocupação e redirecionamento.
         */
        async function handleFixedSlotToggle(id, targetAction, isOccupied = false, clientName = '') {
            // 1. Caso o gestor queira marcar MANUTENÇÃO em um horário que JÁ TEM CLIENTE
            if (targetAction === 'maintenance' && isOccupied) {
                const proceed = confirm(
                    `🚨 CONFLITO DETECTADO!\n\n` +
                    `O cliente "${clientName}" tem uma reserva ativa neste horário.\n\n` +
                    `Como a manutenção é um imprevisto da Arena, deseja ser redirecionado para os DETALHES desta reserva para aplicar a manutenção e tratar os valores pagos?`
                );

                if (proceed) {
                    // ✅ Ajustado para usar o caminho absoluto do seu grupo de rotas prefixado com /admin
                    window.location.href = `/admin/reservas/${id}/show`;
                    return;
                }
                return;
            }

            // 2. Lógica normal para slots vazios ou liberar manutenção
            const actionText = targetAction === 'confirmed' ? 'disponibilizar (Livre)' : 'marcar como Manutenção';

            if (!confirm(`Confirma a ação de ${actionText} o horário ID #${id}?`)) {
                return;
            }

            // Chama a função AJAX que você já tem no arquivo
            sendAjaxRequest(id, 'POST', UPDATE_SLOT_STATUS_URL, "Ajuste de disponibilidade de grade", {
                status: targetAction
            });
        }


        /**
         * FUNÇÃO AJAX GENÉRICA PARA ENVIAR REQUISIÇÕES (Unificada para Cancelamento, Reativação, Falta, Preço e Slots Fixos)
         */
        async function sendAjaxRequest(reservaId, method, urlBase, reason = null, extraData = {}) {
            const url = urlBase.replace(':id', reservaId);

            if (!CSRF_TOKEN) {
                alert("Erro de segurança: Token CSRF não encontrado.");
                return;
            }

            // Monta o body da requisição
            const bodyData = {
                cancellation_reason: reason,
                justification: reason, // Para o controller de preço
                _token: CSRF_TOKEN,
                ...extraData,
            };

            // Se o método for PATCH ou DELETE, Laravel exige o campo _method via POST
            if (['PATCH', 'DELETE'].includes(method)) {
                bodyData._method = method;
            }

            // Identifica o botão de submissão ativo para feedback visual
            const submitBtn = document.getElementById('confirm-cancellation-btn') ||
                document.getElementById('confirm-reactivation-btn') ||
                document.getElementById('confirm-noshow-btn') ||
                document.getElementById('confirm-price-update-btn');

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Processando...';
            }

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(bodyData)
                });

                let result = {};
                try {
                    result = await response.json();
                } catch (e) {
                    const errorText = await response.text();
                    console.error("Falha ao ler resposta do servidor:", errorText);
                    result = {
                        error: "Erro interno no servidor."
                    };
                }

                if (response.ok && result.success) {
                    alert(result.message || "Ação realizada com sucesso.");

                    // Fecha todos os modais possíveis
                    if (typeof closeCancellationModal === 'function') closeCancellationModal();
                    if (typeof closeReactivationModal === 'function') closeReactivationModal();
                    if (typeof closeNoShowModal === 'function') closeNoShowModal();
                    if (typeof closePriceUpdateModal === 'function') closePriceUpdateModal();

                    setTimeout(() => {
                        window.location.reload();
                    }, 50);

                }
                // ✅ TRATAMENTO ESPECIAL: Trava de Segurança do Caixa (Erros 400, 403 ou 500)
                else if (response.status === 500 || response.status === 403 || response.status === 400) {
                    const errorMsg = result.message || result.error || "Erro desconhecido";

                    if (errorMsg.includes('Bloqueio de Segurança')) {
                        alert("🛑 OPERAÇÃO NEGADA:\n\n" + errorMsg);
                    } else {
                        alert("Erro ao processar: " + errorMsg);
                    }
                }
                // Erros de validação (Campos obrigatórios ou regras do Laravel)
                else if (response.status === 422) {
                    const errorMsg = result.errors ? Object.values(result.errors).flat().join('\n') : result.message;
                    alert("⚠️ VALIDAÇÃO:\n" + errorMsg);

                    // Se for erro no modal de preço, mostra a mensagem específica
                    const priceError = document.getElementById('price-justification-error');
                    if (priceError && document.getElementById('price-update-modal').offsetParent !== null) {
                        priceError.textContent = errorMsg;
                        priceError.classList.remove('hidden');
                    }
                } else {
                    alert(result.error || result.message || `Erro status ${response.status}`);
                }

            } catch (error) {
                console.error('Erro de Rede:', error);
                alert("Erro de conexão. Verifique sua internet e tente novamente.");
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    // Restaura o texto original baseado no ID
                    if (submitBtn.id === 'confirm-cancellation-btn') submitBtn.textContent = 'Confirmar Cancelamento';
                    else if (submitBtn.id === 'confirm-reactivation-btn') submitBtn.textContent = 'Reativar Reserva';
                    else if (submitBtn.id === 'confirm-noshow-btn') submitBtn.textContent = 'Confirmar Falta (No Show)';
                    else if (submitBtn.id === 'confirm-price-update-btn') submitBtn.textContent = 'Confirmar Preço';
                }
            }
        }

        // --- Listener de Confirmação do Modal de Cancelamento (IGUALADO AO CONTROLLER) ---
        document.getElementById('confirm-cancellation-btn').addEventListener('click', function() {
            const reason = document.getElementById('cancellation-reason-input').value.trim();

            // Captura o estado do checkbox de estorno
            const estornoCheckbox = document.getElementById('estornar-credito-checkbox');

            // TRADUÇÃO PARA O CONTROLLER:
            // O seu PHP espera 'should_refund' (bool) e 'paid_amount_ref' (float)
            const shouldRefund = estornoCheckbox ? estornoCheckbox.checked : false;

            if (reason.length < 5) {
                alert("Por favor, forneça um motivo de cancelamento com pelo menos 5 caracteres.");
                return;
            }

            if (currentReservaId && currentMethod && currentUrlBase) {
                // Enviamos os nomes que o seu ReservaController@cancelarPontual já utiliza
                sendAjaxRequest(currentReservaId, currentMethod, currentUrlBase, reason, {
                    should_refund: shouldRefund, // Substitui 'estornar_credito'
                    paid_amount_ref: currentReservaPaidAmount // Passa o valor real para o estorno
                });
            } else {
                alert("Erro: Dados da reserva não configurados corretamente.");
            }
        });
    </script>
</x-app-layout>
