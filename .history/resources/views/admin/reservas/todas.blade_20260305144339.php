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
                                <tr class="odd:bg-white even:bg-gray-50 hover:bg-indigo-50 transition duration-150">

                                    {{-- 1. DATA/HORA --}}
                                    <td class="px-4 py-3 whitespace-nowrap min-w-[120px]">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ \Carbon\Carbon::parse($reserva->date)->format('d/m/y') }}
                                        </div>
                                        <div class="text-indigo-600 text-xs font-semibold">
                                            {{ \Carbon\Carbon::parse($reserva->start_time)->format('H:i') }} -
                                            {{ \Carbon\Carbon::parse($reserva->end_time)->format('H:i') }}
                                        </div>
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            @if ($reserva->is_recurrent)
                                                <span
                                                    class="text-[9px] font-bold text-indigo-700 bg-indigo-100 px-1 rounded uppercase">Recorrente</span>
                                            @else
                                                <span
                                                    class="text-[9px] font-bold text-blue-700 bg-blue-100 px-1 rounded uppercase">Pontual</span>
                                            @endif
                                            @if ($reserva->is_fixed)
                                                <span
                                                    class="text-[9px] font-bold text-gray-700 bg-gray-200 px-1 rounded uppercase">Fixo</span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- 2. CLIENTE/RESERVA --}}
                                    <td class="px-4 py-3 text-left">
                                        @if ($reserva->is_fixed)
                                            <div class="text-sm font-bold text-gray-700 uppercase tracking-tight">Slot
                                                Inventário</div>
                                            <div class="text-[10px] text-gray-400">Bloqueio de grade</div>
                                        @elseif ($reserva->user)
                                            <div class="text-sm font-semibold text-gray-900">
                                                {{ $reserva->user->name }}</div>
                                            <div class="text-[10px] text-green-600 font-bold uppercase">Cliente Web
                                            </div>
                                        @else
                                            <div class="text-sm font-bold text-indigo-700">
                                                {{ $reserva->client_name ?? 'Cliente Manual' }}</div>
                                            <div class="text-[10px] text-gray-500 font-medium">
                                                {{ $reserva->client_contact ?? 'Sem contato' }}</div>
                                        @endif
                                    </td>

                                    {{-- 3. ARENA --}}
                                    <td class="px-4 py-3 text-left">
                                        <span
                                            class="inline-flex items-center px-2 py-1 rounded-lg text-[10px] font-black bg-indigo-50 text-indigo-700 border border-indigo-100 uppercase whitespace-nowrap">
                                            {{ $reserva->arena->name ?? 'N/D' }}
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
                                            {{ $reserva->status_text }}
                                        </span>
                                    </td>

                                    {{-- 6. PAGAMENTO (COM LÓGICA DE ATRASO CORRIGIDA) --}}
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        @php
                                            $pStatus = $reserva->payment_status;
                                            $rStatus = $reserva->status;
                                            $badgeClass = '';
                                            $badgeText = '';

                                            if ($rStatus === 'no_show') {
                                                $badgeClass = 'bg-black text-white font-bold';
                                                $badgeText = 'FALTA (NÃO PAGO)';
                                            } elseif ($reserva->is_fixed) {
                                                $badgeClass = 'bg-gray-100 text-gray-400';
                                                $badgeText = 'N/A';
                                            } elseif ($pStatus === 'paid' || $pStatus === 'completed') {
                                                $badgeClass = 'bg-green-100 text-green-800';
                                                $badgeText = 'PAGO';
                                            } elseif ($pStatus === 'partial') {
                                                $badgeClass = 'bg-yellow-100 text-yellow-800';
                                                $badgeText = 'PARCIAL';
                                            } else {
                                                // Proteção contra erro de "Double Date": parseamos a data de forma segura
                                                try {
                                                    $baseDate = is_string($reserva->date)
                                                        ? explode(' ', $reserva->date)[0]
                                                        : $reserva->date->format('Y-m-d');
                                                    $reservaEndTime = \Carbon\Carbon::parse(
                                                        $baseDate . ' ' . $reserva->end_time,
                                                    );

                                                    if ($reservaEndTime->isPast()) {
                                                        $badgeClass = 'bg-red-700 text-white font-bold animate-pulse';
                                                        $badgeText = 'ATRASADO';
                                                    } else {
                                                        $badgeClass = 'bg-red-100 text-red-800';
                                                        $badgeText = 'AGUARDANDO';
                                                    }
                                                } catch (\Exception $e) {
                                                    $badgeClass = 'bg-red-100 text-red-800';
                                                    $badgeText = 'AGUARDANDO';
                                                }
                                            }
                                        @endphp
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $badgeClass }}">
                                            {{ $badgeText }}
                                        </span>
                                    </td>

                                    {{-- 7. CRIADA POR --}}
                                    <td class="px-4 py-3 text-left min-w-[120px]">
                                        @if ($reserva->manager)
                                            <span
                                                class="font-medium text-purple-700 bg-purple-50 px-2 py-0.5 text-[10px] rounded-full uppercase border border-purple-100">
                                                {{ \Illuminate\Support\Str::limit($reserva->manager->name, 12, '..') }}
                                            </span>
                                        @else
                                            <span
                                                class="text-gray-400 text-[10px] uppercase font-bold italic">Automático</span>
                                        @endif
                                    </td>


                                    {{-- 8. AÇÕES SIMPLIFICADAS (Apenas Inventário e Navegação) --}}
                                    <td class="px-4 py-3 text-sm font-medium min-w-[120px]">
                                        <div class="flex flex-col space-y-1.5">

                                            {{-- 🔍 BOTÃO DETALHES (Sempre visível para funções completas) --}}
                                            <a href="{{ route('admin.reservas.show', $reserva) }}"
                                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 text-[10px] font-bold rounded shadow uppercase text-center transition">
                                                Detalhes
                                            </a>

                                            {{-- 🛠️ LÓGICA DE MANUTENÇÃO (Bloqueio de Grade) --}}
                                            @if ($reserva->status === 'maintenance')
                                                {{-- Se já está em manutenção, o botão é para LIBERAR --}}
                                                <button onclick="handleFixedSlotToggle({{ $reserva->id }}, 'free')"
                                                    class="bg-emerald-500 hover:bg-emerald-600 text-white px-3 py-1 text-[10px] font-bold rounded shadow uppercase text-center transition">
                                                    Liberar Agenda
                                                </button>
                                            @else
                                                {{-- Se não está em manutenção, permite ATIVAR --}}
                                                @php
                                                    $isOccupied =
                                                        ($reserva->client_name || $reserva->user_id) &&
                                                        !$reserva->is_fixed;
                                                    $displayClient = addslashes(
                                                        $reserva->client_name ??
                                                            ($reserva->user->name ?? 'Cliente Externo'),
                                                    );
                                                @endphp
                                                <button
                                                    onclick="handleFixedSlotToggle({{ $reserva->id }}, 'maintenance', {{ $isOccupied ? 'true' : 'false' }},'{{ $displayClient }}')"
                                                    class="bg-pink-600 hover:bg-pink-700 text-white px-3 py-1 text-[10px] font-bold rounded shadow uppercase text-center transition">
                                                    Manutenção
                                                </button>
                                            @endif

                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8"
                                        class="px-6 py-10 whitespace-nowrap text-center text-sm text-gray-500 italic font-medium bg-gray-50">
                                        Nenhuma reserva ou slot encontrado para os filtros selecionados.
                                    </td>
                                </tr>
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

    {{-- MODAL DE CANCELAMENTO REFINADO COM OPÇÃO DE ESTORNO --}}
    <div id="cancellation-modal"
        class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden items-center justify-center z-50 transition-opacity duration-300">
        <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-lg p-8 m-4 transform transition-transform duration-300 scale-95 opacity-0"
            id="cancellation-modal-content" onclick="event.stopPropagation()">

            <div class="flex items-center gap-3 mb-4 border-b pb-4">
                <div class="bg-red-100 p-2 rounded-full">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                        </path>
                    </svg>
                </div>
                <h3 id="modal-title" class="text-xl font-black text-gray-900 uppercase tracking-tighter">Confirmação
                </h3>
            </div>

            <div class="bg-gray-50 rounded-2xl p-4 mb-4 border border-gray-100">
                <p id="modal-message" class="text-sm text-gray-600 font-medium leading-relaxed"></p>
            </div>

            {{-- 🔄 NOVO: Opção de Estorno de Crédito --}}
            <div id="estorno-container" class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-2xl hidden">
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" id="estornar-credito-checkbox"
                        class="rounded-lg text-red-600 focus:ring-red-500 h-5 w-5 border-gray-300 transition cursor-pointer">
                    <div class="flex flex-col">
                        <span class="text-[11px] font-black text-amber-900 uppercase tracking-tight">Estornar valores
                            pagos?</span>
                        <span class="text-[10px] text-amber-700 italic leading-tight">O saldo (parcial ou total) será
                            devolvido à carteira do cliente.</span>
                    </div>
                </label>
            </div>

            <div class="mb-6">
                <label for="cancellation-reason-input"
                    class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">
                    Motivo da Operação: <span class="text-red-500 font-bold">*</span>
                </label>
                <textarea id="cancellation-reason-input" rows="3"
                    class="w-full p-4 border-gray-200 rounded-2xl focus:ring-red-500 focus:border-red-500 bg-gray-50 text-sm"
                    placeholder="Descreva o motivo (mínimo 5 caracteres)..."></textarea>
            </div>

            <div class="flex gap-3">
                <button onclick="closeCancellationModal()" type="button"
                    class="flex-1 px-4 py-3 bg-gray-100 text-gray-500 font-black text-[10px] uppercase rounded-2xl hover:bg-gray-200 transition">Voltar</button>
                <button id="confirm-cancellation-btn" type="button"
                    class="flex-1 px-4 py-3 bg-red-600 text-white font-black text-[10px] uppercase rounded-2xl hover:bg-red-700 transition shadow-lg shadow-red-100">Confirmar</button>
            </div>
        </div>
    </div>


    {{-- 🆕 NOVO MODAL DE REGISTRO DE FALTA (NO SHOW) REFINADO --}}
    <div id="noshow-modal"
        class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden items-center justify-center z-50 transition-opacity duration-300">
        <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-lg p-8 m-4 transform transition-transform duration-300 scale-95 opacity-0"
            id="noshow-modal-content" onclick="event.stopPropagation()">

            <div class="flex items-center gap-3 mb-6 border-b pb-4">
                <div class="bg-black p-2 rounded-full">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636"></path>
                    </svg>
                </div>
                <h3 id="noshow-modal-title" class="text-xl font-black text-gray-900 uppercase tracking-tighter">
                    Registrar No-Show</h3>
            </div>

            <div class="bg-gray-50 rounded-2xl p-5 mb-6 border border-gray-100">
                <p id="noshow-modal-message" class="text-sm text-gray-700 font-bold leading-tight"></p>
                <p class="text-[11px] text-gray-500 mt-2 italic">Esta ação liberará o horário no sistema e aplicará as
                    regras de falta ao cliente.</p>
            </div>

            <div class="p-5 bg-amber-50 border border-amber-100 rounded-2xl mb-8">
                <div class="flex gap-3">
                    <span class="text-xl">🚨</span>
                    <p class="text-[11px] font-bold text-amber-800 uppercase leading-normal">
                        O status será alterado para <span class="text-black">NO SHOW</span>.
                        Isso resolve o alerta de <span class="text-red-600">ATRASADO</span> no painel financeiro.
                    </p>
                </div>
            </div>

            <div class="flex gap-3">
                <button onclick="closeNoShowModal()" type="button"
                    class="flex-1 px-4 py-4 bg-gray-100 text-gray-500 font-black text-[10px] uppercase rounded-2xl hover:bg-gray-200 transition">
                    Voltar
                </button>
                <button id="confirm-noshow-btn" type="button"
                    class="flex-1 px-4 py-4 bg-black text-white font-black text-[10px] uppercase rounded-2xl hover:bg-gray-800 transition shadow-lg shadow-gray-200">
                    Confirmar No-Show
                </button>
            </div>
        </div>
    </div>


    {{-- NOVO MODAL DE REATIVAÇÃO REFINADO --}}
    <div id="reactivation-modal"
        class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden items-center justify-center z-50 transition-opacity duration-300">
        <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-lg p-8 m-4 transform transition-transform duration-300 scale-95 opacity-0"
            id="reactivation-modal-content" onclick="event.stopPropagation()">

            <div class="flex items-center gap-3 mb-6 border-b pb-4">
                <div class="bg-emerald-100 p-2 rounded-full">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                        </path>
                    </svg>
                </div>
                <h3 id="reactivation-modal-title" class="text-xl font-black text-gray-900 uppercase tracking-tighter">
                    Reativar Reserva</h3>
            </div>

            <div class="bg-gray-50 rounded-2xl p-5 mb-6 border border-gray-100">
                <p id="reactivation-modal-message" class="text-sm text-gray-700 font-bold leading-tight"></p>
                <p class="text-[11px] text-gray-500 mt-2 italic">A reserva voltará a ocupar o slot na agenda e
                    aparecerá como confirmada.</p>
            </div>

            <div class="p-5 bg-emerald-50 border border-emerald-100 rounded-2xl mb-8">
                <div class="flex gap-3">
                    <span class="text-xl">✅</span>
                    <p class="text-[11px] font-bold text-emerald-800 uppercase leading-normal">
                        O status retornará para <span class="text-black">CONFIRMADO</span>.
                        Certifique-se de que a quadra ainda está disponível para este horário.
                    </p>
                </div>
            </div>

            <div class="flex gap-3">
                <button onclick="closeReactivationModal()" type="button"
                    class="flex-1 px-4 py-4 bg-gray-100 text-gray-500 font-black text-[10px] uppercase rounded-2xl hover:bg-gray-200 transition">
                    Voltar
                </button>
                <button id="confirm-reactivation-btn" type="button"
                    class="flex-1 px-4 py-4 bg-emerald-600 text-white font-black text-[10px] uppercase rounded-2xl hover:bg-emerald-700 transition shadow-lg shadow-emerald-100">
                    Reativar Agora
                </button>
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


    {{-- SCRIPTS DE AÇÃO AJAX SIMPLIFICADOS (Grade e Manutenção) --}}
