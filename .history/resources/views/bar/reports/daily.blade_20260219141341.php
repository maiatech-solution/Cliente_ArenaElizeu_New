<x-bar-layout>
    @php
        // 1. CÁLCULOS TÉCNICOS INICIAIS (Blindagem contra erros)
        $totalGeralMes = 0;
        $maxValorDia = 0;
        $mesAtualFiltro = date('m', strtotime($mesReferencia));

        foreach($datas as $dia => $valores) {
            $carbonDia = \Carbon\Carbon::parse($dia);
            if($carbonDia->format('m') == $mesAtualFiltro) {
                $vendaDia = ($valores['mesas'] ?? 0) + ($valores['pdv'] ?? 0);
                $totalGeralMes += $vendaDia;
                if($vendaDia > $maxValorDia) $maxValorDia = $vendaDia;
            }
        }

        $diasComVenda = collect($datas)->filter(fn($d) => (($d['mesas'] ?? 0) + ($d['pdv'] ?? 0)) > 0)->count();
        $mediaDiaria = $diasComVenda > 0 ? $totalGeralMes / $diasComVenda : 0;

        $diasSemana = ['Sunday' => 'Dom', 'Monday' => 'Seg', 'Tuesday' => 'Ter', 'Wednesday' => 'Qua', 'Thursday' => 'Qui', 'Friday' => 'Sex', 'Saturday' => 'Sáb'];
        $diasSemanaFull = ['Sunday' => 'Domingo', 'Monday' => 'Segunda-feira', 'Tuesday' => 'Terça-feira', 'Wednesday' => 'Quarta-feira', 'Thursday' => 'Quinta-feira', 'Friday' => 'Sexta-feira', 'Saturday' => 'Sábado'];
    @endphp

    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-8">

        {{-- 🛰️ CABEÇALHO --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-6">
            <div class="flex items-center gap-5">
                <a href="{{ route('bar.reports.index') }}"
                    class="p-3 bg-gray-900 hover:bg-gray-800 text-orange-500 rounded-2xl border border-gray-800 transition-all shadow-lg group">
                    <span class="group-hover:-translate-x-1 transition-transform inline-block">◀</span>
                </a>
                <div>
                    <h1 class="text-4xl font-black text-white uppercase tracking-tighter italic leading-none">
                        Resumo <span class="text-orange-600">Diário</span>
                    </h1>
                    <p class="text-gray-500 font-bold text-[10px] uppercase tracking-[0.2em] mt-1">
                        Evolução de faturamento por dia de operação
                    </p>
                </div>
            </div>

            <form action="{{ route('bar.reports.daily') }}" method="GET" class="bg-gray-900 p-1 rounded-2xl border border-gray-800 shadow-xl">
                <input type="month" name="mes_referencia" value="{{ $mesReferencia }}" onchange="this.form.submit()"
                    class="bg-transparent border-none p-2 font-black text-orange-500 uppercase text-xs focus:ring-0 cursor-pointer">
            </form>
        </div>

        {{-- 📊 CARDS DE RESUMO RÁPIDO NO TOPO --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="bg-gray-900 p-6 rounded-[2rem] border border-gray-800 border-l-4 border-l-blue-500 shadow-2xl">
                <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1 italic">Recorde Diário (Mês)</p>
                <h3 class="text-3xl font-black text-white italic tracking-tighter font-mono font-black">
                    R$ {{ number_format($maxValorDia, 2, ',', '.') }}
                </h3>
            </div>

            <div class="bg-gray-900 p-6 rounded-[2rem] border border-gray-800 border-l-4 border-l-orange-500 shadow-2xl">
                <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1 italic">Média Diária (Dias Úteis)</p>
                <h3 class="text-3xl font-black text-white italic tracking-tighter font-mono font-black">
                    R$ {{ number_format($mediaDiaria, 2, ',', '.') }}
                </h3>
            </div>

            <div class="bg-orange-600 p-6 rounded-[2rem] shadow-xl shadow-orange-600/20 flex flex-col justify-center">
                <p class="text-[10px] font-black text-orange-100 uppercase tracking-widest mb-1 italic">Total Acumulado</p>
                <h3 class="text-3xl font-black text-white italic tracking-tighter font-mono font-black">
                    R$ {{ number_format($totalGeralMes, 2, ',', '.') }}
                </h3>
            </div>
        </div>

        {{-- 📈 GRÁFICO DE BARRAS --}}
        <div class="bg-gray-900 border border-gray-800 rounded-[3rem] p-8 mb-10 shadow-2xl relative overflow-hidden">
            <div class="absolute right-0 top-0 p-10 opacity-5 text-8xl italic font-black text-white uppercase">Gráfico</div>
            <h2 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-10 italic">Performance de Vendas no Mês Selecionado</h2>

            <div class="flex items-end justify-between gap-1 h-64 px-2 border-b border-gray-800/50 pb-2 overflow-x-auto no-scrollbar">
                @php $maxChart = $maxValorDia ?: 1; @endphp
                @foreach ($datas as $dia => $valores)
                    @php
                        $totalDia = ($valores['mesas'] ?? 0) + ($valores['pdv'] ?? 0);
                        $altura = ($totalDia / $maxChart) * 100;
                        $carbonDia = \Carbon\Carbon::parse($dia);
                    @endphp

                    @if ($carbonDia->format('m') == $mesAtualFiltro)
                        <div class="flex-1 min-w-[30px] flex flex-col items-center group relative">
                            <div class="absolute -top-10 bg-orange-600 text-white text-[9px] font-black px-2 py-1 rounded shadow-xl opacity-0 group-hover:opacity-100 transition-all z-10 whitespace-nowrap">
                                R$ {{ number_format($totalDia, 2, ',', '.') }}
                            </div>

                            <div class="w-full max-w-[22px] rounded-t-md transition-all duration-500 relative
                            {{ $carbonDia->isToday() ? 'bg-orange-500 shadow-[0_0_15px_rgba(234,88,12,0.4)]' : ($totalDia > 0 ? 'bg-gray-700 group-hover:bg-orange-600/50' : 'bg-gray-800/30') }}"
                                style="height: {{ max($altura, 2) }}%">
                            </div>

                            <span class="mt-3 text-[8px] font-black {{ $carbonDia->isToday() ? 'text-orange-500' : 'text-gray-600' }}">
                                {{ $carbonDia->format('d') }}
                            </span>
                            <span class="text-[6px] font-bold uppercase text-gray-700">{{ $diasSemana[$carbonDia->format('l')] }}</span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- 📋 TABELA DETALHADA --}}
        <div class="bg-gray-900 border border-gray-800 rounded-[3rem] shadow-2xl overflow-hidden">
            <div class="p-8 border-b border-gray-800 bg-black/20 flex justify-between items-center">
                <h2 class="text-[10px] font-black text-white uppercase tracking-widest italic font-mono uppercase">Detalhamento por Dia</h2>
                <div class="flex gap-4">
                    <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.5)]"></span><span class="text-[8px] text-gray-500 font-black uppercase">Mesas</span></div>
                    <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-orange-500 shadow-[0_0_8px_rgba(234,88,12,0.5)]"></span><span class="text-[8px] text-gray-500 font-black uppercase">PDV / Balcão</span></div>
                </div>
            </div>

            <div class="overflow-x-auto no-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-black/40">
                            <th class="p-8 text-[10px] font-black text-gray-500 uppercase tracking-widest uppercase">Data / Dia da Semana</th>
                            <th class="p-8 text-[10px] font-black text-gray-500 uppercase tracking-widest text-right">Vendas Mesas</th>
                            <th class="p-8 text-[10px] font-black text-gray-500 uppercase tracking-widest text-right">Vendas PDV</th>
                            <th class="p-8 text-[10px] font-black text-white uppercase tracking-widest text-right italic font-bold">Total Diário</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @foreach (array_reverse($datas, true) as $dia => $valores)
                            @php
                                $totalDia = ($valores['mesas'] ?? 0) + ($valores['pdv'] ?? 0);
                                $carbon = \Carbon\Carbon::parse($dia);
                            @endphp

                            @if ($carbon->format('m') == $mesAtualFiltro && ($totalDia > 0 || $carbon->isToday()))
                                <tr class="hover:bg-white/[0.02] transition-colors group {{ $carbon->isToday() ? 'bg-orange-600/[0.03]' : '' }}">
                                    <td class="p-8">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 rounded-2xl bg-black border border-gray-800 flex flex-col items-center justify-center text-sm font-black {{ $carbon->isToday() ? 'text-orange-500' : 'text-gray-500' }} uppercase italic shadow-inner">
                                                <span>{{ $carbon->format('d') }}</span>
                                                <span class="text-[7px]">{{ $diasSemana[$carbon->format('l')] }}</span>
                                            </div>
                                            <div>
                                                <span class="text-white font-black block uppercase text-sm tracking-tighter group-hover:text-orange-500 transition-colors">
                                                    {{ $diasSemanaFull[$carbon->format('l')] }}
                                                </span>
                                                <span class="text-[10px] text-gray-600 font-bold uppercase tracking-widest">{{ $carbon->format('d/m/Y') }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-8 text-right font-bold text-blue-400/70 text-xs font-mono font-black">
                                        R$ {{ number_format($valores['mesas'] ?? 0, 2, ',', '.') }}
                                    </td>
                                    <td class="p-8 text-right font-bold text-orange-400/70 text-xs font-mono font-black">
                                        R$ {{ number_format($valores['pdv'] ?? 0, 2, ',', '.') }}
                                    </td>
                                    <td class="p-8 text-right">
                                        <span class="text-2xl font-black text-white italic tracking-tighter font-mono font-black">
                                            R$ {{ number_format($totalDia, 2, ',', '.') }}
                                        </span>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        input[type="month"] { color-scheme: dark; }
    </style>
</x-bar-layout>
