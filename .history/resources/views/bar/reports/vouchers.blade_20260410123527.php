<x-bar-layout>
    {{-- Início do Componente Alpine.js --}}
    <div x-data="{
        modalDetalhes: false,
        carregando: false,
        venda: { id: '', data: '', itens: [], total: '0,00', operador: '', pagamento: '', desconto: 0, total_raw: 0, tipo: '' },

        openVendaDetails(tipo, id) {
            this.modalDetalhes = true;
            this.carregando = true;

            // Busca os dados via AJAX na rota de detalhes
            fetch(`/bar/relatorios/venda-detalhes/${tipo}/${id}`)
                .then(res => res.json())
                .then(data => {
                    this.venda = data;
                    this.carregando = false;
                })
                .catch(err => {
                    console.error('Erro ao buscar itens:', err);
                    this.carregando = false;
                });
        }
    }" class="max-w-[1400px] mx-auto px-6 py-8 italic text-white font-sans">

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
                    <p class="text-gray-500 font-bold text-[10px] uppercase tracking-[0.2em] mt-1 italic leading-none">
                        Auditoria de consumo não financeiro do Bar
                    </p>
                </div>
            </div>

            {{-- 🔍 FILTROS DARK --}}
            <form action="{{ route('bar.reports.vouchers') }}" method="GET"
                class="flex flex-wrap items-center gap-4 bg-gray-900/50 p-4 rounded-[2rem] border border-gray-800 shadow-xl">

                <div class="flex flex-col gap-1">
                    <label class="text-[9px] font-black text-gray-600 uppercase ml-2 italic">Pesquisar Operador ou ID</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Ex: Maia ou #87"
                        class="bg-black border-2 border-gray-800 rounded-xl px-4 py-2 text-white text-xs font-bold focus:border-orange-500 outline-none transition-all w-56 font-sans">
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[9px] font-black text-gray-600 uppercase ml-2 italic text-center leading-none">Início</label>
                    <input type="date" name="data_inicio" value="{{ $start }}"
                        class="bg-black border-2 border-gray-800 rounded-xl px-4 py-2 text-white text-xs font-bold focus:border-orange-500 outline-none font-sans">
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[9px] font-black text-gray-600 uppercase ml-2 italic text-center leading-none">Fim</label>
                    <input type="date" name="data_fim" value="{{ $end }}"
                        class="bg-black border-2 border-gray-800 rounded-xl px-4 py-2 text-white text-xs font-bold focus:border-orange-500 outline-none font-sans">
                </div>

                <button type="submit"
                    class="mt-5 bg-orange-600 hover:bg-orange-500 text-white p-2.5 rounded-xl transition-all shadow-lg shadow-orange-900/20">
                    🔍
                </button>

                <a href="{{ route('bar.reports.vouchers') }}"
                    class="mt-5 bg-gray-800 hover:bg-gray-700 text-gray-400 p-2.5 rounded-xl transition-all font-sans">
                    🔄
                </a>
            </form>
        </div>

        {{-- 📊 CARDS DE RESUMO --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
            <div class="bg-gray-900 p-8 rounded-[3rem] border border-gray-800 shadow-2xl flex justify-between items-center transition-all hover:border-orange-900/50">
                <div>
                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] italic leading-none mb-2">Impacto em Cortesias</p>
                    <h3 class="text-4xl font-black text-orange-600 font-mono tracking-tighter italic leading-none">R$ {{ number_format($totalValor, 2, ',', '.') }}</h3>
                </div>
                <div class="text-4xl opacity-20">🎁</div>
            </div>
            <div class="bg-gray-900 p-8 rounded-[3rem] border border-gray-800 shadow-2xl flex justify-between items-center transition-all hover:border-gray-700">
                <div>
                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] italic leading-none mb-2">Total de Vendas Voucher</p>
                    <h3 class="text-4xl font-black text-white font-mono tracking-tighter italic leading-none">{{ $totalQtd }} <span class="text-sm">Vendas</span></h3>
                </div>
                <div class="text-4xl opacity-20">📊</div>
            </div>
        </div>

        {{-- 🏆 TABELA DE AUDITORIA --}}
        <div class="bg-gray-900 rounded-[3rem] border border-gray-800 overflow-hidden shadow-2xl">
            <div class="p-8 border-b border-gray-800 bg-gray-800/20 flex justify-between items-center">
                <h3 class="text-white font-black uppercase italic tracking-widest text-lg">Histórico de Auditoria</h3>
                <span class="text-[10px] text-gray-500 font-bold uppercase tracking-tighter italic">
                    Período: {{ date('d/m/Y', strtotime($start)) }} à {{ date('d/m/Y', strtotime($end)) }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-gray-500 text-[10px] font-black uppercase tracking-widest border-b border-gray-800 bg-black/20 italic">
                            <th class="p-6">Data/Hora</th>
                            <th class="p-6">Venda / Cliente</th>
                            <th class="p-6">Operador</th>
                            <th class="p-6 text-right">Valor Abatido</th>
                            <th class="p-6 text-center">Ações</th>
                        </tr>
                    </thead>
                  <tbody class="divide-y divide-gray-800/50 font-sans">
    @forelse($cortesias as $item)
        <tr class="hover:bg-white/[0.02] transition-colors group">
            <td class="p-6 text-xs text-gray-400 font-bold italic font-mono uppercase">
                {{ $item->created_at->format('d/m/Y H:i') }}
            </td>
            <td class="p-6">
                <div class="flex flex-col leading-none gap-1">
                    <span class="text-white font-black uppercase italic text-sm">{{ $item->customer_name ?? 'Consumidor' }}</span>
                    <div class="flex items-center gap-2">
                        <span class="text-[9px] text-orange-500 font-bold uppercase tracking-tighter italic">ID Mov: #{{ $item->id }}</span>
                        @if($item->bar_order_id)
                            <span class="text-[8px] bg-blue-900/30 text-blue-400 px-1.5 rounded uppercase font-black border border-blue-900/50">Mesa #{{ $item->bar_order_id }}</span>
                        @else
                            <span class="text-[8px] bg-emerald-900/30 text-emerald-400 px-1.5 rounded uppercase font-black border border-emerald-900/50">PDV</span>
                        @endif
                    </div>
                </div>
            </td>
            <td class="p-6">
                <span class="text-xs text-gray-400 font-bold uppercase italic tracking-tighter">{{ $item->user->name ?? 'Sistema' }}</span>
            </td>
            <td class="p-6 text-right">
                <span class="text-orange-600 font-black text-2xl italic font-mono tracking-tighter leading-none">
                    R$ {{ number_format($item->valor_real, 2, ',', '.') }}
                </span>
            </td>
            <td class="p-6 text-center">
                @php
                    // 🎯 O pulo do gato: Usa o ID real que o Controller pescou
                    $tipoJS = $item->origem_tipo;
                    $idJS = $item->venda_id_real;
                @endphp

                @if($idJS)
                    <button @click="openVendaDetails('{{ $tipoJS }}', {{ $idJS }})"
                        class="bg-gray-800 hover:bg-orange-600 text-[9px] text-white font-black uppercase py-2.5 px-5 rounded-xl transition-all shadow-lg hover:shadow-orange-900/30 italic">
                        Ver Itens
                    </button>
                @else
                    <span class="text-[8px] text-gray-600 italic uppercase font-black">Sem Itens</span>
                @endif
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="5" class="p-24 text-center">
                <p class="text-gray-600 font-black uppercase tracking-widest italic text-3xl opacity-20">Nenhuma cortesia registrada</p>
            </td>
        </tr>
    @endforelse
</tbody>
                </table>
            </div>
        </div>

        {{-- 📋 MODAL DE DETALHES (Estilo PDV/Mesas Integrado) --}}
        <div x-show="modalDetalhes" x-transition.opacity
            class="fixed inset-0 z-[110] flex items-center justify-center bg-black/95 backdrop-blur-sm p-4" style="display: none;">
            <div @click.away="modalDetalhes = false"
                class="bg-gray-900 border border-gray-800 w-full max-w-md rounded-[3rem] overflow-hidden shadow-2xl relative font-sans">

                <div class="absolute -right-10 -top-10 text-9xl opacity-5 italic font-black text-white pointer-events-none uppercase"
                    x-text="venda.tipo"></div>

                <div class="p-10 relative italic">
                    <div class="flex justify-between items-start mb-8 leading-none">
                        <div>
                            <h3 class="text-white text-3xl font-black uppercase italic tracking-tighter">
                                Resumo <span class="text-orange-500">#</span><span x-text="venda.id"></span>
                            </h3>
                            <p class="text-gray-500 text-[10px] font-bold uppercase tracking-widest mt-1" x-text="venda.data"></p>
                        </div>
                        <button @click="modalDetalhes = false" class="text-gray-500 hover:text-white text-2xl">✕</button>
                    </div>

                    {{-- Estado de Carregamento --}}
                    <div x-show="carregando" class="py-20 text-center">
                        <span class="animate-pulse text-orange-500 font-black uppercase text-xs tracking-[0.3em]">Buscando Itens...</span>
                    </div>

                    {{-- Conteúdo Carregado --}}
                    <div x-show="!carregando" class="space-y-6">
                        <div class="bg-black/40 rounded-[2rem] p-6 border border-gray-800/50">
                            <label class="text-[8px] font-black text-gray-600 uppercase tracking-widest block mb-4 italic">Itens Bonificados</label>
                            <div class="space-y-4 max-h-60 overflow-y-auto no-scrollbar">
                                <template x-for="item in venda.itens">
                                    <div class="flex justify-between items-center border-b border-gray-800 pb-3 last:border-0 leading-none">
                                        <div>
                                            <span class="text-orange-500 font-black text-xs" x-text="item.qtd + 'x '"></span>
                                            <span class="text-gray-300 text-xs font-bold uppercase italic" x-text="item.nome"></span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-white font-mono text-xs font-bold tracking-tighter" x-text="'R$ ' + item.subtotal"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="space-y-3 pt-4 border-t border-gray-800 leading-none">
                            <div class="flex justify-between items-center text-[10px] font-black uppercase italic text-gray-500">
                                <span>Operador que liberou</span>
                                <span class="text-gray-300 font-sans" x-text="venda.operador"></span>
                            </div>

                            <div class="flex justify-between items-center text-[10px] font-black uppercase italic text-gray-500">
                                <span>Método de Registro</span>
                                <span class="text-orange-500" x-text="venda.pagamento"></span>
                            </div>

                            <div class="pt-4 border-t border-gray-800 flex justify-between items-end">
                                <span class="text-gray-500 font-black uppercase text-xs italic">Prejuízo Op.</span>
                                <span class="text-4xl font-black text-white italic tracking-tighter font-mono" x-text="'R$ ' + venda.total"></span>
                            </div>
                        </div>
                    </div>

                    <button @click="modalDetalhes = false"
                        class="w-full mt-10 py-5 bg-gray-800 hover:bg-gray-700 text-gray-400 hover:text-white rounded-2xl font-black uppercase text-[10px] tracking-[0.2em] transition-all italic">
                        Fechar Relatório
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-bar-layout>

<style>
    /* Remove a barra de rolagem lateral sem perder a funcionalidade */
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