<script>
    // Variáveis de Rota e Token
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    const CSRF_TOKEN = metaTag ? metaTag.getAttribute('content') : null;

    // Mantemos apenas a rota de status de grade (Manutenção)
    const UPDATE_SLOT_STATUS_URL = '{{ route('admin.config.update_status', ':id') }}';

    /**
     * FUNÇÃO PARA ALTERNAR STATUS DE SLOT FIXO (Manutenção <-> Livre)
     * Com verificação de ocupação e redirecionamento para segurança.
     */
    async function handleFixedSlotToggle(id, targetAction, isOccupied = false, clientName = '') {
        // 1. Caso o gestor/colaborador queira marcar MANUTENÇÃO em um horário que JÁ TEM CLIENTE
        if (targetAction === 'maintenance' && isOccupied) {
            const proceed = confirm(
                `🚨 CONFLITO DETECTADO!\n\n` +
                `O cliente "${clientName}" tem uma reserva ativa neste horário.\n\n` +
                `Para aplicar manutenção em horários ocupados, é necessário tratar o cancelamento e possíveis estornos.\n\n` +
                `Deseja ser redirecionado para os DETALHES desta reserva?`
            );

            if (proceed) {
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

        // Chama a função AJAX simplificada
        sendAjaxRequest(id, 'POST', UPDATE_SLOT_STATUS_URL, {
            status: targetAction
        });
    }

    /**
     * FUNÇÃO AJAX UNIFICADA
     */
    async function sendAjaxRequest(reservaId, method, urlBase, extraData = {}) {
        const url = urlBase.replace(':id', reservaId);

        if (!CSRF_TOKEN) {
            alert("Erro de segurança: Token CSRF não encontrado.");
            return;
        }

        const bodyData = {
            _token: CSRF_TOKEN,
            justification: "Ajuste de disponibilidade via painel de inventário",
            ...extraData,
        };

        // Adicionamos o spoofing de método se necessário pelo Laravel
        if (['PATCH', 'DELETE'].includes(method)) {
            bodyData._method = method;
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

            const result = await response.json();

            if (response.ok && result.success) {
                alert(result.message || "Ação realizada com sucesso.");
                window.location.reload();
            } else {
                alert("Erro ao processar: " + (result.message || "Erro desconhecido"));
            }

        } catch (error) {
            console.error('Erro de Rede:', error);
            alert("Erro de conexão ao tentar atualizar o status.");
        }
    }
</script>
</x-app-layout>
