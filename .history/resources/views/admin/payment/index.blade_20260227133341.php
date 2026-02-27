<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            💰 Gerenciamento de Caixa
        </h2>
    </x-slot>

    @php
        // Variável de controle para desabilitar ações se o caixa estiver fechado
        $isActionDisabled = isset($cashierStatus) && $cashierStatus === 'closed';
        // Para garantir que exista, caso não venha do Controller
        $totalReservasDia = $totalReservasDia ?? 0;
        $totalRecebidoDiaLiquido = $totalRecebidoDiaLiquido ?? 0;
        $totalAntecipadoReservasDia = $totalAntecipadoReservasDia ?? 0;
        $totalPending = $totalPending ?? 0;
        $totalExpected = $totalExpected ?? 0;
        $noShowCount = $noShowCount ?? 0;
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- 1. ESTRUTURA DE INDICADORES (KPIs) --}}
            <div class="space-y-4 mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 lg:gap-4">

                    {{-- CARD 1: SALDO LÍQUIDO (O que REALMENTE entrou) --}}
                    <div
                        class="bg-green-600 dark:bg-green-700 overflow-hidden shadow-lg rounded-xl p-4 flex flex-col justify-center border-b-4 border-green-900 transition-all hover:scale-105">
                        <div class="text-[10px] font-bold text-green-50 uppercase tracking-tighter flex items-center">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                    d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z">
                                </path>
                            </svg>
                            Saldo em Dinheiro {{ request('arena_id') ? 'da Arena' : 'Geral' }}
                        </div>
                        <div id="valor-liquido-total-real" class="mt-1 text-2xl font-black text-white truncate">
                            R$ {{ number_format($totalRecebidoDiaLiquido, 2, ',', '.') }}
                        </div>
                        <div class="text-[9px] text-green-100 mt-1 italic leading-tight font-medium">
                            Dinheiro físico no caixa (Sinais + Quitações).
                        </div>
                    </div>

                    {{-- CARD 2: REFORÇOS (Sem alterações) --}}
                    <div
                        class="bg-white dark:bg-gray-800 border border-emerald-200 dark:border-emerald-900 overflow-hidden shadow-md rounded-xl p-4 flex flex-col justify-center">
                        <div
                            class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-tighter flex items-center">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                    d="M12 4v16m8-8H4"></path>
                            </svg>
                            Reforços (Avulsos)
                        </div>
                        <div class="mt-1 text-2xl font-black text-emerald-700 dark:text-emerald-500">
                            R$
                            {{ number_format($financialTransactions->where('type', 'reforco')->sum('amount'), 2, ',', '.') }}
                        </div>
                        <div class="text-[9px] text-gray-500 mt-1 leading-tight">Troco inicial ou aportes.</div>
                    </div>

                    {{-- CARD 3: SANGRIA (Sem alterações) --}}
                    <div
                        class="bg-white dark:bg-gray-800 border border-red-200 dark:border-red-900 overflow-hidden shadow-md rounded-xl p-4 flex flex-col justify-center text-left">
                        <div
                            class="text-[10px] font-bold text-red-600 dark:text-red-400 uppercase tracking-tighter flex items-center">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M20 12H4">
                                </path>
                            </svg>
                            Sangrias / Saídas
                        </div>
                        <div class="mt-1 text-2xl font-black text-red-600 dark:text-red-500">
                            R$
                            {{ number_format(abs($financialTransactions->whereIn('type', ['sangria', 'refund', 'no_show_penalty'])->where('amount', '<', 0)->sum('amount')),2,',','.') }}
                        </div>
                        <div class="text-[9px] text-gray-500 mt-1 leading-tight font-medium">Pagamentos e retiradas.
                        </div>
                    </div>

                    {{-- CARD 4: VALORES PENDENTES (Com link para o Relatório Geral) --}}
                    <div
                        class="bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-800 overflow-hidden shadow-md rounded-xl p-4 flex flex-col justify-center text-left relative group">

                        {{-- Badge de Link (Aparece ao passar o mouse ou indica que é clicável) --}}
                        <a href="{{ route('admin.financeiro.relatorio_dividas') }}"
                            class="absolute top-2 right-2 text-amber-600 dark:text-amber-400 hover:scale-110 transition-transform"
                            title="Ver Relatório Detalhado">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14">
                                </path>
                            </svg>
                        </a>

                        <div
                            class="text-[10px] font-bold text-amber-700 dark:text-amber-500 uppercase tracking-tighter flex items-center">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Dívidas (A Receber)
                        </div>

                        <div class="mt-1 text-2xl font-black text-amber-700 dark:text-amber-400">
                            R$ {{ number_format($totalAuthorizedDebt, 2, ',', '.') }}
                        </div>

                        {{-- Botão de redirecionamento interno no card --}}
                        <a href="javascript:void(0)" onclick="acessarDividasComSenha()"
                            class="mt-2 text-center block w-full py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-black text-[9px] uppercase tracking-widest transition shadow-sm">
                            GERENCIAR PENDÊNCIAS
                        </a>
                    </div>

                    {{-- CARD 5: OPERACIONAL --}}
                    <div
                        class="bg-gray-50 dark:bg-gray-700/50 border border-gray-300 dark:border-gray-600 overflow-hidden shadow-md rounded-xl p-4 flex flex-col justify-center text-left">
                        <div class="text-[10px] font-bold text-gray-600 dark:text-gray-400 uppercase tracking-tighter">
                            📊 Jogos / Faltas</div>
                        <div class="mt-1 text-xl font-black text-gray-900 dark:text-white flex items-baseline gap-1">
                            {{-- Mostra o total de linhas exibidas na tabela atual --}}
                            <span>{{ $reservas->count() }}</span>
                            <span class="text-[10px] font-normal text-gray-500 uppercase">Lista</span>

                            <span class="mx-1 text-gray-300">|</span>

                            {{-- Mostra quantos desses agendamentos são de fato para a data selecionada --}}
                            <span class="text-blue-600">{{ $reservas->where('date', $selectedDate)->count() }}</span>
                            <span class="text-[10px] font-normal text-blue-500 uppercase">Jogos</span>

                            <span class="mx-1 text-gray-300">|</span>

                            {{-- Mantém a contagem de faltas --}}
                            <span class="text-red-600">{{ $noShowCount }}</span>
                            <span class="text-[10px] font-normal text-red-500 uppercase">Faltas</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. CONTROLE DE STATUS E DADOS (UNIFICADO) --}}
            <div class="space-y-4">
                {{-- 1. DADOS OCULTOS GLOBAIS (Sempre fora de condições para o JS nunca perder o ID) --}}
                <div id="js_global_data">
                    <input type="hidden" id="js_totalReservas" value="{{ $reservas->count() }}">
                    <input type="hidden" id="js_totalPending" value="{{ $totalPending }}">
                    <input type="hidden" id="js_arenaId" value="{{ request('arena_id') }}">
                    <input type="hidden" id="js_isFiltered" value="{{ request('arena_id') ? '1' : '0' }}">
                    <input type="hidden" id="js_cashierDate" value="{{ $selectedDate }}">
                    <input type="hidden" id="js_isActionDisabled" value="{{ $isActionDisabled ? '1' : '0' }}">

                    {{-- Dados para composição do Modal de Fechamento --}}
                    <input type="hidden" id="js_valorLiquidoArenaRaw" value="{{ $totalRecebidoDiaLiquido }}">
                    <input type="hidden" id="js_saldoFisicoGavetaRaw"
                        value="{{ $financialTransactions->where('payment_method', 'money')->sum('amount') }}">
                    <input type="hidden" id="js_saldoDigitalBancoRaw"
                        value="{{ $financialTransactions->where('payment_method', 'pix')->sum('amount') }}">
                </div>

                {{-- 2. INTERFACE VISUAL --}}
                <div
                    class="bg-gray-50 dark:bg-gray-700/50 overflow-hidden shadow-lg sm:rounded-lg p-5 border {{ $cashierStatus === 'closed' ? 'border-red-400 dark:border-red-600' : 'border-indigo-400 dark:border-indigo-600' }}">

                    {{-- Aviso de Filtro (Só aparece se estiver aberto e com arena) --}}
                    @if ($cashierStatus !== 'closed' && request('arena_id'))
                        <div
                            class="mb-3 p-2 bg-amber-100 text-amber-800 text-xs rounded-lg border border-amber-200 flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            <span>Nota: O fechamento considera apenas os valores da arena selecionada.</span>
                        </div>
                    @endif

                    <div class="flex flex-col sm:flex-row items-center justify-between">
                        {{-- Lado Esquerdo: Texto de Status --}}
                        <div
                            class="text-sm sm:text-base font-medium text-gray-700 dark:text-gray-300 mb-3 sm:mb-0 flex items-center">
                            <svg class="w-6 h-6 mr-2 {{ $cashierStatus === 'closed' ? 'text-red-600' : 'text-indigo-600' }}"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>

                            @if ($cashierStatus === 'closed')
                                <div class="flex flex-col">
                                    <span class="font-bold text-red-700 dark:text-red-300 uppercase leading-none">
                                        Caixa Fechado
                                    </span>
                                    <span class="text-[10px] text-gray-500 font-bold uppercase mt-1">
                                        Referente a: {{ \Carbon\Carbon::parse($selectedDate)->format('d/m/Y') }}
                                    </span>
                                </div>
                            @else
                                <div class="flex items-baseline">
                                    <span
                                        class="text-xs font-black text-gray-400 uppercase mr-2 tracking-widest">Status:</span>

                                    {{-- 🛑 A MUDANÇA ESTÁ AQUI: ID "cashStatus" para o JS poder manipular --}}
                                    <span id="cashStatus">
                                        @if (!request('arena_id'))
                                            <span class="font-bold text-amber-500 italic">Selecione uma
                                                unidade...</span>
                                        @elseif($totalPending > 0)
                                            <span class="font-bold text-red-500 animate-pulse">
                                                Aguardando Recebimentos (R$
                                                {{ number_format($totalPending, 2, ',', '.') }})
                                            </span>
                                        @else
                                            <span class="font-bold text-green-600 uppercase">
                                                ✅ Pronta para fechar
                                            </span>
                                        @endif
                                    </span>
                                </div>
                            @endif
                        </div>

                        {{-- Lado Direito: Botões --}}
                        <div class="w-full sm:w-auto">
                            @if ($cashierStatus === 'closed')
                                {{-- BOTÃO REABRIR: Só aparece se o caixa geral do dia estiver fechado --}}
                                <button type="button" onclick="openCash('{{ $selectedDate }}')"
                                    class="w-full sm:w-auto px-6 py-2.5 bg-red-600 text-white font-black rounded-lg shadow-lg hover:bg-red-700 transition duration-150 flex items-center justify-center uppercase tracking-widest text-[10px]">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z">
                                        </path>
                                    </svg>
                                    Reabrir Caixa Diário
                                </button>
                            @else
                                @if (request('arena_id'))
                                    {{-- BOTÃO DE FECHAR: Ele SEMPRE existirá se a arena for selecionada.
                 Quem decide se ele é clicável ou não é o JAVASCRIPT em tempo real. --}}
                                    <button id="openCloseCashModalBtn" onclick="openCloseCashModal()"
                                        class="w-full sm:w-auto px-6 py-2.5 bg-green-600 text-white font-black rounded-lg shadow-xl hover:bg-green-700 transition duration-150 transform hover:scale-105 uppercase tracking-widest text-[10px] disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-400">
                                        Encerrar Caixa:
                                        {{ $faturamentoPorArena->firstWhere('id', request('arena_id'))->name ?? '' }}
                                    </button>
                                @else
                                    {{-- BOTÃO SELECIONE: Se não filtrou arena, não faz nada --}}
                                    <button disabled
                                        class="w-full sm:w-auto px-6 py-2.5 bg-indigo-200 dark:bg-gray-700 text-indigo-400 dark:text-gray-500 font-black rounded-lg cursor-not-allowed text-[10px] uppercase tracking-widest border border-indigo-100 dark:border-gray-600">
                                        Selecione uma Arena
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>


            {{-- 3. LINHA DOS FILTROS (DATA E BUSCA) --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                {{-- BLOCO 1: FILTRO DE DATA --}}
                <div
                    class="bg-white dark:bg-gray-800 overflow-hidden shadow-lg sm:rounded-lg p-5 flex flex-col justify-between border border-gray-200 dark:border-gray-700">
                    <form method="GET" action="{{ route('admin.payment.index') }}">
                        {{-- Preserva a pesquisa e a arena ao mudar a data --}}
                        <input type="hidden" name="search" value="{{ request('search') }}">
                        @if (request('arena_id'))
                            <input type="hidden" name="arena_id" value="{{ request('arena_id') }}">
                        @endif

                        <div class="flex items-center justify-between">
                            <label for="date"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                    </path>
                                </svg>
                                Data do Caixa:
                            </label>

                            {{-- Link para limpar todos os filtros --}}
                            @if (request()->has('reserva_id') || request()->has('arena_id') || request()->has('search') || request()->has('filter'))
                                <a href="{{ route('admin.payment.index', ['date' => $selectedDate]) }}"
                                    class="text-xs text-red-500 hover:text-red-700 dark:text-red-400 font-medium"
                                    title="Limpar todos os filtros e ver visão geral">
                                    Limpar Filtros
                                </a>
                            @endif
                        </div>

                        <input type="date" name="date" id="date" value="{{ $selectedDate }}"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white mt-1 text-base"
                            onchange="this.form.submit()">
                    </form>
                </div>

                {{-- BLOCO 2: BARRA DE PESQUISA E LOCALIZADOR DE DEVEDORES --}}
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-lg sm:rounded-lg p-5">
                    <form method="GET" action="{{ route('admin.payment.index') }}">
                        {{-- Preserva a data e a arena ao pesquisar --}}
                        <input type="hidden" name="date" value="{{ $selectedDate }}">
                        @if (request('arena_id'))
                            <input type="hidden" name="arena_id" value="{{ request('arena_id') }}">
                        @endif

                        <div class="flex flex-col h-full justify-between">
                            <div class="flex items-center justify-between mb-1">
                                <label for="search"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Buscar Cliente (Nome ou WhatsApp):
                                </label>

                            </div>

                            <div class="flex items-end gap-3 mt-auto">
                                <input type="text" name="search" id="search" value="{{ request('search') }}"
                                    placeholder="Ex: Nome do cliente..."
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white">

                                <button type="submit"
                                    class="h-10 px-4 py-2 bg-indigo-600 text-white rounded-md shadow-sm hover:bg-indigo-700 transition duration-150 flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </button>

                                @if (request()->has('search') || request('filter') === 'debts')
                                    <a href="{{ route('admin.payment.index', ['date' => $selectedDate, 'arena_id' => request('arena_id')]) }}"
                                        class="h-10 px-2 py-1 flex items-center justify-center text-gray-500 hover:text-red-500 border border-gray-300 dark:border-gray-600 rounded-md bg-gray-50 dark:bg-gray-700 transition duration-150"
                                        title="Limpar filtros ativos">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- 4. NOVA MOVIMENTAÇÃO AVULSA --}}
            <div class="flex justify-end items-center mb-4">
                <button onclick="openTransactionModal()"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 transition ease-in-out duration-150 {{ $isActionDisabled ? 'opacity-50 cursor-not-allowed' : '' }}"
                    {{ $isActionDisabled ? 'disabled' : '' }}>
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Nova Movimentação (Sangria/Reforço)
                </button>
            </div>

            {{-- 5. FATURAMENTO SEGMENTADO POR ARENA (COM FILTRO CLICÁVEL) --}}
            <div class="space-y-3 mb-6">
                <div class="flex items-center justify-between">
                    <h3
                        class="text-[11px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 8.293A1 1 0 013 7.586V4z">
                            </path>
                        </svg>
                        Faturamento por Arena (Clique para filtrar)
                    </h3>

                    {{-- Botão para Resetar Filtro de Arena --}}
                    @if (request('arena_id'))
                        <a href="{{ route('admin.payment.index', ['date' => $selectedDate, 'search' => request('search')]) }}"
                            class="text-[10px] bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded-lg font-bold text-gray-600 dark:text-gray-300 hover:bg-red-500 hover:text-white transition uppercase tracking-tighter">
                            ✕ Limpar Filtro
                        </a>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    @forelse($faturamentoPorArena as $arena)
                        @php
                            $isActive = request('arena_id') == $arena->id;
                        @endphp

                        <a href="{{ route('admin.payment.index', ['date' => $selectedDate, 'arena_id' => $arena->id, 'search' => request('search')]) }}"
                            class="p-4 rounded-2xl shadow-sm border transition flex justify-between items-center
                {{ $isActive
                    ? 'bg-indigo-50 dark:bg-indigo-900/30 border-indigo-500 ring-2 ring-indigo-500 ring-opacity-50'
                    : 'bg-white dark:bg-gray-800 border-gray-100 dark:border-gray-700 hover:shadow-md hover:border-indigo-300' }}
                border-l-4 {{ $isActive ? 'border-l-indigo-600' : 'border-l-indigo-400' }}">

                            <div class="truncate">
                                <span
                                    class="block text-[10px] font-black {{ $isActive ? 'text-indigo-600' : 'text-gray-400' }} uppercase tracking-tighter truncate">
                                    {{ $arena->name }}
                                </span>
                                <span
                                    class="text-lg font-black {{ $isActive ? 'text-indigo-800 dark:text-indigo-300' : 'text-indigo-700 dark:text-indigo-400' }}">
                                    R$ {{ number_format($arena->total, 2, ',', '.') }}
                                </span>
                            </div>

                            <div class="{{ $isActive ? 'text-indigo-600' : 'text-indigo-500' }}">
                                @if ($isActive)
                                    {{-- Ícone de Selecionado --}}
                                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                @else
                                    {{-- Ícone de Filtro --}}
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                                        </path>
                                    </svg>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div
                            class="col-span-full bg-gray-50 dark:bg-gray-800/40 border border-dashed border-gray-200 dark:border-gray-700 rounded-2xl p-4 text-center">
                            <p class="text-xs text-gray-400 italic font-medium">Nenhum faturamento registrado para as
                                arenas nesta data.</p>
                        </div>
                    @endforelse
                </div>
            </div>


            {{-- 6. TABELA DE RESERVAS (CONTROLE DE FLUXO) --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4 flex items-center justify-between">
                        <div class="flex items-center">
                            @if (request()->has('reserva_id'))
                                <span class="text-indigo-500">Reserva Selecionada (ID:
                                    {{ request('reserva_id') }})</span>
                            @elseif(request()->has('arena_id'))
                                @php
                                    $arenaNome =
                                        $faturamentoPorArena->firstWhere('id', request('arena_id'))->name ??
                                        'Arena Selecionada';
                                @endphp
                                <span class="text-indigo-600 dark:text-indigo-400">Agendamentos:
                                    {{ $arenaNome }}</span>
                            @else
                                Agendamentos do Dia ({{ \Carbon\Carbon::parse($selectedDate)->format('d/m/Y') }})
                            @endif
                        </div>

                        @if (request()->has('reserva_id') || request()->has('arena_id'))
                            <a href="{{ route('admin.payment.index', ['date' => $selectedDate, 'search' => request('search')]) }}"
                                class="text-sm font-medium text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-300 flex items-center transition duration-150">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                Ver Visão Geral
                            </a>
                        @endif
                    </h3>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-1/12">
                                        Horário</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-3/12">
                                        Cliente</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-1/12">
                                        Status Pagto.</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-1/12">
                                        Tipo</th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-1/12">
                                        Valor Total</th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-1/12">
                                        Total Pago</th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-1/12">
                                        Restante</th>
                                    <th
                                        class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-2/12">
                                        Ações</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($reservas as $reserva)
                                    @php
                                        // 1. Definição do Preço Final
                                        $total = $reserva->final_price ?? $reserva->price;

                                        // 2. Quanto entrou no caixa especificamente NESTA DATA (Ajustado para paid_at)
                                        $pagoNoDia = $financialTransactions
                                            ->where('reserva_id', $reserva->id)
                                            ->filter(function ($t) use ($selectedDate) {
                                                return \Carbon\Carbon::parse($t->paid_at)->toDateString() ===
                                                    \Carbon\Carbon::parse($selectedDate)->toDateString();
                                            })
                                            ->sum('amount');

                                        // 3. Total acumulado histórico (Sinais + Quitações)
                                        $pagoGeral = (float) $reserva->total_paid;

                                        // 4. Saldo Devedor Real
                                        $restante = max(0, round($total - $pagoGeral, 2));
                                        $currentStatus = $reserva->payment_status;

                                        // 5. Lógica de Atraso
                                        $dataHoje = \Carbon\Carbon::today()->toDateString();
                                        $dataReserva = \Carbon\Carbon::parse($reserva->date)->toDateString();
                                        $eHoje = $dataReserva === $dataHoje;
                                        $isOverdue = false;

                                        if (
                                            in_array($reserva->status, ['confirmed', 'pending']) &&
                                            $currentStatus !== 'paid'
                                        ) {
                                            $onlyTime = \Carbon\Carbon::parse($reserva->end_time)->format('H:i:s');
                                            try {
                                                $reservaEndTime = \Carbon\Carbon::parse($dataReserva . ' ' . $onlyTime);
                                                if ($reservaEndTime->isPast()) {
                                                    $isOverdue = true;
                                                }
                                            } catch (\Exception $e) {
                                                $isOverdue = false;
                                            }
                                        }

                                        // 6. Definição Visual do Status
                                        if ($reserva->status === 'no_show') {
                                            $statusClass = 'bg-red-500 text-white';
                                            $statusLabel = 'FALTA';
                                        } elseif (in_array($reserva->status, ['canceled', 'rejected'])) {
                                            $statusClass = 'bg-gray-400 text-white';
                                            $statusLabel = 'CANCELADO';
                                        } elseif ($currentStatus === 'paid') {
                                            $statusClass =
                                                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
                                            $statusLabel = 'PAGO';
                                        } elseif ($reserva->status === 'completed' && $currentStatus !== 'paid') {
                                            $statusClass =
                                                'bg-amber-100 text-amber-800 border border-amber-300 animate-pulse';
                                            $statusLabel = 'DÍVIDA ATIVA';
                                        } elseif ($isOverdue) {
                                            $statusClass = 'bg-red-700 text-white animate-pulse';
                                            $statusLabel = 'ATRASADO';
                                        } elseif ($currentStatus === 'partial') {
                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                            $statusLabel = 'PARCIAL';
                                        } else {
                                            $statusClass = 'bg-gray-100 text-gray-800';
                                            $statusLabel = $pagoGeral > 0 ? 'SINAL' : 'PENDENTE';
                                        }

                                        $rowHighlight = $eHoje
                                            ? 'bg-blue-50/40 dark:bg-blue-900/10 border-l-4 border-blue-500'
                                            : 'hover:bg-gray-50 border-l-4 border-transparent';

                                        $canPay =
                                            $restante > 0.01 && !in_array($reserva->status, ['canceled', 'rejected']);
                                        $canBeNoShow = !in_array($reserva->status, [
                                            'no_show',
                                            'canceled',
                                            'rejected',
                                            //'completed',
                                        ]);
                                        $canBeDebt =
                                            $restante > 0 &&
                                            $reserva->status !== 'completed' &&
                                            !in_array($reserva->status, ['canceled', 'rejected', 'no_show']);
                                    @endphp

                                    <tr class="{{ $rowHighlight }} transition duration-150">
                                        {{-- Horário --}}
                                        <td
                                            class="px-4 py-4 whitespace-nowrap text-sm font-bold text-gray-700 dark:text-gray-300">
                                            {{ \Carbon\Carbon::parse($reserva->start_time)->format('H:i') }} -
                                            {{ \Carbon\Carbon::parse($reserva->end_time)->format('H:i') }}
                                        </td>

                                        {{-- Cliente --}}
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $reserva->client_name }}
                                                <span
                                                    class="text-[10px] text-gray-400 font-normal">#{{ $reserva->id }}</span>
                                            </div>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span
                                                    class="text-[9px] px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 font-black border border-indigo-100 uppercase">
                                                    🏟️ {{ $reserva->arena->name ?? 'N/A' }}
                                                </span>
                                            </div>
                                        </td>

                                        {{-- Status Pagto --}}
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span
                                                class="px-2 py-0.5 inline-flex text-[10px] leading-4 font-bold rounded-full {{ $statusClass }}">
                                                {{ $statusLabel }}
                                            </span>
                                        </td>

                                        {{-- Tipo --}}
                                        <td class="px-4 py-4 whitespace-nowrap text-xs">
                                            <span
                                                class="font-semibold {{ $reserva->is_recurrent ? 'text-fuchsia-600' : 'text-blue-600' }}">
                                                {{ $reserva->is_recurrent ? 'Recorrente' : 'Pontual' }}
                                            </span>
                                        </td>

                                        {{-- Valor Total --}}
                                        <td
                                            class="px-4 py-4 text-right text-sm font-bold text-gray-900 dark:text-white">
                                            R$ {{ number_format($total, 2, ',', '.') }}
                                        </td>

                                        {{-- Total Pago (DINÂMICO PELO DIA SELECIONADO) --}}
                                        <td class="px-4 py-4 text-right whitespace-nowrap">
                                            <div class="text-sm text-green-600 font-bold">
                                                R$ {{ number_format($pagoNoDia, 2, ',', '.') }}
                                            </div>
                                            @if ($pagoGeral > $pagoNoDia)
                                                <div class="text-[9px] text-gray-400 font-medium italic">
                                                    Total Pago: R$ {{ number_format($pagoGeral, 2, ',', '.') }}
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Restante (Visual de Quitação) --}}
                                        <td class="px-4 py-4 text-right text-sm font-bold">
                                            @if ($restante > 0)
                                                <span class="text-red-600">R$
                                                    {{ number_format($restante, 2, ',', '.') }}</span>
                                            @else
                                                <span class="text-green-500 flex items-center justify-end gap-1">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="3" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                    QUITADO
                                                </span>
                                            @endif
                                        </td>

                                        {{-- Ações --}}
                                        <td class="px-4 py-4 whitespace-nowrap text-center text-sm space-x-1">
                                            @if ($canPay)
                                                <button type="button"
                                                    onclick="openPaymentModal({{ $reserva->id }}, {{ (float) $total }}, {{ (float) $restante }}, {{ (float) $pagoGeral }}, '{{ addslashes($reserva->client_name) }}', {{ $reserva->is_recurrent ? 'true' : 'false' }})"
                                                    class="bg-green-600 hover:bg-green-700 text-white text-[10px] font-bold px-2 py-1 rounded transition {{ $isActionDisabled ? 'opacity-50 cursor-not-allowed' : '' }}"
                                                    {{ $isActionDisabled ? 'disabled' : '' }}>
                                                    $ BAIXAR
                                                </button>

                                                @if ($canBeDebt)
                                                    <button type="button"
                                                        onclick="openDebtModal({{ $reserva->id }}, '{{ addslashes($reserva->client_name) }}')"
                                                        class="bg-amber-500 hover:bg-amber-600 text-white text-[10px] font-bold px-2 py-1 rounded transition {{ $isActionDisabled ? 'opacity-50 cursor-not-allowed' : '' }}"
                                                        {{ $isActionDisabled ? 'disabled' : '' }}
                                                        title="O cliente vai pagar em outro dia">
                                                        🕒 P. DEPOIS
                                                    </button>
                                                @endif
                                            @endif

                                            @if ($canBeNoShow)
                                                <button type="button"
                                                    onclick="requisitarAutorizacao(() => openNoShowModal({{ $reserva->id }}, '{{ addslashes($reserva->client_name) }}', {{ (float) $pagoGeral }}))"
                                                    class="bg-red-600 hover:bg-red-700 text-white text-[10px] font-bold px-2 py-1 rounded transition {{ $isActionDisabled ? 'opacity-50 cursor-not-allowed' : '' }}"
                                                    {{ $isActionDisabled ? 'disabled' : '' }}>
                                                    X FALTA
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8"
                                            class="px-4 py-8 text-center text-gray-500 italic font-medium">
                                            Nenhum agendamento encontrado para esta data.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- 7. TABELA DE TRANSAÇÕES FINANCEIRAS (AUDITORIA DE CAIXA) --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3
                        class="text-lg font-semibold mb-4 border-b border-gray-200 dark:border-gray-700 pb-2 flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                </path>
                            </svg>
                            Movimentação {{ request('arena_id') ? 'da Arena' : 'Detalhada do Caixa' }}
                        </div>
                        <span
                            class="text-xs font-mono text-gray-400">{{ \Carbon\Carbon::parse($selectedDate)->format('d/m/Y') }}</span>
                    </h3>

                    {{-- DASHBOARD RÁPIDO: ENTRADAS VS SAÍDAS --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div
                            class="p-4 bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-800 rounded-xl flex justify-between items-center text-left">
                            <div>
                                <span
                                    class="block text-[10px] uppercase font-bold text-green-600 dark:text-green-400 tracking-widest">Total
                                    de Entradas</span>
                                <span class="text-2xl font-black text-green-700 dark:text-green-300">
                                    R$
                                    {{ number_format($financialTransactions->where('amount', '>', 0)->sum('amount'), 2, ',', '.') }}
                                </span>
                            </div>
                            <div class="bg-green-100 dark:bg-green-800/30 p-2 rounded-lg text-green-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4"></path>
                                </svg>
                            </div>
                        </div>
                        <div
                            class="p-4 bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 rounded-xl flex justify-between items-center text-left">
                            <div>
                                <span
                                    class="block text-[10px] uppercase font-bold text-red-600 dark:text-red-400 tracking-widest">Saídas
                                    / Estornos</span>
                                <span class="text-2xl font-black text-red-700 dark:text-red-300">
                                    R$
                                    {{ number_format(abs($financialTransactions->where('amount', '<', 0)->sum('amount')), 2, ',', '.') }}
                                </span>
                            </div>
                            <div class="bg-red-100 dark:bg-red-800/30 p-2 rounded-lg text-red-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M20 12H4"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    @php
                        $groupedTransactions = $financialTransactions->groupBy('reserva_id');

                        // DICIONÁRIO DE TRADUÇÃO PARA DADOS DO BANCO (Atualizado para evitar termos em inglês)
                        $traducao = [
                            // Tipos de Movimentação
                            'no_show_penalty' => 'Multa de Falta',
                            'retained_funds' => 'Valor Retido',
                            'payment' => 'Pagamento',
                            'partial_payment' => 'Pagt. Parcial',
                            'signal' => 'Sinal/Entrada',
                            'refund' => 'Estorno',
                            'reforco' => 'Reforço',
                            'sangria' => 'Sangria',
                            'cash_out' => 'Saída (Estorno)',

                            // Meios de Pagamento (Tratando todas as variações do Controller/Banco)
                            'pix' => 'PIX',
                            'money' => 'Dinheiro',
                            'cash' => 'Dinheiro',
                            'dinheiro' => 'Dinheiro',
                            'credit_card' => 'Cartão de Crédito',
                            'debit_card' => 'Cartão de Débito',
                            'card' => 'Cartão',
                            'cartao' => 'Cartão',
                            'transfer' => 'Transf.',
                            'transferencia' => 'Transferência',
                            'other' => 'Outro',
                            'outro' => 'Outro',
                        ];
                    @endphp

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Hora</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">ID</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase text-left">
                                        Pagador / Gestor</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase text-left">
                                        Tipo | Forma</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase text-left">
                                        Descrição</th>
                                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Valor
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($groupedTransactions as $reservaId => $transactions)
                                    @php
                                        $transactionExample = $transactions->first();
                                        $reserva = $transactionExample->reserva;
                                        $clientName =
                                            $reserva->client_name ?? ($transactionExample->payer->name ?? 'N/D');
                                        $arenaTag = $reserva->arena->name ?? '';
                                        $dataDoJogo = $reserva
                                            ? \Carbon\Carbon::parse($reserva->date)->format('d/m')
                                            : null;
                                        $ehPagamentoAntecipado = $reserva && $reserva->date != $selectedDate;
                                    @endphp

                                    @if ($reservaId)
                                        <tr
                                            class="bg-gray-100 dark:bg-gray-700/60 border-t-2 border-indigo-500 text-left">
                                            <td colspan="5"
                                                class="px-4 py-2.5 text-sm font-bold text-gray-800 dark:text-gray-100">
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-extrabold bg-indigo-600 text-white mr-3 uppercase text-left">Reserva</span>
                                                ID: {{ $reservaId }} — {{ $clientName }}

                                                @if ($ehPagamentoAntecipado)
                                                    <span
                                                        class="ml-3 bg-fuchsia-600 text-white px-2 py-0.5 rounded text-[11px] font-black">📅
                                                        JOGO: {{ $dataDoJogo }}</span>
                                                @endif

                                                @if ($reserva)
                                                    <span
                                                        class="ml-2 bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded text-[11px] font-black border border-indigo-200">
                                                        ⏰
                                                        {{ \Carbon\Carbon::parse($reserva->start_time)->format('H:i') }}
                                                        às
                                                        {{ \Carbon\Carbon::parse($reserva->end_time)->format('H:i') }}
                                                    </span>
                                                @endif

                                                @if ($arenaTag)
                                                    <span
                                                        class="ml-2 text-[9px] text-indigo-400 uppercase font-black tracking-widest">[{{ $arenaTag }}]</span>
                                                @endif
                                            </td>
                                            <td
                                                class="px-4 py-2.5 text-right text-sm font-black text-gray-900 dark:text-white bg-indigo-50/50 dark:bg-indigo-900/20 border-l border-indigo-100 dark:border-indigo-800/50">
                                                R$ {{ number_format($transactions->sum('amount'), 2, ',', '.') }}
                                            </td>
                                        </tr>
                                    @endif

                                    @foreach ($transactions as $transaction)
                                        @php
                                            $amount = (float) $transaction->amount;
                                            $isRefund = $transaction->type === 'refund' || $amount < 0;
                                            $rowClass = $isRefund
                                                ? 'bg-red-50/50 dark:bg-red-900/10 border-l-4 border-red-500'
                                                : 'hover:bg-gray-50 dark:hover:bg-gray-700 border-l-4 border-transparent';
                                            $amountClass =
                                                $amount >= 0 ? 'text-green-600 font-bold' : 'text-red-600 font-black';
                                        @endphp
                                        <tr class="{{ $rowClass }} transition duration-150">
                                            <td class="px-4 py-3 text-sm text-gray-500 font-mono italic text-left">
                                                {{ \Carbon\Carbon::parse($transaction->paid_at)->format('H:i:s') }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-sm font-medium {{ $isRefund ? 'text-red-700' : 'text-indigo-600' }} text-left">
                                                #{{ $transaction->reserva_id ?? '--' }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-left">
                                                <div class="font-semibold text-gray-700 dark:text-gray-300">
                                                    {{ $transaction->payer->name ?? 'Caixa Geral' }}
                                                </div>
                                                <div class="text-[10px] text-gray-400 italic">Gestor:
                                                    {{ $transaction->manager->name ?? 'Sistema' }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-left">
                                                {{-- AQUI É FEITA A TRADUÇÃO DINÂMICA --}}
                                                <div class="text-[10px] font-extrabold uppercase text-gray-500">
                                                    {{ $traducao[strtolower($transaction->type)] ?? $transaction->type }}
                                                </div>
                                                <div
                                                    class="text-[9px] px-1 bg-gray-100 dark:bg-gray-700 w-fit rounded font-bold text-gray-600 dark:text-gray-400">
                                                    ({{ $traducao[strtolower($transaction->payment_method)] ?? $transaction->payment_method }})
                                                </div>
                                            </td>
                                            <td
                                                class="px-4 py-3 text-sm text-left leading-tight text-gray-600 dark:text-gray-400">
                                                {{ $transaction->description }}
                                            </td>
                                            <td class="px-4 py-3 text-right text-sm font-mono {{ $amountClass }}">
                                                {{ $amount < 0 ? '-' : '' }} R$
                                                {{ number_format(abs($amount), 2, ',', '.') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-12 text-center text-gray-500 italic">Nenhuma
                                            transação financeira registrada hoje.</td>
                                    </tr>
                                @endforelse

                                <tr class="bg-gray-100 dark:bg-gray-700 font-bold border-t-2 border-gray-300">
                                    <td colspan="5"
                                        class="px-4 py-4 text-right uppercase text-xs tracking-widest text-gray-600 dark:text-gray-300">
                                        Total Líquido do Caixa:</td>
                                    <td
                                        class="px-4 py-4 text-right text-lg {{ $totalRecebidoDiaLiquido >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                        R$ {{ number_format($totalRecebidoDiaLiquido, 2, ',', '.') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>


            {{-- 8. HISTÓRICO DE FECHAMENTOS (AUDITORIA DE DIVERGÊNCIAS POR ARENA) --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg mt-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3
                        class="text-lg font-semibold mb-4 border-b border-gray-200 dark:border-gray-700 pb-2 flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-2 text-fuchsia-500" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Histórico de Fechamentos
                        </div>
                        @if (request('arena_id'))
                            <span
                                class="text-[10px] bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-900/30 dark:text-fuchsia-400 px-2 py-1 rounded-md font-bold uppercase tracking-tighter">
                                Filtrado por Unidade
                            </span>
                        @endif
                    </h3>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Data</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Arena / Unidade</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Responsável</th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Esperado</th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Informado</th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Diferença</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($cashierHistory ?? [] as $caixa)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition duration-150">
                                        <td
                                            class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                            {{ \Carbon\Carbon::parse($caixa->date)->format('d/m/Y') }}
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span
                                                class="px-2 py-0.5 rounded bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400 font-bold text-[10px] uppercase border border-indigo-100 dark:border-indigo-800">
                                                {{ $caixa->arena->name ?? 'Geral' }}
                                            </span>
                                        </td>
                                        <td
                                            class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $caixa->user->name ?? 'Sistema' }}
                                        </td>
                                        <td class="px-4 py-4 text-right text-sm font-mono">
                                            R$ {{ number_format($caixa->calculated_amount, 2, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-4 text-right text-sm font-bold font-mono">
                                            R$ {{ number_format($caixa->actual_amount, 2, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-4 text-right text-sm font-bold font-mono">
                                            @if ($caixa->difference > 0)
                                                <span class="text-amber-600">+R$
                                                    {{ number_format($caixa->difference, 2, ',', '.') }} ⚠️</span>
                                            @elseif($caixa->difference < 0)
                                                <span class="text-red-600">-R$
                                                    {{ number_format(abs($caixa->difference), 2, ',', '.') }}
                                                    🚨</span>
                                            @else
                                                <span class="text-green-600">R$ 0,00 ✅</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-gray-500 italic">Nenhum
                                            histórico disponível.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>


            {{-- 9. LINK PARA RELATÓRIOS (NAVEGAÇÃO ESTRATÉGICA) --}}
            <div class="mt-8 pt-4 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                <a href="{{ route('admin.financeiro.dashboard') }}"
                    class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 transition duration-150 group">
                    <svg class="w-4 h-4 mr-1.5 group-hover:scale-110 transition-transform" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 8v8m-4-8v8m-4-8v8M4 16h16a2 2 0 002-2V8a2 2 0 00-2-2H4a2 2 0 00-2 2v6a2 2 0 002 2z">
                        </path>
                    </svg>
                    Ir para Relatórios
                </a>
            </div>

        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- MODAIS (MANTIDOS SEM ALTERAÇÃO NA ESTRUTURA) --}}
    {{-- ================================================================== --}}

    {{-- MODAL 1: FINALIZAR PAGAMENTO (BAIXA DE SALDO) --}}
    <div id="paymentModal" class="fixed inset-0 z-50 hidden overflow-y-auto flex items-center justify-center p-4"
        aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
            onclick="closePaymentModal()"></div>

        <div
            class="relative bg-white dark:bg-gray-800 rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full border dark:border-gray-700">
            <form id="paymentForm">
                @csrf
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div
                            class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 dark:bg-green-900/30 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z">
                                </path>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-xl leading-6 font-black text-gray-900 dark:text-white uppercase tracking-tight"
                                id="modal-title">
                                Baixar Pagamento
                            </h3>

                            {{-- RESUMO RÁPIDO DA RESERVA --}}
                            <div
                                class="mt-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600 space-y-1">
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    Cliente: <span id="modalClientName"
                                        class="font-bold text-gray-900 dark:text-white uppercase"></span>
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    Sinal já pago: <span id="modalSignalAmount"
                                        class="font-bold text-green-600 dark:text-green-400"></span>
                                </p>
                            </div>

                            {{-- CAMPOS OCULTOS DE CONTROLE --}}
                            <input type="hidden" id="modalReservaId" name="reserva_id">
                            <input type="hidden" id="modalSignalAmountRaw" name="signal_amount_raw">
                            {{-- CAMPO CHAVE: Garante que o pagamento caia na data certa do caixa --}}
                            <input type="hidden" id="modalPaymentDate" name="payment_date">

                            <div class="mt-4 space-y-4">
                                {{-- AJUSTE DE VALOR TOTAL --}}
                                <div>
                                    <label for="modalFinalPrice"
                                        class="block text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">
                                        Valor Total da Reserva (Final)
                                    </label>
                                    <div class="relative mt-1">
                                        <div
                                            class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm font-bold">R$</span>
                                        </div>
                                        <input type="number" step="0.01" id="modalFinalPrice" name="final_price"
                                            oninput="calculateAmountDue()" required
                                            class="pl-10 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white font-black text-xl">
                                    </div>
                                    <p class="text-[10px] text-gray-400 mt-1 italic">* Altere para aplicar descontos ou
                                        acréscimos finais.</p>
                                </div>

                                {{-- VALOR RECEBIDO AGORA --}}
                                <div
                                    class="p-4 bg-green-50 dark:bg-green-900/20 rounded-xl border border-green-100 dark:border-green-800">
                                    <label for="modalAmountPaid"
                                        class="block text-[10px] font-black text-green-700 dark:text-green-400 uppercase tracking-widest text-left">
                                        Valor Recebido Agora (Saldo)
                                    </label>
                                    <div class="relative mt-1">
                                        <div
                                            class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-green-600 font-bold">R$</span>
                                        </div>
                                        <input type="number" step="0.01" id="modalAmountPaid" name="amount_paid"
                                            oninput="checkManualOverpayment()" required
                                            class="pl-10 block w-full rounded-md border-green-300 dark:border-green-700 shadow-sm focus:border-green-500 focus:ring-green-500 dark:bg-gray-700 dark:text-green-400 font-black text-2xl">
                                    </div>
                                    <p id="trocoMessage"
                                        class="hidden mt-2 text-sm font-bold text-amber-600 dark:text-amber-400 animate-pulse">
                                        ⚠️ Troco: R$ 0,00
                                    </p>
                                </div>

                                {{-- FORMA DE PAGAMENTO --}}
                                <div>
                                    <label for="modalPaymentMethod"
                                        class="block text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">
                                        Forma de Pagamento
                                    </label>
                                    <select id="modalPaymentMethod" name="payment_method" required
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white font-bold">
                                        <option value="">Selecione...</option>
                                        <option value="pix">PIX</option>
                                        <option value="money">Dinheiro (Espécie)</option>
                                        <option value="credit_card">Cartão de Crédito</option>
                                        <option value="debit_card">Cartão de Débito</option>
                                        <option value="transfer">Transferência Bancária</option>
                                        <option value="other">Outro / Cortesia</option>
                                    </select>
                                </div>

                                {{-- OPÇÃO PARA RECORRÊNCIA --}}
                                <div id="recurrentOption"
                                    class="hidden pt-3 border-t border-gray-200 dark:border-gray-700">
                                    <label class="relative flex items-start cursor-pointer group">
                                        <input type="checkbox" id="apply_to_series" name="apply_to_series"
                                            value="1"
                                            class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 transition cursor-pointer">
                                        <span class="ml-3 text-left">
                                            <span
                                                class="block text-sm font-bold text-gray-700 dark:text-gray-200 uppercase tracking-tight">Atualizar
                                                série futura</span>
                                            <span class="block text-[11px] text-gray-500 dark:text-gray-400 italic">
                                                Aplicar este preço (R$ <span id="currentNewPrice"
                                                    class="font-bold"></span>) em todas as reservas futuras desta
                                                recorrência.
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <div id="payment-error-message"
                                class="hidden mt-4 p-3 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 text-xs font-bold rounded-lg border border-red-200 dark:border-red-800">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-900/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                    <button type="submit" id="submitPaymentBtn"
                        class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-6 py-2.5 bg-green-600 text-base font-black text-white hover:bg-green-700 focus:ring-2 focus:ring-green-500 sm:w-auto sm:text-sm transition duration-150">
                        <span id="submitPaymentText">CONCLUIR PAGAMENTO</span>
                        <svg id="submitPaymentSpinner" class="animate-spin ml-2 h-4 w-4 text-white hidden"
                            fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                    </button>
                    <button type="button" onclick="closePaymentModal()"
                        class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm px-6 py-2.5 bg-white dark:bg-gray-800 text-base font-bold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 sm:mt-0 sm:w-auto sm:text-sm transition duration-150">
                        CANCELAR
                    </button>
                </div>
            </form>
        </div>
    </div>


    {{-- MODAL 2: REGISTRAR FALTA (NO-SHOW) COM ESTORNO FLEXÍVEL --}}
    <div id="noShowModal" class="fixed inset-0 z-50 hidden overflow-y-auto flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
            onclick="closeNoShowModal()"></div>

        <div
            class="relative bg-white dark:bg-gray-800 rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full border dark:border-gray-700">
            {{-- 💡 O ID do Form deve ser 'noShowForm' para bater com o JS --}}
            <form id="noShowForm">
                @csrf
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div
                            class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/30 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L12 12M6 6l12 12">
                                </path>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3
                                class="text-lg leading-6 font-black text-gray-900 dark:text-white uppercase tracking-tight">
                                Registrar Falta (No-Show)
                            </h3>

                            <div
                                class="mt-2 p-3 bg-red-50 dark:bg-red-900/10 rounded-lg border border-red-100 dark:border-red-900/50">
                                <p class="text-sm text-red-800 dark:text-red-300 font-medium">
                                    Cliente: <span id="noShowClientName" class="font-black uppercase"></span>
                                </p>
                            </div>

                            {{-- CAMPOS OCULTOS --}}
                            <input type="hidden" id="noShowReservaId" name="reserva_id">
                            <input type="hidden" id="noShowPaidAmount" name="paid_amount">
                            <input type="hidden" id="noShowPaymentDate" name="payment_date">

                            <div id="refundControls"
                                class="mt-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-xl hidden border border-gray-200 dark:border-gray-600 space-y-4">
                                <div>
                                    <label for="should_refund"
                                        class="block text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-2">
                                        Dinheiro Recebido: <span id="noShowAmountDisplay"
                                            class="text-indigo-600 dark:text-indigo-400 font-bold"></span>
                                    </label>
                                    <select id="should_refund" name="should_refund"
                                        onchange="toggleCustomRefundInput()"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-800 dark:text-white font-bold">
                                        <option value="false">Reter valor total (Multa de No-Show)</option>
                                        <option value="true">Estornar / Devolver Valor ao Cliente</option>
                                    </select>
                                </div>

                                <div id="customRefundDiv" class="hidden">
                                    <label for="custom_refund_amount"
                                        class="block text-[10px] font-black text-red-600 dark:text-red-400 uppercase tracking-widest mb-1">
                                        Quanto deseja devolver agora?
                                    </label>
                                    <div class="relative">
                                        <div
                                            class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 font-bold">R$</span>
                                        </div>
                                        <input type="number" step="0.01" id="custom_refund_amount"
                                            name="refund_amount"
                                            class="pl-10 block w-full rounded-md border-red-300 dark:border-red-700 shadow-sm focus:border-red-500 focus:ring-red-500 dark:bg-gray-800 dark:text-red-400 font-black text-xl">
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <label for="no_show_reason"
                                    class="block text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">
                                    Motivo / Observação (Obrigatório)
                                </label>
                                <textarea id="no_show_reason" name="no_show_reason" rows="2" required
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-red-500 focus:ring-red-500 dark:bg-gray-700 dark:text-white text-sm"
                                    placeholder="Descreva o motivo da falta..."></textarea>
                            </div>

                            <div
                                class="mt-4 p-3 bg-red-50 dark:bg-red-900/20 rounded-lg border-2 border-dashed border-red-200 dark:border-red-800">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" id="block_user" name="block_user"
                                        class="h-5 w-5 rounded border-gray-300 text-red-600 focus:ring-red-500 transition cursor-pointer">
                                    <span
                                        class="ml-2 text-xs font-black text-red-700 dark:text-red-400 uppercase tracking-tighter">
                                        Bloquear cliente permanentemente
                                    </span>
                                </label>
                            </div>

                            <div id="noshow-error-message"
                                class="hidden mt-3 p-3 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 text-xs font-bold rounded-lg border border-red-200 dark:border-red-800">
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="bg-gray-50 dark:bg-gray-900/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2 text-left">
                    <button type="submit" id="submitNoShowBtn"
                        class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-6 py-2.5 bg-red-600 text-base font-black text-white hover:bg-red-700 focus:ring-2 focus:ring-red-500 sm:w-auto sm:text-sm transition duration-150">
                        <span id="submitNoShowText">CONFIRMAR FALTA</span>
                        <svg id="submitNoShowSpinner" class="animate-spin ml-2 h-4 w-4 text-white hidden"
                            fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                    </button>
                    <button type="button" onclick="closeNoShowModal()"
                        class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm px-6 py-2.5 bg-white dark:bg-gray-800 text-base font-bold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 sm:mt-0 sm:w-auto sm:text-sm transition duration-150">
                        VOLTAR
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL 3: FECHAR CAIXA (CLOSE CASH) - ATUALIZADO COM CARTÃO/OUTROS --}}
    <div id="closeCashModal" class="fixed inset-0 z-50 hidden overflow-y-auto flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
            onclick="closeCloseCashModal()"></div>

        <div
            class="relative bg-white dark:bg-gray-800 rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full border dark:border-gray-700">
            <form id="closeCashForm">
                @csrf
                <input type="hidden" id="closeCashDate" name="date">
                <input type="hidden" id="closeCashArenaId" name="arena_id" value="{{ request('arena_id') }}">

                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div
                            class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 dark:bg-indigo-900/30 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                </path>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3
                                class="text-lg leading-6 font-black text-gray-900 dark:text-white uppercase tracking-tight">
                                Fechamento de Unidade
                            </h3>

                            <div class="mt-1 flex items-center gap-2">
                                <span class="text-xs font-bold text-gray-500 uppercase">Arena:</span>
                                <span class="text-sm font-black text-indigo-600 dark:text-indigo-400 uppercase">
                                    {{ $faturamentoPorArena->firstWhere('id', request('arena_id'))->name ?? 'Não selecionada' }}
                                </span>
                            </div>

                            <div class="text-[10px] text-gray-400 font-bold uppercase mt-1">
                                Período: <span id="closeCashDateDisplay"></span>
                            </div>

                            {{-- 🚀 COMPOSIÇÃO DO SALDO (GAVETA VS BANCO VS OUTROS) --}}
                            <div class="mt-4 grid grid-cols-1 gap-2">
                                <div class="grid grid-cols-2 gap-2">
                                    <div
                                        class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-2 rounded-xl">
                                        <span
                                            class="block text-[9px] font-black text-amber-600 dark:text-amber-400 uppercase tracking-widest">Gaveta
                                            (Espécie)</span>
                                        <span id="displayGavetaModal"
                                            class="text-base font-black text-amber-700 dark:text-amber-300">R$
                                            0,00</span>
                                    </div>

                                    <div
                                        class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-2 rounded-xl">
                                        <span
                                            class="block text-[9px] font-black text-blue-600 dark:text-blue-400 uppercase tracking-widest">Banco
                                            (Digital)</span>
                                        <span id="displayBancoModal"
                                            class="text-base font-black text-blue-700 dark:text-blue-300">R$
                                            0,00</span>
                                    </div>
                                </div>

                                {{-- CARD DE CARTÃO / OUTROS --}}
                                <div
                                    class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 p-2 rounded-xl">
                                    <span
                                        class="block text-[9px] font-black text-orange-600 dark:text-orange-400 uppercase tracking-widest">Cartão
                                        / Outros Formas</span>
                                    <span id="displayOutrosModal"
                                        class="text-base font-black text-orange-700 dark:text-orange-300">R$
                                        0,00</span>
                                    <p class="text-[8px] text-orange-600/70 leading-tight">Crédito, Débito e outras
                                        conciliações.</p>
                                </div>
                            </div>

                            <div class="mt-4 space-y-4">
                                {{-- VALOR CALCULADO PELO SISTEMA (TOTAL) --}}
                                <div>
                                    <label
                                        class="block text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">
                                        Saldo Total Esperado (Soma Geral)
                                    </label>
                                    <div id="calculatedLiquidAmount"
                                        class="mt-1 block w-full bg-gray-50 dark:bg-gray-900 p-3 rounded-md font-black text-2xl text-indigo-600 dark:text-indigo-400 border border-gray-200 dark:border-gray-700 text-center">
                                        R$ 0,00
                                    </div>
                                </div>

                                {{-- VALOR INFORMADO PELO OPERADOR --}}
                                <div
                                    class="p-4 bg-indigo-50 dark:bg-indigo-900/10 rounded-xl border-2 border-indigo-100 dark:border-indigo-800">
                                    <label for="actualCashAmount"
                                        class="block text-[10px] font-black text-indigo-700 dark:text-indigo-400 uppercase tracking-widest mb-1 text-left">
                                        Valor Total Contado (Dinheiro + Digital)
                                    </label>
                                    <div class="relative">
                                        <div
                                            class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-indigo-500 font-bold">R$</span>
                                        </div>
                                        <input type="number" step="0.01" id="actualCashAmount"
                                            name="actual_amount" required
                                            class="pl-10 block w-full rounded-md border-indigo-300 dark:border-indigo-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white font-black text-2xl"
                                            placeholder="0,00">
                                    </div>
                                </div>

                                <div id="differenceMessage"
                                    class="hidden mt-3 p-3 text-sm font-bold rounded-lg text-center border transition-all duration-300">
                                </div>
                                <div id="closecash-error-message"
                                    class="hidden mt-3 p-3 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 text-xs font-bold rounded-lg border border-red-200 dark:border-red-800">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="bg-gray-50 dark:bg-gray-900/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2 border-t border-gray-100 dark:border-gray-700 text-left">
                    <button type="submit" id="submitCloseCashBtn"
                        class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-6 py-2.5 bg-indigo-600 text-base font-black text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 sm:w-auto sm:text-sm transition duration-150 uppercase tracking-wider">
                        Finalizar Caixa
                    </button>
                    <button type="button" onclick="closeCloseCashModal()"
                        class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm px-6 py-2.5 bg-white dark:bg-gray-800 text-base font-bold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 sm:mt-0 sm:w-auto sm:text-sm transition duration-150">
                        VOLTAR
                    </button>
                </div>
            </form>
        </div>
    </div>
    {{-- MODAL 4: ABRIR CAIXA (OPEN CASH) --}}
    <div id="openCashModal" class="fixed inset-0 z-50 hidden overflow-y-auto flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeOpenCashModal()"></div>

        <div
            class="relative bg-white dark:bg-gray-800 rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full border dark:border-gray-700">
            <form id="openCashForm">
                @csrf
                <input type="hidden" id="reopenCashDate" name="date">
                <input type="hidden" id="reopenCashArenaId" name="arena_id">

                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3
                                class="text-lg leading-6 font-black text-gray-900 dark:text-white uppercase tracking-tight">
                                Reabrir Caixa Diário
                            </h3>

                            <div
                                class="mt-2 p-3 bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-900/50 rounded-lg">
                                <p class="text-sm text-red-700 dark:text-red-400">
                                    {{-- ESTE ID ABAIXO É O QUE ESTAVA DANDO ERRO --}}
                                    O caixa do dia <span id="reopenCashDateDisplay"
                                        class="font-black underline"></span> está FECHADO.
                                </p>
                            </div>

                            <div class="mt-4">
                                <label for="reopen_reason"
                                    class="block text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1">
                                    Justificativa da Reabertura <span class="text-red-500">*</span>
                                </label>
                                <textarea id="reopen_reason" name="reason" rows="3" required
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-red-500 focus:ring-red-500 dark:bg-gray-700 dark:text-white text-sm"
                                    placeholder="Descreva o motivo da reabertura"></textarea>
                            </div>

                            <div id="openCash-error-message"
                                class="hidden mt-3 p-3 bg-red-100 text-red-700 text-xs font-bold rounded-lg"></div>
                        </div>
                    </div>
                </div>

                <div
                    class="bg-gray-50 dark:bg-gray-900/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2 border-t">
                    <button type="submit" id="submitOpenCashBtn"
                        class="bg-red-600 text-white px-6 py-2.5 rounded-lg font-black text-sm uppercase tracking-widest">
                        <span id="submitOpenCashText">Confirmar Reabertura</span>
                        <svg id="submitOpenCashSpinner" class="animate-spin ml-2 h-4 w-4 text-white hidden"
                            fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                    </button>
                    <button type="button" onclick="closeOpenCashModal()"
                        class="text-gray-700 dark:text-gray-300 font-bold text-sm uppercase">CANCELAR</button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL 5: JUSTIFICATIVA DE DÍVIDA (PAGAR DEPOIS) --}}
    <div id="debtModal" class="fixed inset-0 z-50 hidden overflow-y-auto flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeDebtModal()"></div>
        <div
            class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl transform transition-all sm:max-w-lg sm:w-full border dark:border-gray-700">
            <form id="debtForm">
                @csrf
                <input type="hidden" id="debtReservaId" name="reserva_id">

                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="bg-amber-100 dark:bg-amber-900/30 p-2 rounded-full mr-3">
                            <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-black text-gray-900 dark:text-white uppercase tracking-tight">
                            Autorizar Pagamento Posterior
                        </h3>
                    </div>

                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        O cliente <span id="debtClientName" class="font-bold text-indigo-600"></span> usufruiu da
                        quadra mas não pagou agora? Descreva o motivo abaixo:
                    </p>

                    <div>
                        <label
                            class="block text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1">
                            Motivo da Pendência
                        </label>
                        <textarea id="debtReason" name="reason" rows="3" required
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:ring-amber-500 focus:border-amber-500"
                            placeholder="Ex: Esqueceu a carteira / Problema no PIX / Autorizado pelo gestor"></textarea>
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-900/50 px-6 py-3 flex flex-row-reverse gap-2">
                    <button type="submit" id="submitDebtBtn"
                        class="bg-amber-600 text-white px-4 py-2 rounded-lg font-bold text-sm hover:bg-amber-700 transition flex items-center">
                        <span id="submitDebtText">CONFIRMAR DÍVIDA</span>
                        <svg id="submitDebtSpinner" class="animate-spin ml-2 h-4 w-4 text-white hidden"
                            fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                    </button>
                    <button type="button" onclick="closeDebtModal()"
                        class="text-gray-700 dark:text-gray-300 font-bold text-sm uppercase tracking-wider">
                        CANCELAR
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL 6: MOVIMENTAÇÃO AVULSA (SANGRIA/REFORÇO) --}}
    <div id="transactionModal" class="fixed inset-0 z-50 hidden overflow-y-auto flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeTransactionModal()">
        </div>
        <div
            class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl sm:max-w-lg sm:w-full border dark:border-gray-700">
            <form id="transactionForm">
                @csrf
                <input type="hidden" name="date" value="{{ $selectedDate }}">

                <div class="p-6">
                    <h3 class="text-lg font-black text-gray-900 dark:text-white uppercase mb-4">
                        Nova Movimentação Avulsa
                    </h3>

                    <div class="space-y-4">
                        {{-- Seleção de Arena --}}
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1">
                                Arena / Unidade
                            </label>
                            <select name="arena_id" id="modal_transaction_arena_id" required
                                class="w-full rounded-md border-gray-300 dark:bg-gray-700 dark:text-white font-bold focus:ring-indigo-500">
                                <option value="">SELECIONE A ARENA...</option>
                                @foreach ($arenasAtivas ?? ($faturamentoPorArena ?? []) as $arena)
                                    <option value="{{ $arena->id }}"
                                        {{ request('arena_id') == $arena->id ? 'selected' : '' }}>
                                        {{ $arena->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            {{-- Tipo de Movimentação --}}
                            <div>
                                <label
                                    class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1">Operação</label>
                                <select name="type" id="transaction_type" required
                                    class="w-full rounded-md border-gray-300 dark:bg-gray-700 dark:text-white font-bold text-sm">
                                    <option value="out">🔴 SAÍDA (Sangria)</option>
                                    <option value="in">🟢 ENTRADA (Reforço)</option>
                                </select>
                            </div>

                            {{-- Origem/Forma de Pagamento --}}
                            <div>
                                <label
                                    class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1">Origem
                                    do Recurso</label>
                                <select name="payment_method" id="transaction_payment_method" required
                                    class="w-full rounded-md border-gray-300 dark:bg-gray-700 dark:text-white font-bold text-sm">
                                    <option value="money">💵 DINHEIRO (GAVETA)</option>
                                    <option value="pix">📱 PIX (BANCO)</option>
                                    <option value="other">💳 CARTÃO / OUTRO</option>
                                </select>
                            </div>
                        </div>

                        {{-- Valor --}}
                        <div>
                            <label
                                class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1">Valor
                                da Operação</label>
                            <div class="relative">
                                <span
                                    class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 font-bold">R$</span>
                                <input type="number" step="0.01" name="amount" required placeholder="0,00"
                                    class="pl-10 w-full rounded-md border-gray-300 dark:bg-gray-700 dark:text-white font-black text-2xl">
                            </div>
                            <p id="transaction_helper_text"
                                class="text-[9px] font-bold text-gray-400 mt-1 uppercase italic">
                                * Esta operação afetará o saldo físico da gaveta.
                            </p>
                        </div>

                        {{-- Descrição --}}
                        <div>
                            <label
                                class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1">Descrição
                                / Motivo</label>
                            <textarea name="description" rows="2" required
                                class="w-full rounded-md border-gray-300 dark:bg-gray-700 dark:text-white text-sm"
                                placeholder="Ex: Compra de gás / Suprimento para troco"></textarea>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-900/50 px-6 py-3 flex flex-row-reverse gap-2">
                    <button type="submit" id="submitTransactionBtn"
                        class="bg-indigo-600 text-white px-4 py-2 rounded-lg font-bold text-sm hover:bg-indigo-700 transition uppercase">
                        Confirmar Movimentação
                    </button>
                    <button type="button" onclick="closeTransactionModal()"
                        class="text-gray-700 dark:text-gray-300 font-bold text-sm uppercase px-4">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- SCRIPT PARA MODAIS E LÓGICA DE CAIXA --}}


    <script>
        // Substitua as duas linhas antigas por esta:
        if (!window.__CAIXA_SCRIPT_LOADED) {
            window.__CAIXA_SCRIPT_LOADED = true;

            // ... todo o restante do seu código vem aqui dentro ...

            // --- Funções de Suporte e Formatação ---
            function toCents(value) {
                return Math.round(parseFloat(value || 0) * 100);
            }

            function fromCents(cents) {
                return (cents / 100).toFixed(2);
            }

            function updateRecurrentTogglePrice(newPrice) {
                const currentNewPriceEl = document.getElementById('currentNewPrice');
                if (currentNewPriceEl) {
                    const newPriceFloat = parseFloat(newPrice) || 0;
                    currentNewPriceEl.innerText = newPriceFloat.toLocaleString('pt-BR', {
                        minimumFractionDigits: 2
                    });
                }
            }

            // --- Controle de Estorno no No-Show ---
            function toggleCustomRefundInput() {
                const shouldRefund = document.getElementById('should_refund').value === 'true';
                const customDiv = document.getElementById('customRefundDiv');
                const paidAmount = parseFloat(document.getElementById('noShowPaidAmount').value) || 0;
                const inputRefund = document.getElementById('custom_refund_amount');

                if (shouldRefund) {
                    customDiv.classList.remove('hidden');
                    inputRefund.value = paidAmount.toFixed(2);
                } else {
                    customDiv.classList.add('hidden');
                    inputRefund.value = 0;
                }
            }

            // --- Lógica de Cálculo de Pagamento ---
            function calculateAmountDue() {
                const finalPriceEl = document.getElementById('modalFinalPrice');
                const signalRawEl = document.getElementById('modalSignalAmountRaw');
                const amountPaidEl = document.getElementById('modalAmountPaid');
                const trocoMessageEl = document.getElementById('trocoMessage');

                if (!finalPriceEl || !amountPaidEl) return;

                trocoMessageEl.classList.add('hidden');
                amountPaidEl.classList.remove('border-yellow-500', 'bg-yellow-50');

                const finalPriceCents = toCents(finalPriceEl.value);
                const signalAmountCents = toCents(signalRawEl.value);
                const balanceCents = finalPriceCents - signalAmountCents;

                updateRecurrentTogglePrice(fromCents(finalPriceCents));

                if (balanceCents < 0) {
                    const trocoCents = Math.abs(balanceCents);
                    amountPaidEl.value = "0.00";
                    trocoMessageEl.innerHTML =
                        `🚨 <strong>ATENÇÃO:</strong> Devolver Troco: R$ ${fromCents(trocoCents).replace('.', ',')}`;
                    trocoMessageEl.classList.remove('hidden');
                    amountPaidEl.classList.add('border-yellow-500', 'bg-yellow-50');
                } else {
                    // Se o saldo for maior que zero, coloca o valor, senão deixa 0.00
                    amountPaidEl.value = balanceCents > 0 ? fromCents(balanceCents) : "0.00";
                    checkManualOverpayment();
                }
            }

            function checkManualOverpayment() {
                const finalPriceEl = document.getElementById('modalFinalPrice');
                const signalRawEl = document.getElementById('modalSignalAmountRaw');
                const amountPaidEl = document.getElementById('modalAmountPaid');
                const trocoMessageEl = document.getElementById('trocoMessage');

                if (!finalPriceEl || !amountPaidEl) return;

                const finalPriceCents = toCents(finalPriceEl.value);
                const signalAmountCents = toCents(signalRawEl.value);
                const amountPaidNowCents = toCents(amountPaidEl.value);
                const overpaymentCents = (signalAmountCents + amountPaidNowCents) - finalPriceCents;

                if (overpaymentCents > 0) {
                    trocoMessageEl.innerHTML =
                        `🚨 <strong>ATENÇÃO:</strong> Devolver Troco: R$ ${fromCents(overpaymentCents).replace('.', ',')}`;
                    trocoMessageEl.classList.remove('hidden');
                    amountPaidEl.classList.add('border-yellow-500', 'bg-yellow-50');
                } else {
                    trocoMessageEl.classList.add('hidden');
                    amountPaidEl.classList.remove('border-yellow-500', 'bg-yellow-50');
                }
            }

            // --- Abertura de Modais ---

            // NOVO: Abrir Modal de Sangria/Reforço com Detecção de Arena
            function openTransactionModal() {
                // 1. Verifica se o caixa geral/arena está fechado
                if (document.getElementById('js_isActionDisabled')?.value === '1') {
                    alert('🚫 Operação bloqueada: O caixa deste dia já está fechado.');
                    return;
                }

                // 2. Captura a arena que está selecionada no filtro da URL
                const urlParams = new URLSearchParams(window.location.search);
                const filteredArenaId = urlParams.get('arena_id');
                const arenaSelect = document.getElementById('modal_transaction_arena_id');

                // 3. Se houver um filtro de arena ativo, pré-seleciona ela no Modal
                if (filteredArenaId && arenaSelect) {
                    arenaSelect.value = filteredArenaId;
                } else if (arenaSelect) {
                    // Se não houver filtro, reseta para a opção padrão "Selecione..."
                    arenaSelect.value = "";
                }

                // 4. Abre o modal trocando as classes do Tailwind
                document.getElementById('transactionModal').classList.replace('hidden', 'flex');
            }

            function openPaymentModal(id, totalPrice, remaining, signalAmount, clientName, isRecurrent = false) {
                // 1. Trava de segurança: impede abrir o modal se o caixa estiver fechado
                if (document.getElementById('js_isActionDisabled')?.value === '1') {
                    return alert('🚫 Operação bloqueada: O caixa deste dia já está fechado.');
                }

                // 2. Preenchimento de IDs e nomes
                document.getElementById('modalReservaId').value = id;
                document.getElementById('modalClientName').innerText = clientName;

                // 3. Formatação visual do sinal já pago
                document.getElementById('modalSignalAmount').innerText =
                    signalAmount.toLocaleString('pt-BR', {
                        style: 'currency',
                        currency: 'BRL'
                    });

                // 4. Valores brutos para cálculos
                document.getElementById('modalSignalAmountRaw').value = signalAmount.toFixed(2);
                document.getElementById('modalFinalPrice').value = totalPrice.toFixed(2);

                // 5. Captura da data operacional correta
                const inputDataFiltro = document.querySelector('input[name="date"]');
                const dataDoCaixa = inputDataFiltro ?
                    inputDataFiltro.value :
                    document.getElementById('js_cashierDate')?.value;

                if (dataDoCaixa) {
                    document.getElementById('modalPaymentDate').value = dataDoCaixa;
                    console.log("📅 Sistema operando na data:", dataDoCaixa);
                } else {
                    console.error("❌ Erro: Não foi possível capturar a data operacional.");
                }

                // 6. Controle da opção de recorrência
                const recurrentOption = document.getElementById('recurrentOption');
                if (recurrentOption) {
                    if (isRecurrent) {
                        recurrentOption.classList.remove('hidden');
                    } else {
                        recurrentOption.classList.add('hidden');
                    }
                }

                // 7. Recalcula saldo automaticamente
                calculateAmountDue();

                // 8. Exibe o modal
                const modal = document.getElementById('paymentModal');
                if (modal) {
                    modal.classList.replace('hidden', 'flex');
                }
            }

            function openNoShowModal(id, clientName, paidAmount) {
                // 1. Preenchimento básico dos dados no modal
                document.getElementById('noShowReservaId').value = id;
                document.getElementById('noShowClientName').innerText = clientName;
                document.getElementById('noShowPaidAmount').value = paidAmount;

                // 2. 🎯 CORREÇÃO DA DATA OPERACIONAL
                // Buscamos a data do filtro (calendário do topo) para garantir o registro no caixa correto
                const inputDataFiltro = document.querySelector('input[name="date"]');
                const dataDoCaixa = inputDataFiltro ? inputDataFiltro.value : document.getElementById('js_cashierDate')
                    ?.value;

                if (dataDoCaixa) {
                    document.getElementById('noShowPaymentDate').value = dataDoCaixa;
                    console.log("📅 No-Show sendo registrado na data:", dataDoCaixa);
                } else {
                    console.error("❌ Erro: Data operacional não encontrada para o No-Show.");
                }

                // 3. Exibição do valor pago para conferência de estorno
                document.getElementById('noShowAmountDisplay').innerText = paidAmount.toLocaleString('pt-BR', {
                    style: 'currency',
                    currency: 'BRL'
                });

                // 4. Controle visual: Se não houve pagamento, não precisa mostrar opções de estorno
                const refundControls = document.getElementById('refundControls');
                if (refundControls) {
                    paidAmount > 0 ? refundControls.classList.remove('hidden') : refundControls.classList.add('hidden');
                }

                // 5. Abre o modal
                const modal = document.getElementById('noShowModal');
                if (modal) {
                    modal.classList.replace('hidden', 'flex');
                }
            }

            function openDebtModal(id, clientName) {
                // 1. Trava de segurança (já está ok)
                if (document.getElementById('js_isActionDisabled')?.value === '1') return alert('🚫 Caixa Fechado.');

                // 2. BUSCA O INPUT (Garanta que o ID do elemento no HTML é exatamente este)
                const inputId = document.getElementById('debtReservaId');

                if (inputId) {
                    inputId.value = id; // Injeta o ID real (ex: 450)
                    console.log("✅ Modal de Dívida aberto para ID:", id); // Log para seu debug
                } else {
                    console.error("❌ Erro: O input 'debtReservaId' não foi encontrado no HTML.");
                }

                // 3. Atualiza o texto e exibe
                document.getElementById('debtClientName').innerText = clientName;
                document.getElementById('debtModal').classList.replace('hidden', 'flex');
            }

            function openCloseCashModal() {
                try {
                    // 1. Captura de elementos e valores com fallback para "0"
                    const dateEl = document.getElementById('js_cashierDate');
                    const systemValueEl = document.getElementById('js_valorLiquidoArenaRaw'); // O TOTAL (1.780)
                    const saldoGavetaEl = document.getElementById('js_saldoFisicoGavetaRaw'); // DINHEIRO (90)
                    const saldoDigitalEl = document.getElementById('js_saldoDigitalBancoRaw'); // PIX (1.410)

                    const date = dateEl ? dateEl.value : '';

                    // Convertemos para float para poder fazer contas matemáticas
                    const totalGeral = parseFloat(systemValueEl?.value || 0);
                    const totalDinheiro = parseFloat(saldoGavetaEl?.value || 0);
                    const totalPix = parseFloat(saldoDigitalEl?.value || 0);

                    // 🧠 A MÁGICA: O que não é dinheiro nem PIX, é Cartão/Outros
                    // No seu caso: 1780 - 90 - 1410 = 280
                    const totalOutros = totalGeral - (totalDinheiro + totalPix);

                    // 2. Helper de formatação
                    const formatarBRL = (val) => {
                        return parseFloat(val).toLocaleString('pt-br', {
                            style: 'currency',
                            currency: 'BRL'
                        });
                    };

                    // 3. Alimentação do Modal (Campos de exibição e Inputs)
                    if (document.getElementById('closeCashDate'))
                        document.getElementById('closeCashDate').value = date;

                    if (document.getElementById('closeCashDateDisplay'))
                        document.getElementById('closeCashDateDisplay').innerText = date.split('-').reverse().join('/');

                    // Exibe o Totalzão no topo
                    if (document.getElementById('calculatedLiquidAmount'))
                        document.getElementById('calculatedLiquidAmount').innerText = formatarBRL(totalGeral);

                    // Cards Detalhados: Preenche os 3 agora
                    const displayGaveta = document.getElementById('displayGavetaModal');
                    const displayBanco = document.getElementById('displayBancoModal');
                    const displayOutros = document.getElementById('displayOutrosModal'); // O novo ID que criamos

                    if (displayGaveta) displayGaveta.innerText = formatarBRL(totalDinheiro);
                    if (displayBanco) displayBanco.innerText = formatarBRL(totalPix);
                    if (displayOutros) displayOutros.innerText = formatarBRL(totalOutros);

                    // 4. Reset do campo de entrada para forçar nova conferência cega
                    const actualInput = document.getElementById('actualCashAmount');
                    if (actualInput) actualInput.value = '';

                    // 5. Troca de visibilidade do Modal
                    const modal = document.getElementById('closeCashModal');
                    if (modal) {
                        modal.classList.replace('hidden', 'flex');
                        // Foca automaticamente no campo de digitar o valor
                        setTimeout(() => actualInput?.focus(), 100);
                    }

                    // 6. Atualiza a mensagem de diferença
                    calculateDifference();

                } catch (error) {
                    console.error("Erro ao abrir o modal de fechamento:", error);
                }
            }

            function openCash(date) {
                // 1. Busca os elementos necessários (IDs sincronizados com o Modal 4)
                const arenaIdInput = document.getElementById('js_arenaId');
                const modalArenaInput = document.getElementById('reopenCashArenaId');
                const modalDateInput = document.getElementById('reopenCashDate');
                const modalDateDisplay = document.getElementById('reopenCashDateDisplay'); // Ajustado

                // 2. Validação
                if (!arenaIdInput || !arenaIdInput.value) {
                    alert('⚠️ Selecione uma arena antes de tentar reabrir o caixa.');
                    return;
                }

                // 3. Preenche os campos do Modal
                if (modalDateInput) modalDateInput.value = date;
                if (modalArenaInput) modalArenaInput.value = arenaIdInput.value;

                // 4. Preenche o texto visual com segurança
                if (modalDateDisplay) {
                    modalDateDisplay.innerText = date.split('-').reverse().join('/');
                }

                // 5. Limpa justificativa
                const reasonField = document.getElementById('reopen_reason');
                if (reasonField) reasonField.value = '';

                // 6. Exibe o modal
                const modal = document.getElementById('openCashModal');
                if (modal) {
                    modal.classList.replace('hidden', 'flex');
                }
            }

            // --- Fechamento de Modais ---

            // NOVO: Fechar Modal de Sangria/Reforço
            function closeTransactionModal() {
                document.getElementById('transactionModal').classList.replace('flex', 'hidden');
                document.getElementById('transactionForm').reset();
            }

            function closePaymentModal() {
                document.getElementById('paymentModal').classList.replace('flex', 'hidden');
            }

            function closeNoShowModal() {
                document.getElementById('noShowModal').classList.replace('flex', 'hidden');
            }

            function closeCloseCashModal() {
                document.getElementById('closeCashModal').classList.replace('flex', 'hidden');
            }

            function closeOpenCashModal() {
                document.getElementById('openCashModal').classList.replace('flex', 'hidden');
            }

            function closeDebtModal() {
                document.getElementById('debtModal').classList.replace('flex', 'hidden');
                document.getElementById('debtForm').reset();
            }

            // --- Lógica de Diferença de Caixa ---
            function calculateDifference() {
                // 🚀 AJUSTE: Buscamos o valor bruto (raw) do input oculto para evitar erros de formatação ou de arena
                const systemValueRaw = document.getElementById('js_valorLiquidoArenaRaw')?.value || "0";
                const actualInput = document.getElementById('actualCashAmount');
                const diffMessageEl = document.getElementById('differenceMessage');

                if (!actualInput || !diffMessageEl) return;

                // Calculamos em centavos para evitar erros de precisão do JavaScript
                const diffCents = toCents(actualInput.value) - toCents(systemValueRaw);

                // Reset de classes visual
                diffMessageEl.className =
                    'mt-3 p-3 text-sm font-bold rounded-lg text-center border transition-all duration-300';

                if (diffCents === 0) {
                    diffMessageEl.innerHTML = '✅ Caixa Perfeito!';
                    diffMessageEl.classList.add('bg-green-100', 'text-green-700', 'border-green-200');
                } else if (diffCents > 0) {
                    // Se informado > sistema = Sobra
                    diffMessageEl.innerHTML = `⚠️ Sobra no Físico: R$ ${fromCents(diffCents).replace('.', ',')}`;
                    diffMessageEl.classList.add('bg-amber-100', 'text-amber-700', 'border-amber-200');
                } else {
                    // Se informado < sistema = Falta
                    diffMessageEl.innerHTML = `🚨 Falta no Físico: R$ ${fromCents(Math.abs(diffCents)).replace('.', ',')}`;
                    diffMessageEl.classList.add('bg-red-100', 'text-red-700', 'border-red-200');
                }

                diffMessageEl.classList.remove('hidden');
            }

            function checkCashierStatus() {
                const btn = document.getElementById('openCloseCashModalBtn');
                const statusEl = document.getElementById('cashStatus');
                const isFiltered = document.getElementById('js_isFiltered')?.value === '1';

                if (!btn || !statusEl) return;

                // 1. Bloqueio por falta de filtro
                if (!isFiltered) {
                    btn.disabled = true;
                    statusEl.innerHTML = "👈 Selecione uma Arena";
                    statusEl.className = "text-amber-500 font-bold text-xs";
                    return;
                }

                // 2. BUSCA INTELIGENTE: Varre as linhas da tabela
                const pendenciasReais = Array.from(document.querySelectorAll('table tbody tr')).filter(tr => {
                    const txtLinha = tr.innerText.toUpperCase();

                    // 🛡️ REGRA DE OURO: Se o status visual já é um desses, NÃO é pendência.
                    // Isso ignora o Romulo (Dívida) e qualquer um que já pagou ou faltou.
                    if (txtLinha.includes('DÍVIDA ATIVA') ||
                        txtLinha.includes('PAGO') ||
                        txtLinha.includes('CANCELADO') ||
                        txtLinha.includes('FALTA') ||
                        txtLinha.includes('QUITADO')) {
                        return false;
                    }

                    // 3. Verificação de Horário (Opcional, mas profissional)
                    // Só travamos se o botão de ação existir E a linha não for de um jogo futuro
                    const botoes = tr.querySelectorAll('button:not([disabled]), a:not([disabled])');
                    let temBotaoAtivo = false;

                    botoes.forEach(el => {
                        const txtBotao = el.innerText.toUpperCase();
                        if (txtBotao.includes('BAIXAR') || txtBotao.includes('DEPOIS') || txtBotao.includes(
                                'FALTA')) {
                            temBotaoAtivo = true;
                        }
                    });

                    return temBotaoAtivo;
                });

                const pendenciasCount = pendenciasReais.length;

                if (pendenciasCount > 0) {
                    btn.disabled = true;
                    btn.classList.add('opacity-50', 'cursor-not-allowed');
                    statusEl.innerHTML = `🚨 PENDÊNCIAS: ${pendenciasCount} jogo(s) aberto(s)`;
                    statusEl.className = "text-red-600 font-black text-xs uppercase animate-pulse";
                } else {
                    btn.disabled = false;
                    btn.classList.remove('opacity-50', 'cursor-not-allowed');
                    statusEl.innerHTML = "✅ Arena pronta para fechar!";
                    statusEl.className = "text-green-600 font-black text-xs uppercase";
                }
            }

            // 1. Variável global atômica para controle de concorrência
            window.caixaProcessandoGlobal = window.caixaProcessandoGlobal || {};

            function setupAjaxForm(formId, btnId, spinnerId, errorId, urlTemplate) {
                const form = document.getElementById(formId);
                if (!form) return;

                if (form.dataset.ajaxBound === "1") return;
                form.dataset.ajaxBound = "1";

                const userRole = "{{ Auth::user()->role ?? 'guest' }}";

                form.onsubmit = function(e) {
                    e.preventDefault();

                    // 🛑 TRAVA 1: Bloqueio físico imediato no envio
                    if (window.caixaProcessandoGlobal[formId]) return false;
                    window.caixaProcessandoGlobal[formId] = true;

                    const enviarParaOServidor = (tokenRecebido = null) => {
                        const btn = document.getElementById(btnId);
                        const spinner = document.getElementById(spinnerId);

                        if (btn) {
                            btn.disabled = true;
                            btn.innerText = "AGUARDE...";
                        }
                        if (spinner) spinner.classList.remove('hidden');

                        const formData = new FormData(form);
                        if (tokenRecebido) formData.append('supervisor_token', tokenRecebido);

                        const reservaId = formData.get('reserva_id') || document.getElementById('noShowReservaId')
                            ?.value;
                        let targetUrl = urlTemplate.replace('{reserva}', reservaId).replace('{id}', reservaId);

                        fetch(targetUrl, {
                                method: 'POST',
                                body: formData,
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                        .getAttribute('content'),
                                    'Accept': 'application/json'
                                }
                            })
                            .then(res => res.json())
                            .then(json => {
                                // --- 🛡️ FILTRO DE ALERTA AGRESSIVO ---
                                const originalAlert = window.alert;

                                // Sobrescrevemos o alert APENAS para esta resposta
                                window.alert = function(msg) {
                                    const m = msg.toLowerCase();

                                    // Se já exibimos um sucesso ou é uma mensagem de duplicidade, CANCELA o alert
                                    if (form.dataset.finalizado === "true" ||
                                        m.includes("baixada anteriormente") ||
                                        m.includes("ja foi fechado") ||
                                        m.includes("duplicidade")) {
                                        console.log("Mensagem duplicada bloqueada com sucesso.");
                                        return; // O alert morre aqui, não aparece na tela
                                    }

                                    if (json.success) form.dataset.finalizado = "true";
                                    originalAlert(msg);
                                };

                                if (json.success) {
                                    alert(json.message);
                                    window.location.reload();
                                } else {
                                    alert(json.message || 'Erro ao processar.');

                                    // Só libera se não for sucesso, para permitir correção
                                    if (form.dataset.finalizado !== "true") {
                                        window.caixaProcessandoGlobal[formId] = false;
                                        if (btn) {
                                            btn.disabled = false;
                                            btn.innerText = "CONCLUIR";
                                        }
                                        if (spinner) spinner.classList.add('hidden');
                                    }
                                }

                                // Devolve o alert original ao sistema após 1.5s
                                setTimeout(() => {
                                    window.alert = originalAlert;
                                }, 1500);
                            })
                            .catch(err => {
                                console.error(err);
                                window.caixaProcessandoGlobal[formId] = false;
                                if (btn) btn.disabled = false;
                            });
                    };

                    const acoesCriticas = ['debtForm', 'noShowForm', 'transactionForm', 'openCashForm',
                        'closeCashForm'
                    ];
                    if (acoesCriticas.includes(formId) && userRole === 'colaborador') {
                        window.requisitarAutorizacao(token => {
                            if (token) enviarParaOServidor(token);
                            else window.caixaProcessandoGlobal[formId] = false;
                        });
                    } else {
                        enviarParaOServidor();
                    }
                    return false;
                };
            }

            document.addEventListener('DOMContentLoaded', () => {
                // 🧹 LIMPEZA TOTAL DE TRAVAS AO CARREGAR
                sessionStorage.clear();
                window.caixaProcessandoGlobal = {};

                console.log('🚀 Scripts de Caixa carregados e travas resetadas!');

                // 1. Inicialização de status e cálculos (Verifica se a arena pode fechar)
                if (typeof checkCashierStatus === "function") {
                    checkCashierStatus();
                }

                // 2. Ouvinte para cálculo de diferença em tempo real no Fechamento
                const actualCashInput = document.getElementById('actualCashAmount');
                if (actualCashInput) {
                    actualCashInput.oninput = function() {
                        if (typeof calculateDifference === "function") {
                            calculateDifference();
                        }
                    };
                }

                // 3. 🧠 INTELIGÊNCIA DE MOVIMENTAÇÃO (Sangria/Reforço)
                // Este trecho avisa visualmente se o dinheiro sai do PIX ou da GAVETA
                const paymentMethodSelect = document.getElementById('transaction_payment_method');
                if (paymentMethodSelect) {
                    paymentMethodSelect.addEventListener('change', function(e) {
                        const helper = document.getElementById('transaction_helper_text');
                        if (!helper) return;

                        if (e.target.value === 'money') {
                            helper.innerText = "* ESTA OPERAÇÃO AFETARÁ O SALDO FÍSICO DA GAVETA.";
                            helper.classList.remove('text-blue-500');
                            helper.classList.add('text-gray-400');
                        } else {
                            helper.innerText = "* ESTA OPERAÇÃO AFETARÁ O SALDO DIGITAL DO BANCO (PIX).";
                            helper.classList.remove('text-gray-400');
                            helper.classList.add('text-blue-500');
                        }
                    });
                }

                // 4. Registro dos formulários AJAX com blindagem anti-duplicidade
                try {
                    // --- ROTAS FIXAS ---
                    setupAjaxForm('transactionForm', 'submitTransactionBtn', null, null,
                        '/admin/pagamentos/movimentacao-avulsa');

                    setupAjaxForm('closeCashForm', 'submitCloseCashBtn', 'submitCloseCashSpinner',
                        'closecash-error-message', '/admin/pagamentos/fechar-caixa');

                    setupAjaxForm('openCashForm', 'submitOpenCashBtn', 'submitOpenCashSpinner',
                        'openCash-error-message', '/admin/pagamentos/abrir-caixa');

                    // --- ROTAS DINÂMICAS ({reserva}) ---

                    // Finalizar Pagamento
                    setupAjaxForm('paymentForm', 'submitPaymentBtn', 'submitPaymentSpinner',
                        'payment-error-message', '/admin/pagamentos/{reserva}/finalizar');

                    // Registrar No-Show (Falta)
                    setupAjaxForm('noShowForm', 'submitNoShowBtn', 'submitNoShowSpinner',
                        'noshow-error-message', '/admin/reservas/{reserva}/no-show');

                    // Pagar Depois (Dívida)
                    setupAjaxForm('debtForm', 'submitDebtBtn', null, null,
                        '/admin/pagamentos/{reserva}/pendenciar');

                    console.log('✅ Todos os formulários registrados com blindagem e seletores de origem!');
                } catch (e) {
                    console.error('❌ Erro crítico ao registrar formulários:', e);
                }
            });

            function acessarDividasComSenha() {
                const userRole = "{{ Auth::user()->role ?? 'guest' }}";
                // Usamos o nome da rota nova que o colaborador tem permissão de "atravessar"
                const urlDestino = "{{ route('admin.payment.dividas_acesso') }}";

                if (userRole === 'colaborador') {
                    window.requisitarAutorizacao(function(token) {
                        if (token) window.location.href = urlDestino;
                    });
                } else {
                    window.location.href = urlDestino;
                }
            }
        }
    </script>

</x-app-layout>
