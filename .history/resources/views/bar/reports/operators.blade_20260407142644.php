<x-bar-layout>
    <div class="max-w-[1400px] mx-auto px-6 py-8">

        {{-- 🛡️ HEADER & FILTROS --}}
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end gap-6 mb-10">
            <div>
                <h1 class="text-4xl font-black text-white uppercase tracking-tighter italic leading-none">
                    Performance de <span class="text-orange-500">Operadores</span>
                </h1>
                <p class="text-gray-500 font-bold uppercase text-[10px] tracking-widest mt-2 italic">
                    Análise de produtividade
                </p>
            </div>

            {{-- 🔍 FORMULÁRIO DE FILTRO --}}
            <form action="{{ route('bar.reports.operators') }}" method="GET" class="flex flex-wrap items-center gap-4 bg-gray-900/50 p-4 rounded-[2rem] border border-gray-800 shadow-xl">

                {{-- Busca por Nome --}}
                <div class="flex flex-col gap-1">
                    <label class="text-[9px] font-black text-gray-600 uppercase ml-2">Pesquisar Nome</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Ex: Renato..."
                        class="bg-black border-2 border-gray-800 rounded-xl px-4 py-2 text-white text-xs font-bold focus:border-orange-500 outline-none transition-all w-48">
                </div>

                {{-- Data Início --}}
                <div class="flex flex-col gap-1">
                    <label class="text-[9px] font-black text-gray-600 uppercase ml-2">De:</label>
                    <input type="date" name="start_date" value="{{ $start }}"
                        class="bg-black border-2 border-gray-800 rounded-xl px-4 py-2 text-white text-xs font-bold focus:border-orange-500 outline-none">
                </div>

                {{-- Data Fim --}}
                <div class="flex flex-col gap-1">
                    <label class="text-[9px] font-black text-gray-600 uppercase ml-2">Até:</label>
                    <input type="date" name="end_date" value="{{ $end }}"
                        class="bg-black border-2 border-gray-800 rounded-xl px-4 py-2 text-white text-xs font-bold focus:border-orange-500 outline-none">
                </div>

                <button type="submit" class="mt-5 bg-orange-600 hover:bg-orange-500 text-white p-2.5 rounded-xl transition-all shadow-lg shadow-orange-900/20">
                    🔍
                </button>

                <a href="{{ route('bar.reports.operators') }}" class="mt-5 bg-gray-800 hover:bg-gray-700 text-gray-400 p-2.5 rounded-xl transition-all" title="Limpar Filtros">
                    🔄
                </a>
            </form>
        </div>

        {{-- 🏆 RANKING DE VENDAS --}}
        <div class="bg-gray-900 rounded-[3rem] border border-gray-800 overflow-hidden shadow-2xl">
            <div class="p-8 border-b border-gray-800 bg-gray-800/20 flex justify-between items-center">
                <h3 class="text-white font-black uppercase italic tracking-widest text-lg">Ranking do Período</h3>
                <span class="text-[10px] text-gray-500 font-bold uppercase tracking-tighter italic">
                    Período: {{ date('d/m/Y', strtotime($start)) }} à {{ date('d/m/Y', strtotime($end)) }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-gray-500 text-[10px] font-black uppercase tracking-widest border-b border-gray-800 bg-black/20">
                            <th class="p-6">#</th>
                            <th class="p-6">Operador</th>
                            <th class="p-6 text-center">Volume Vendas</th>
                            <th class="p-6 text-right">Faturamento Bruto</th>
                            <th class="p-6 text-right">Estornos</th>
                            <th class="p-6 text-right">Líquido</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/50">
                        @forelse($vendasPorOperador as $index => $rank)
                        <tr class="hover:bg-white/[0.02] transition-colors group">
                            <td class="p-6 font-black italic text-gray-700 text-xl">#{{ $loop->iteration }}</td>
                            <td class="p-6">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 bg-gray-800 rounded-2xl flex items-center justify-center font-black text-orange-500 border border-gray-700 shadow-inner group-hover:border-orange-500/50 transition-all">
                                        {{ substr($rank->user->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <span class="text-white font-black uppercase italic text-sm block leading-none">{{ $rank->user->name }}</span>
                                        <span class="text-[8px] text-gray-600 font-bold uppercase tracking-widest">{{ $rank->user->role }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="p-6 text-center text-white font-mono font-bold">{{ $rank->qtd_vendas }}</td>
                            <td class="p-6 text-right text-gray-400 font-mono text-xs">R$ {{ number_format($rank->total_bruto, 2, ',', '.') }}</td>
                            <td class="p-6 text-right text-red-500/50 font-mono text-xs">- R$ {{ number_format($rank->total_estornado, 2, ',', '.') }}</td>
                            <td class="p-6 text-right text-white font-black text-2xl italic font-mono tracking-tighter">
                                R$ {{ number_format($rank->faturamento_liquido, 2, ',', '.') }}
                            </td>

                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="p-24 text-center opacity-20">
                                <p class="text-gray-600 font-black uppercase tracking-widest italic text-3xl">Nenhum dado no período</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-bar-layout>
