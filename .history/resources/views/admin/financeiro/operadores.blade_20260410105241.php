<x-app-layout>
    <div class="max-w-[1400px] mx-auto px-6 py-8 italic">

        {{-- 🛡️ HEADER & FILTROS --}}
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end gap-6 mb-10">
            <div class="flex items-center gap-5">
                <a href="{{ route('admin.financeiro.dashboard') }}"
                    class="p-3 bg-white dark:bg-gray-800 text-indigo-500 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-lg hover:scale-105 transition-all">
                    <span>◀</span>
                </a>
                <div>
                    <h1 class="text-4xl font-black text-gray-800 dark:text-white uppercase tracking-tighter leading-none">
                        Performance <span class="text-indigo-600">Arena</span>
                    </h1>
                    <p class="text-gray-500 font-bold text-[10px] uppercase tracking-[0.2em] mt-1">
                        Reservas por Operador e Conversão Financeira
                    </p>
                </div>
            </div>

            {{-- 🔍 FILTROS --}}
            <form method="GET" class="flex flex-wrap items-center gap-4 bg-white dark:bg-gray-800 p-4 rounded-[2rem] border border-gray-100 dark:border-gray-700 shadow-xl">
                <div class="flex flex-col gap-1">
                    <label class="text-[9px] font-black text-gray-400 uppercase ml-2">Operador</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Nome..."
                        class="bg-gray-50 dark:bg-gray-900 border-none rounded-xl px-4 py-2 text-gray-700 dark:text-white text-xs font-bold w-40">
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[9px] font-black text-gray-400 uppercase ml-2">De:</label>
                    <input type="date" name="start_date" value="{{ $start }}"
                        class="bg-gray-50 dark:bg-gray-900 border-none rounded-xl px-4 py-2 text-gray-700 dark:text-white text-xs font-bold">
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[9px] font-black text-gray-400 uppercase ml-2">Até:</label>
                    <input type="date" name="end_date" value="{{ $end }}"
                        class="bg-gray-50 dark:bg-gray-900 border-none rounded-xl px-4 py-2 text-gray-700 dark:text-white text-xs font-bold">
                </div>

                <button type="submit" class="mt-5 bg-indigo-600 text-white p-2.5 rounded-xl shadow-lg shadow-indigo-200">🔍</button>
            </form>
        </div>

        {{-- 🏆 RANKING --}}
        <div class="bg-white dark:bg-gray-800 rounded-[3rem] border border-gray-100 dark:border-gray-700 overflow-hidden shadow-2xl">
            <div class="p-8 border-b dark:border-gray-700 flex justify-between items-center bg-gray-50/50 dark:bg-gray-900/20">
                <h3 class="text-gray-800 dark:text-white font-black uppercase tracking-widest text-lg">Ranking de Produtividade</h3>
                <span class="text-[10px] text-gray-400 font-bold uppercase tracking-tighter">
                    Período: {{ date('d/m/Y', strtotime($start)) }} - {{ date('d/m/Y', strtotime($end)) }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-gray-400 text-[10px] font-black uppercase tracking-widest border-b dark:border-gray-700">
                            <th class="p-6">#</th>
                            <th class="p-6">Operador</th>
                            <th class="p-6 text-center">Horários Reservados</th>
                            <th class="p-6 text-right">Bruto Estimado</th>
                            <th class="p-6 text-right text-red-400">Cancelamentos/Perdas</th>
                            <th class="p-6 text-right">Faturamento Real</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                        @forelse($operadores as $rank)
                            <tr class="hover:bg-indigo-50/30 dark:hover:bg-indigo-900/10 transition-colors group">
                                <td class="p-6 font-black text-gray-300 dark:text-gray-600 text-2xl italic">#{{ $loop->iteration }}</td>
                                <td class="p-6">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 bg-indigo-100 dark:bg-indigo-900/50 rounded-2xl flex items-center justify-center font-black text-indigo-600 border border-indigo-200 dark:border-indigo-800 group-hover:scale-110 transition-transform">
                                            {{ substr($rank->manager->name ?? '?', 0, 1) }}
                                        </div>
                                        <div>
                                            <span class="text-gray-800 dark:text-white font-black uppercase text-sm block leading-none">{{ $rank->manager->name ?? 'Desconhecido' }}</span>
                                            <span class="text-[8px] text-gray-400 font-bold uppercase tracking-widest">Operador Arena</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-6 text-center text-gray-600 dark:text-gray-300 font-mono font-bold">{{ $rank->qtd_reservas }}</td>
                                <td class="p-6 text-right text-gray-400 font-mono text-xs">R$ {{ number_format($rank->total_bruto, 2, ',', '.') }}</td>
                                <td class="p-6 text-right text-red-500/50 font-mono text-xs">- R$ {{ number_format($rank->total_perdas, 2, ',', '.') }}</td>
                                <td class="p-4 text-right">
                                    <div class="bg-gray-50 dark:bg-gray-900/50 px-4 py-2 rounded-2xl inline-block border border-gray-100 dark:border-gray-700 group-hover:border-indigo-500/30 transition-all">
                                        <span class="text-indigo-600 dark:text-indigo-400 font-black text-2xl font-mono tracking-tighter italic">
                                            R$ {{ number_format($rank->faturamento_liquido, 2, ',', '.') }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-24 text-center">
                                    <p class="text-gray-300 dark:text-gray-700 font-black uppercase tracking-widest text-3xl">Sem dados no período</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
