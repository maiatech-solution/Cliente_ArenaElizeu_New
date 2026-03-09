<x-bar-layout>
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-10">

        {{-- 🍺 HEADER --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-6">
            <div>
                <h1 class="text-4xl font-black text-white uppercase tracking-tighter italic leading-none">
                    Conferência de <span class="text-emerald-500">Turnos</span>
                </h1>
                <p class="text-gray-500 font-medium italic mt-2 tracking-wide">Auditoria de aberturas, fechamentos e quebras de caixa.</p>
            </div>

            <div class="flex items-center gap-4">
                <form action="{{ route('bar.reports.cashier') }}" method="GET"
                    class="bg-gray-900 p-1 rounded-2xl flex items-center gap-3 px-4 border border-gray-800 shadow-2xl">
                    <input type="month" name="mes_referencia" value="{{ $mesReferencia }}"
                        onchange="this.form.submit()"
                        class="bg-transparent border-none p-2 font-black text-orange-500 uppercase text-xs focus:ring-0 cursor-pointer">
                </form>

                <a href="{{ route('bar.reports.index') }}"
                    class="p-3 bg-gray-900 text-orange-500 rounded-xl border border-gray-800 hover:bg-gray-800 transition-all font-black text-[10px] uppercase shadow-lg">
                    ◀ Voltar
                </a>
            </div>
        </div>

        {{-- 📈 RESUMO MENSAL RÁPIDO --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="bg-gray-900 p-8 rounded-[2.5rem] border border-gray-800 relative overflow-hidden group shadow-2xl">
                <div class="absolute -right-4 -bottom-4 text-7xl opacity-5 group-hover:scale-110 transition-transform">💰</div>
                <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1">Faturamento Auditado (Mês)</p>
                <h3 class="text-4xl font-black text-white italic tracking-tighter">
                    R$ {{ number_format($sessoes->sum('vendas_turno'), 2, ',', '.') }}
                </h3>
            </div>

            <div class="bg-gray-900 p-8 rounded-[2.5rem] border border-gray-800 border-l-4 border-l-red-600 shadow-2xl">
                @php
                    $quebraTotal = 0;
                    foreach($sessoes as $s) {
                        $diff = $s->closing_balance - $s->total_sistema_esperado;
                        if($s->status == 'closed' && $diff < -0.1) $quebraTotal += abs($diff);
                    }
                @endphp
                <p class="text-[10px] font-black text-red-500 uppercase tracking-widest mb-1">Total de Quebras (Faltas)</p>
                <h3 class="text-3xl font-black text-white italic tracking-tighter">- R$ {{ number_format($quebraTotal, 2, ',', '.') }}</h3>
            </div>

            <div class="bg-emerald-600 p-8 rounded-[2.5rem] shadow-xl shadow-emerald-600/20 flex flex-col justify-center group overflow-hidden relative">
                <div class="absolute -right-4 -bottom-4 text-7xl opacity-10 italic font-black group-hover:rotate-12 transition-transform">OK</div>
                <p class="text-[10px] font-black text-emerald-100 uppercase tracking-widest mb-1">Status Operacional</p>
                <h3 class="text-3xl font-black text-white italic tracking-tighter">EFICIENTE ✅</h3>
            </div>
        </div>

        {{-- 📟 TABELA DE AUDITORIA --}}
        <div class="bg-gray-900/40 border-2 border-gray-800 rounded-[3rem] overflow-hidden shadow-2xl">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-black/40 border-b border-gray-800">
                        <th class="p-8 text-[10px] font-black text-gray-500 uppercase tracking-widest uppercase">Início / Operador</th>
                        <th class="p-8 text-[10px] font-black text-gray-500 uppercase tracking-widest text-right">Fundo</th>
                        <th class="p-8 text-[10px] font-black text-gray-500 uppercase tracking-widest text-right">Vendas</th>
                        <th class="p-8 text-[10px] font-black text-gray-500 uppercase tracking-widest text-right">Esperado</th>
                        <th class="p-8 text-[10px] font-black text-white uppercase tracking-widest text-right italic">Real Contado</th>
                        <th class="p-8 text-[10px] font-black text-orange-500 uppercase tracking-widest text-center italic">Status / Diferença</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50">
                    @forelse($sessoes as $index => $sessao)
                        @php
                            $diferenca = 0;
                            if ($sessao->status == 'closed') {
                                $diferenca = $sessao->closing_balance - $sessao->total_sistema_esperado;
                            }
                        @endphp
                        <tr class="{{ $index == 0 ? 'bg-white/[0.01]' : '' }} hover:bg-white/[0.03] transition-colors group">
                            <td class="p-8">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-black rounded-2xl flex items-center justify-center border border-gray-800 group-hover:border-emerald-500 transition-all text-xl shadow-inner">
                                        👤
                                    </div>
                                    <div>
                                        <span class="text-white font-black block uppercase italic tracking-tighter text-sm">{{ $sessao->user->name ?? 'N/A' }}</span>
                                        <span class="text-[10px] text-gray-600 font-bold uppercase tracking-widest">
                                            {{ $sessao->opened_at->format('d/m/Y H:i') }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="p-8 text-right font-bold text-gray-600 text-xs font-mono">
                                R$ {{ number_format($sessao->opening_balance, 2, ',', '.') }}
                            </td>
                            <td class="p-8 text-right font-black text-blue-400 text-sm font-mono italic">
                                R$ {{ number_format($sessao->vendas_turno, 2, ',', '.') }}
                            </td>
                            <td class="p-8 text-right font-bold text-gray-400 text-xs italic font-mono">
                                R$ {{ number_format($sessao->total_sistema_esperado, 2, ',', '.') }}
                            </td>
                            <td class="p-8 text-right">
                                @if ($sessao->status == 'closed')
                                    <span class="text-xl font-black text-white italic tracking-tighter font-mono">
                                        R$ {{ number_format($sessao->closing_balance, 2, ',', '.') }}
                                    </span>
                                @else
                                    <span class="px-4 py-1.5 bg-orange-500/10 text-orange-500 text-[10px] font-black uppercase rounded-full border border-orange-500/20 animate-pulse italic">Turno em Aberto</span>
                                @endif
                            </td>
                            <td class="p-8 text-center">
                                @if ($sessao->status == 'closed')
                                    @if (abs($diferenca) < 0.1)
                                        <span class="px-4 py-1.5 bg-emerald-500/10 text-emerald-500 text-[10px] font-black uppercase rounded-xl border border-emerald-500/20 italic">Bateu ✅</span>
                                    @elseif($diferenca > 0)
                                        <div class="flex flex-col items-center">
                                            <span class="text-blue-400 font-black italic tracking-tighter text-lg font-mono">+ R$ {{ number_format($diferenca, 2, ',', '.') }}</span>
                                            <span class="text-[8px] text-blue-500/50 uppercase font-black">Sobra</span>
                                        </div>
                                    @else
                                        <div class="flex flex-col items-center">
                                            <span class="text-red-500 font-black italic tracking-tighter text-lg font-mono">- R$ {{ number_format(abs($diferenca), 2, ',', '.') }}</span>
                                            <span class="text-[8px] text-red-600/50 uppercase font-black tracking-widest">Quebra de Caixa</span>
                                        </div>
                                    @endif
                                @else
                                    <span class="text-gray-800 italic text-xs">Pendente...</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-32 text-center">
                                <p class="text-gray-600 text-xs font-black uppercase tracking-[0.4em] italic opacity-20">Nenhum turno registrado</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- 💡 LEGENDAS E AUDITORIA --}}
        <div class="grid grid-cols-1 mt-10 md:grid-cols-2 gap-8 mt-12">
            <div class="p-10 bg-gray-900/40 border border-gray-800 rounded-[3rem] relative overflow-hidden group">
                <div class="absolute right-0 top-0 p-8 opacity-5 text-6xl group-hover:scale-110 transition-transform">⚖️</div>
                <h4 class="text-emerald-500 text-[10px] font-black uppercase tracking-widest mb-4 italic">Auditoria de Precisão</h4>
                <p class="text-[11px] text-gray-500 font-bold uppercase leading-relaxed">
                    Este relatório ignora falhas pontuais e <span class="text-white">recalcula o Total Esperado</span> em tempo real, cruzando dados de Mesas e PDV. Caso o valor contado seja diferente, a diferença é exposta imediatamente.
                </p>
            </div>
            <div class="p-10 bg-gray-900/40 border border-gray-800 rounded-[3rem] relative overflow-hidden group">
                <div class="absolute right-0 top-0 p-8 opacity-5 text-6xl group-hover:scale-110 transition-transform">🚩</div>
                <h4 class="text-orange-500 text-[10px] font-black uppercase tracking-widest mb-4 italic">Alerta de Quebra</h4>
                <p class="text-[11px] text-gray-500 font-bold uppercase leading-relaxed">
                    Diferenças de centavos são toleradas por arredondamento. <span class="text-orange-400 italic font-black">Faltas repetitivas</span> devem ser investigadas no log de cancelamentos para evitar prejuízo operacional.
                </p>
            </div>
        </div>

    </div>
</x-bar-layout>
