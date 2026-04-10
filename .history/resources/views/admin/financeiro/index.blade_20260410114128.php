<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-800 dark:text-gray-200 leading-tight flex items-center gap-3">
            <span class="p-2 bg-indigo-100 dark:bg-indigo-900/50 rounded-lg shadow-sm">📊</span>
            Relatórios Financeiros
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            {{-- Filtro de Arena e Período Dinâmico --}}
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mb-6">
                <form action="{{ route('admin.financeiro.dashboard') }}" method="GET"
                    class="flex items-center gap-4 bg-white dark:bg-gray-800 p-4 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700">
                    {{-- Seletor de Unidade --}}
                    <div class="flex-1">
                        <label class="text-[10px] font-black uppercase text-gray-400 ml-2">Filtrar Unidade</label>
                        <select name="arena_id" onchange="this.form.submit()"
                            class="w-full border-none focus:ring-0 bg-transparent font-bold text-gray-700 dark:text-gray-200">
                            <option value="">Todas as Arenas (Consolidado)</option>
                            @foreach ($arenas as $arena)
                                <option value="{{ $arena->id }}"
                                    {{ request('arena_id') == $arena->id ? 'selected' : '' }}>
                                    {{ $arena->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="h-10 w-px bg-gray-200 dark:bg-gray-700"></div>

                    {{-- Seletor de Período (Mês/Ano) --}}
                    <div class="px-4 text-center">
                        <label class="text-[10px] font-black uppercase text-gray-400 block mb-1">Período de
                            Análise</label>
                        <input type="month" name="mes_referencia"
                            value="{{ request('mes_referencia', now()->format('Y-m')) }}" onchange="this.form.submit()"
                            class="border-none focus:ring-0 bg-transparent font-bold text-indigo-600 uppercase p-0 cursor-pointer text-sm text-center">
                    </div>
                </form>
            </div>

            {{-- 1. INDICADORES DE PERFORMANCE (KPIs) --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">

                {{-- Card Receita --}}
                <div
                    class="bg-gradient-to-br from-indigo-600 to-indigo-700 p-6 rounded-[2rem] shadow-lg text-white relative overflow-hidden group">
                    <p class="text-xs opacity-80 uppercase font-black tracking-widest">
                        {{ request('arena_id') ? 'Receita Unidade' : 'Receita Bruta Total' }}
                    </p>
                    <p class="text-2xl font-black mt-1 italic">R$ {{ number_format($faturamentoMensal, 2, ',', '.') }}
                    </p>

                    {{-- 🎁 Informativo de Cortesias --}}
                    <div class="mt-2 pt-2 border-t border-white/20">
                        <p class="text-[9px] opacity-70 uppercase font-bold">Cortesias (Vouchers): R$
                            {{ number_format($totalVouchersMes, 2, ',', '.') }}</p>
                    </div>
                </div>

                {{-- Card Ocupação --}}
                <div
                    class="bg-white dark:bg-gray-800 p-6 rounded-[2rem] shadow-sm border border-gray-100 dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-black tracking-widest">Ocupação
                    </p>
                    <p class="text-2xl font-black text-gray-800 dark:text-white mt-1">{{ $totalReservasMes }} <span
                            class="text-xs opacity-50">JOGOS</span></p>
                </div>

                {{-- Card Prejuízo/Faltas --}}
                <div
                    class="bg-white dark:bg-gray-800 p-6 rounded-[2rem] shadow-sm border border-red-50 dark:border-red-900/10">
                    <p class="text-xs text-red-400 uppercase font-black tracking-widest">Prejuízo (Faltas)</p>

                    {{-- ✅ AJUSTE AQUI: Mostrar apenas as Faltas (No-Show) reais --}}
                    <p class="text-2xl font-black text-red-500 mt-1">
                        {{ $countFaltasReais ?? $canceladasMes }}
                        <span class="text-xs opacity-50 uppercase">No-Show</span>
                    </p>
                </div>

                {{-- NOVO CARD: Inadimplência (Dívidas) --}}
                <div
                    class="bg-white dark:bg-gray-800 p-6 rounded-[2rem] shadow-sm border border-orange-50 dark:border-orange-900/10">
                    <p class="text-xs text-orange-500 uppercase font-black tracking-widest">Dívidas em Aberto</p>
                    <p class="text-2xl font-black text-orange-600 mt-1">R$
                        {{ number_format($totalGlobalDividas ?? 0, 2, ',', '.') }}</p>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <h3 class="text-sm font-black text-gray-400 uppercase tracking-[0.3em] italic">Módulos Analíticos</h3>
                <div class="flex-1 h-px bg-gray-200 dark:bg-gray-700"></div>
            </div>

            {{-- 2. GRID DE MENUS REFINADO --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                {{-- Faturamento --}}
                <a href="{{ route('admin.financeiro.relatorio_faturamento', ['arena_id' => request('arena_id')]) }}"
                    class="group flex items-center p-3 bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-transparent hover:border-indigo-500 hover:shadow-xl transition-all duration-300">
                    <div
                        class="p-5 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 rounded-2xl group-hover:scale-90 transition-transform">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                            </path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-sm font-black dark:text-white uppercase tracking-tight">Faturamento</h4>
                        <p class="text-[10px] text-gray-400 font-bold uppercase">Entradas e Métodos</p>
                    </div>
                </a>

                {{-- Fechamento de Caixa --}}
                <a href="{{ route('admin.financeiro.relatorio_caixa', ['arena_id' => request('arena_id')]) }}"
                    class="group flex items-center p-3 bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-transparent hover:border-emerald-500 hover:shadow-xl transition-all duration-300">
                    <div
                        class="p-5 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 rounded-2xl group-hover:scale-90 transition-transform">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1">
                            </path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-sm font-black dark:text-white uppercase tracking-tight">Fechamento de Caixa</h4>
                        <p class="text-[10px] text-gray-400 font-bold uppercase">Auditoria Diária</p>
                    </div>
                </a>

                {{-- Dívidas Pendentes (ADICIONADO) --}}
                <a href="{{ route('admin.financeiro.relatorio_dividas', ['arena_id' => request('arena_id')]) }}"
                    class="group flex items-center p-3 bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-transparent hover:border-orange-500 hover:shadow-xl transition-all duration-300">
                    <div
                        class="p-5 bg-orange-50 dark:bg-orange-900/30 text-orange-600 rounded-2xl group-hover:scale-90 transition-transform">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                            </path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-sm font-black dark:text-white uppercase tracking-tight">Dívidas Pendentes</h4>
                        <p class="text-[10px] text-gray-400 font-bold uppercase">Cobranças em aberto</p>
                    </div>
                </a>

                {{-- Faltas & No-Show --}}
                <a href="{{ route('admin.financeiro.relatorio_cancelamentos', ['arena_id' => request('arena_id')]) }}"
                    class="group flex items-center p-3 bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-transparent hover:border-red-500 hover:shadow-xl transition-all duration-300">
                    <div
                        class="p-5 bg-red-50 dark:bg-red-900/30 text-red-600 rounded-2xl group-hover:scale-90 transition-transform">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-sm font-black dark:text-white uppercase tracking-tight">Faltas & No-Show</h4>
                        <p class="text-[10px] text-gray-400 font-bold uppercase">Mapear Prejuízos</p>
                    </div>
                </a>

                {{-- Mapa de Ocupação --}}
                <a href="{{ route('admin.financeiro.relatorio_ocupacao', ['arena_id' => request('arena_id')]) }}"
                    class="group flex items-center p-3 bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-transparent hover:border-blue-500 hover:shadow-xl transition-all duration-300">
                    <div
                        class="p-5 bg-blue-50 dark:bg-blue-900/30 text-blue-600 rounded-2xl group-hover:scale-90 transition-transform">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                            </path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-sm font-black dark:text-white uppercase tracking-tight">Mapa de Ocupação</h4>
                        <p class="text-[10px] text-gray-400 font-bold uppercase">Agenda & Histórico</p>
                    </div>
                </a>

                {{-- Ranking Fidelidade --}}
                <a href="{{ route('admin.financeiro.relatorio_ranking', ['arena_id' => request('arena_id')]) }}"
                    class="group flex items-center p-3 bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-transparent hover:border-amber-500 hover:shadow-xl transition-all duration-300">
                    <div
                        class="p-5 bg-amber-50 dark:bg-amber-900/30 text-amber-500 rounded-2xl group-hover:scale-90 transition-transform">
                        <span class="text-3xl">🏆</span>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-sm font-black dark:text-white uppercase tracking-tight">Ranking Fidelidade</h4>
                        <p class="text-[10px] text-gray-400 font-bold uppercase">Top 15 Gastos</p>
                    </div>
                </a>

                {{-- Performance de Operadores (ADICIONADO) --}}
                <a href="{{ route('admin.financeiro.relatorio_operadores', ['arena_id' => request('arena_id')]) }}"
                    class="group flex items-center p-3 bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-transparent hover:border-violet-500 hover:shadow-xl transition-all duration-300">
                    <div
                        class="p-5 bg-violet-50 dark:bg-violet-900/30 text-violet-600 rounded-2xl group-hover:scale-90 transition-transform">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                            </path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-sm font-black dark:text-white uppercase tracking-tight">Performance</h4>
                        <p class="text-[10px] text-gray-400 font-bold uppercase">Produtividade por Operador</p>
                    </div>
                </a>

                {{-- Relatório de Cortesias --}}
                <a href="{{ route('admin.financeiro.relatorio_cortesias', ['arena_id' => request('arena_id')]) }}"
                    class="group flex items-center p-3 bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-transparent hover:border-amber-500 hover:shadow-xl transition-all duration-300">
                    <div
                        class="p-5 bg-amber-50 dark:bg-amber-900/30 text-amber-500 rounded-2xl group-hover:scale-90 transition-transform">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-sm font-black dark:text-white uppercase tracking-tight">Cortesias</h4>
                        <p class="text-[10px] text-gray-400 font-bold uppercase">Rastrear Vouchers</p>
                    </div>
                </a>

            </div>
        </div>
    </div>
</x-app-layout>
