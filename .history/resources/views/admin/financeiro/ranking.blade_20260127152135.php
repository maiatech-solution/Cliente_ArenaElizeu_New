<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-4">
                <a href="{{ route('admin.financeiro.dashboard', ['arena_id' => request('arena_id')]) }}"
                    class="flex items-center gap-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-all font-bold text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Painel
                </a>
                <h2 class="font-black text-xl text-gray-800 dark:text-gray-200 uppercase tracking-tighter">
                    {{-- Proteção Null Safe para evitar erro se arena_id for inválido --}}
                    🏆 Ranking:
                    {{ request('arena_id') ? \App\Models\Arena::find(request('arena_id'))?->name ?? 'Unidade não encontrada' : 'Todas as Unidades' }}
                </h2>
            </div>

            <button onclick="window.print()"
                class="print:hidden bg-black text-white px-6 py-2 rounded-full font-bold text-xs uppercase tracking-widest hover:bg-gray-800 transition-all shadow-lg">
                Imprimir Ranking
            </button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- 🔍 FILTROS DE UNIDADE E PERÍODO --}}
            <div
                class="bg-white dark:bg-gray-800 p-6 rounded-[2rem] shadow-sm border border-gray-100 dark:border-gray-700 print:hidden">
                <form method="GET" action="{{ route('admin.financeiro.relatorio_ranking') }}" id="filterForm"
                    class="grid grid-cols-1 md:grid-cols-12 gap-6 items-end">

                    {{-- Arena --}}
                    <div class="md:col-span-4">
                        <label class="block text-[10px] font-black text-gray-400 uppercase mb-1 ml-1">🏟️
                            Unidade</label>
                        <select name="arena_id" onchange="this.form.submit()"
                            class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-2 focus:ring-indigo-500 font-bold text-sm">
                            <option value="">Todas as Arenas</option>
                            @foreach (\App\Models\Arena::all() as $arena)
                                <option value="{{ $arena->id }}"
                                    {{ request('arena_id') == $arena->id ? 'selected' : '' }}>
                                    {{ $arena->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Mês --}}
                    <div class="md:col-span-4">
                        <label class="block text-[10px] font-black text-gray-400 uppercase mb-1 ml-1">📅 Mês
                            Referência</label>
                        <select name="mes" onchange="this.form.submit()"
                            class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-2 focus:ring-indigo-500 font-bold text-sm">
                            @foreach (range(1, 12) as $m)
                                <option value="{{ $m }}"
                                    {{ request('mes', now()->month) == $m ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::create()->month($m)->locale('pt_BR')->monthName }}
                                </option>
                            @endforeach
                            <option value="all" {{ request('mes') == 'all' ? 'selected' : '' }}>Ano Inteiro
                                (Consolidado)</option>
                        </select>
                    </div>

                    {{-- Ano --}}
                    <div class="md:col-span-4">
                        <label class="block text-[10px] font-black text-gray-400 uppercase mb-1 ml-1">📆 Ano</label>
                        <select name="ano" onchange="this.form.submit()"
                            class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-2 focus:ring-indigo-500 font-bold text-sm">
                            @for ($y = now()->year; $y >= 2024; $y--)
                                <option value="{{ $y }}"
                                    {{ request('ano', now()->year) == $y ? 'selected' : '' }}>{{ $y }}
                                </option>
                            @endfor
                        </select>
                    </div>
                </form>
            </div>

            <div
                class="bg-white dark:bg-gray-800 rounded-[2.5rem] shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden italic">
                <div
                    class="p-8 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50 flex justify-between items-center">
                    <div>
                        <h3 class="text-xs font-black text-indigo-600 uppercase tracking-[0.2em]">
                            Os 15 Clientes Mais Fiéis
                        </h3>
                        <p class="text-sm text-gray-400 font-medium italic">
                            Período:
                            <span class="text-indigo-500 font-bold">
                                @if (request('mes') == 'all')
                                    Ano de {{ request('ano', now()->year) }}
                                @else
                                    {{-- A correção (int) garante que o Carbon não receba uma string --}}
                                    {{ \Carbon\Carbon::create()->month((int) request('mes', now()->month))->locale('pt_BR')->monthName }}
                                    de {{ request('ano', now()->year) }}
                                @endif
                            </span>
                            <span class="mx-2 text-gray-300">|</span>
                            <span class="text-[10px] text-indigo-400 font-black uppercase tracking-tighter">
                                {{ request('arena_id') ? 'Ranking da Unidade' : 'Ranking Global' }}
                            </span>
                        </p>
                    </div>
                    <div class="hidden md:block">
                        <span
                            class="text-[10px] bg-indigo-100 text-indigo-600 px-3 py-1 rounded-full font-black uppercase">
                            Atualizado em Tempo Real
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr
                                class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100 dark:border-gray-700">
                                <th class="px-8 py-5 text-center w-20">Posição</th>
                                <th class="px-6 py-5">Cliente / Contato</th>
                                <th class="px-6 py-5 text-center whitespace-nowrap">Partidas Realizadas</th>
                                <th class="px-8 py-5 text-right">Total Investido</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                            @forelse($ranking as $index => $cliente)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/50 transition-colors group">
                                    <td class="px-8 py-5">
                                        <div class="flex justify-center">
                                            @if ($index == 0)
                                                <span
                                                    class="flex items-center justify-center w-10 h-10 bg-amber-400 text-white rounded-full shadow-lg shadow-amber-200 font-black italic text-lg animate-bounce">1º</span>
                                            @elseif($index == 1)
                                                <span
                                                    class="flex items-center justify-center w-9 h-9 bg-slate-300 text-white rounded-full shadow-lg shadow-slate-100 font-black italic">2º</span>
                                            @elseif($index == 2)
                                                <span
                                                    class="flex items-center justify-center w-9 h-9 bg-orange-400 text-white rounded-full shadow-lg shadow-orange-100 font-black italic text-sm">3º</span>
                                            @else
                                                <span
                                                    class="text-gray-400 font-black text-sm italic">{{ $index + 1 }}º</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div
                                            class="font-black text-gray-800 dark:text-white uppercase tracking-tight group-hover:text-indigo-600 transition-colors">
                                            {{ $cliente->client_name }}
                                        </div>
                                        <div class="text-[10px] text-gray-400 font-bold tracking-wider">
                                            {{ $cliente->client_contact ?? 'Contato não informado' }}
                                        </div>
                                    </td>
                                    <td
                                        class="px-6 py-5 text-center font-black text-gray-600 dark:text-gray-400 text-sm">
                                        <div
                                            class="inline-block px-3 py-1 bg-gray-100 dark:bg-gray-700 rounded-full italic">
                                            {{ $cliente->total_reservas }} <span
                                                class="text-[9px] text-gray-400 ml-1 uppercase">Jogos</span>
                                        </div>
                                    </td>
                                    <td
                                        class="px-8 py-5 text-right font-mono font-black text-emerald-600 dark:text-emerald-400 text-lg italic">
                                        R$ {{ number_format($cliente->total_gasto, 2, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4"
                                        class="px-6 py-20 text-center text-gray-400 font-bold uppercase text-xs tracking-widest">
                                        Nenhum dado financeiro encontrado para este período.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
</x-app-layout>
