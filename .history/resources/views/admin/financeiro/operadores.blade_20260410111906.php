<x-app-layout>
    <div class="max-w-[1400px] mx-auto px-6 py-8 italic">

        {{-- 🛡️ HEADER & FILTROS --}}
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end gap-6 mb-10">
            <div class="flex items-center gap-5">
                <a href="{{ route('admin.financeiro.dashboard') }}"
                    class="p-3 bg-white dark:bg-gray-800 text-indigo-500 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-lg hover:scale-110 transition-all group">
                    <span class="group-hover:-translate-x-1 transition-transform inline-block">◀</span>
                </a>
                <div>
                    <h1 class="text-4xl font-black text-gray-800 dark:text-white uppercase tracking-tighter italic leading-none">
                        Performance de <span class="text-indigo-600">Operadores</span>
                    </h1>
                    <p class="text-gray-500 font-bold text-[10px] uppercase tracking-[0.2em] mt-1 italic">
                        Análise de produtividade e preenchimento de agenda
                    </p>
                </div>
            </div>

            {{-- 🔍 FILTROS --}}
            <form action="{{ route('admin.financeiro.relatorio_operadores') }}" method="GET"
                class="flex flex-wrap items-center gap-4 bg-white dark:bg-gray-800 p-4 rounded-[2rem] border border-gray-100 dark:border-gray-700 shadow-xl print:hidden">

                <div class="flex flex-col gap-1">
                    <label class="text-[9px] font-black text-gray-400 uppercase ml-2">Pesquisar Operador</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Nome..."
                        class="bg-gray-50 dark:bg-gray-900 border-none rounded-xl px-4 py-2 text-gray-700 dark:text-white text-xs font-bold focus:ring-2 focus:ring-indigo-500 w-48">
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[9px] font-black text-gray-400 uppercase ml-2">De:</label>
                    <input type="date" name="data_inicio" value="{{ $start }}"
                        class="bg-gray-50 dark:bg-gray-900 border-none rounded-xl px-4 py-2 text-gray-700 dark:text-white text-xs font-bold focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[9px] font-black text-gray-400 uppercase ml-2">Até:</label>
                    <input type="date" name="data_fim" value="{{ $end }}"
                        class="bg-gray-50 dark:bg-gray-900 border-none rounded-xl px-4 py-2 text-gray-700 dark:text-white text-xs font-bold focus:ring-2 focus:ring-indigo-500">
                </div>

                <button type="submit" class="mt-5 bg-indigo-600 hover:bg-indigo-500 text-white p-2.5 rounded-xl transition-all shadow-lg shadow-indigo-900/20">
                    🔍
                </button>

                <a href="{{ route('admin.financeiro.relatorio_operadores') }}" class="mt-5 bg-gray-100 dark:bg-gray-700 text-gray-500 p-2.5 rounded-xl transition-all">
                    🔄
                </a>
            </form>
        </div>

        {{-- 🏆 RANKING DE PRODUTIVIDADE --}}
        <div class="bg-white dark:bg-gray-800 rounded-[3rem] border border-gray-100 dark:border-gray-700 overflow-hidden shadow-2xl">
            <div class="p-8 border-b dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/20 flex justify-between items-center">
                <h3 class="text-gray-800 dark:text-white font-black uppercase italic tracking-widest text-lg">Ranking de Reservas</h3>
                <span class="text-[10px] text-gray-400 font-bold uppercase tracking-tighter italic">
                    Período: {{ date('d/m/Y', strtotime($start)) }} à {{ date('d/m/Y', strtotime($end)) }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-gray-400 text-[10px] font-black uppercase tracking-widest border-b dark:border-gray-700 bg-gray-50/50 dark:bg-black/20">
                            <th class="p-6">#</th>
                            <th class="p-6">Operador</th>
                            <th class="p-6 text-center">Horários Reservados</th>
                            <th class="p-6 text-right">Potencial Bruto</th>
                            <th class="p-6 text-right text-red-400">Cancelamentos/Faltas</th>
                            <th class="p-6 text-right">Efetivado (Líquido)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                        @forelse($operadores as $rank)
                            <tr class="hover:bg-indigo-50/30 dark:hover:bg-white/[0.02] transition-colors group">
                                <td class="p-6 font-black italic text-gray-300 dark:text-gray-700 text-2xl">#{{ $loop->iteration }}</td>
                                <td class="p-6">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900/50 rounded-2xl flex items-center justify-center font-black text-indigo-600 border border-indigo-200 dark:border-indigo-800 group-hover:scale-110 transition-transform shadow-inner">
                                            {{ substr($rank->manager->name ?? '?', 0, 1) }}
                                        </div>
                                        <div>
                                            <span class="text-gray-900 dark:text-white font-black uppercase italic text-sm block leading-none">
                                                {{ $rank->manager->name ?? 'Operador Deletado' }}
                                            </span>
                                            <span class="text-[8px] text-gray-400 font-bold uppercase tracking-widest italic">
                                                Staff Arena
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-6 text-center text-gray-700 dark:text-gray-300 font-mono font-bold">{{ $rank->qtd_reservas }}</td>
                                <td class="p-6 text-right text-gray-400 font-mono text-xs">
                                    R$ {{ number_format($rank->total_bruto, 2, ',', '.') }}
                                </td>
                                <td class="p-6 text-right text-red-500/50 font-mono text-xs italic">
                                    - R$ {{ number_format($rank->total_perdas, 2, ',', '.') }}
                                </td>
                                <td class="p-6 text-right">
                                    <div class="bg-indigo-50 dark:bg-indigo-900/20 px-4 py-2 rounded-2xl inline-block border border-indigo-100 dark:border-indigo-800">
                                        <span class="text-indigo-600 dark:text-indigo-400 font-black text-2xl italic font-mono tracking-tighter">
                                            R$ {{ number_format($rank->faturamento_liquido, 2, ',', '.') }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-24 text-center">
                                    <p class="text-gray-300 dark:text-gray-700 font-black uppercase tracking-widest italic text-3xl">Nenhum dado no período</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
