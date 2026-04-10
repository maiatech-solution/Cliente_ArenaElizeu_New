<x-bar-layout>
    <div class="max-w-[1400px] mx-auto px-6 py-8 italic text-white">

        {{-- 🛡️ HEADER & FILTROS --}}
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end gap-6 mb-10">
            <div class="flex items-center gap-5">
                <a href="{{ route('bar.reports.index') }}"
                    class="p-3 bg-gray-900 hover:bg-gray-800 text-orange-500 rounded-2xl border border-gray-800 transition-all group shadow-lg">
                    <span class="group-hover:-translate-x-1 transition-transform inline-block">◀</span>
                </a>
                <div>
                    <h1 class="text-4xl font-black uppercase tracking-tighter leading-none">
                        Cortesias & <span class="text-orange-600">Vouchers</span>
                    </h1>
                    <p class="text-gray-500 font-bold text-[10px] uppercase tracking-[0.2em] mt-1 italic">
                        Auditoria de consumo não financeiro do Bar
                    </p>
                </div>
            </div>

            {{-- 🔍 FILTROS DARK (Ajustado para Operador/ID) --}}
            <form action="{{ route('bar.reports.vouchers') }}" method="GET"
                class="flex flex-wrap items-center gap-4 bg-gray-900/50 p-4 rounded-[2rem] border border-gray-800 shadow-xl">

                <div class="flex flex-col gap-1">
                    {{-- Rótulo atualizado para fazer sentido com o "Consumidor" padrão --}}
                    <label class="text-[9px] font-black text-gray-600 uppercase ml-2 italic">Pesquisar Operador ou
                        ID</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Ex: Maia ou #87"
                        class="bg-black border-2 border-gray-800 rounded-xl px-4 py-2 text-white text-xs font-bold focus:border-orange-500 outline-none transition-all w-56">
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[9px] font-black text-gray-600 uppercase ml-2 italic text-center">Início</label>
                    <input type="date" name="data_inicio" value="{{ $start }}"
                        class="bg-black border-2 border-gray-800 rounded-xl px-4 py-2 text-white text-xs font-bold focus:border-orange-500 outline-none">
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[9px] font-black text-gray-600 uppercase ml-2 italic text-center">Fim</label>
                    <input type="date" name="data_fim" value="{{ $end }}"
                        class="bg-black border-2 border-gray-800 rounded-xl px-4 py-2 text-white text-xs font-bold focus:border-orange-500 outline-none">
                </div>

                <button type="submit"
                    class="mt-5 bg-orange-600 hover:bg-orange-500 text-white p-2.5 rounded-xl transition-all shadow-lg shadow-orange-900/20">
                    🔍
                </button>

                <a href="{{ route('bar.reports.vouchers') }}"
                    class="mt-5 bg-gray-800 hover:bg-gray-700 text-gray-400 p-2.5 rounded-xl transition-all">
                    🔄
                </a>
            </form>
        </div>

        {{-- 📊 CARDS DE RESUMO --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
            <div
                class="bg-gray-900 p-8 rounded-[3rem] border border-gray-800 shadow-2xl flex justify-between items-center">
                <div>
                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] italic">Impacto em
                        Cortesias</p>
                    <h3 class="text-4xl font-black text-orange-600 font-mono tracking-tighter italic">R$
                        {{ number_format($totalValor, 2, ',', '.') }}</h3>
                </div>
                <div class="text-4xl opacity-20 group-hover:opacity-100 transition-opacity">🎁</div>
            </div>
            <div
                class="bg-gray-900 p-8 rounded-[3rem] border border-gray-800 shadow-2xl flex justify-between items-center">
                <div>
                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] italic">Total de Vendas
                        Voucher</p>
                    <h3 class="text-4xl font-black text-white font-mono tracking-tighter italic">{{ $totalQtd }}
                        <span class="text-sm">Vendas</span>
                    </h3>
                </div>
                <div class="text-4xl opacity-20">📊</div>
            </div>
        </div>

        {{-- 🏆 TABELA DE AUDITORIA --}}
        <div class="bg-gray-900 rounded-[3rem] border border-gray-800 overflow-hidden shadow-2xl">
            <div class="p-8 border-b border-gray-800 bg-gray-800/20 flex justify-between items-center">
                <h3 class="text-white font-black uppercase italic tracking-widest text-lg">Histórico de Cortesias</h3>
                <span class="text-[10px] text-gray-500 font-bold uppercase tracking-tighter italic">
                    Período: {{ date('d/m/Y', strtotime($start)) }} à {{ date('d/m/Y', strtotime($end)) }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr
                            class="text-gray-500 text-[10px] font-black uppercase tracking-widest border-b border-gray-800 bg-black/20">
                            <th class="p-6">Data/Hora</th>
                            <th class="p-6">Venda / Cliente</th>
                            <th class="p-6">Operador</th>
                            <th class="p-6 text-right">Valor Abatido</th>
                            <th class="p-6 text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/50">
                        @forelse($cortesias as $item)
                            <tr class="hover:bg-white/[0.02] transition-colors group">
                                <td class="p-6 text-xs text-gray-400 font-bold italic font-mono uppercase">
                                    {{ $item->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="p-6">
                                    <div class="flex flex-col">
                                        <span
                                            class="text-white font-black uppercase italic text-sm">{{ $item->customer_name ?? 'Consumidor' }}</span>
                                        <span class="text-[9px] text-orange-500 font-bold uppercase">ID:
                                            #{{ $item->id }}</span>
                                    </div>
                                </td>
                                <td class="p-6">
                                    <span
                                        class="text-xs text-gray-400 font-bold uppercase">{{ $item->user->name ?? 'Sistema' }}</span>
                                </td>
                                <td class="p-6 text-right">
                                    <span class="text-orange-600 font-black text-2xl italic font-mono tracking-tighter">
                                        {{-- 🎯 Mudança aqui: de total_amount para valor_real --}}
                                        R$ {{ number_format($item->valor_real, 2, ',', '.') }}
                                    </span>
                                </td>
                                <td class="p-6 text-center">
                                    <button onclick="openVendaDetails('{{ $item->origin }}', {{ $item->id }})"
                                        class="bg-gray-800 hover:bg-orange-600 text-[9px] text-white font-black uppercase py-2 px-4 rounded-xl transition-all">
                                        Ver Itens
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-24 text-center">
                                    <p
                                        class="text-gray-600 font-black uppercase tracking-widest italic text-3xl opacity-20">
                                        Nenhuma cortesia registrada</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-bar-layout>
