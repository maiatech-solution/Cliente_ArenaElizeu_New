<x-bar-layout>
    @php
        // 1. CÁLCULOS TÉCNICOS INICIAIS
        $totalGeralMes = 0;
        $lucroGeralMes = 0;
        $maxValorDia = 0;
        $mesAtualFiltro = date('m', strtotime($mesReferencia));

        // Primeiro Loop: Cálculos de Totais para os Cards
        foreach ($datas as $dia => $valores) {
            $carbonDia = \Carbon\Carbon::parse($dia);
            if ($carbonDia->format('m') == $mesAtualFiltro) {
                $vendaDia = (float)($valores['mesas'] ?? 0) + (float)($valores['pdv'] ?? 0);
                $lucroDia = (float)($valores['lucro_mesas'] ?? 0) + (float)($valores['lucro_pdv'] ?? 0);

                $totalGeralMes += $vendaDia;
                $lucroGeralMes += $lucroDia;

                if ($vendaDia > $maxValorDia) $maxValorDia = $vendaDia;
            }
        }

        // Média baseada em dias que tiveram QUALQUER atividade
        $diasComAtividade = collect($datas)->filter(function($d) use ($mesAtualFiltro) {
             return (($d['mesas'] ?? 0) + ($d['pdv'] ?? 0) > 0) ||
                    ((($d['lucro_mesas'] ?? 0) + ($d['lucro_pdv'] ?? 0)) != 0);
        })->count();

        $mediaDiaria = $diasComAtividade > 0 ? $totalGeralMes / $diasComAtividade : 0;

        $diasSemana = ['Sunday' => 'Dom', 'Monday' => 'Seg', 'Tuesday' => 'Ter', 'Wednesday' => 'Qua', 'Thursday' => 'Qui', 'Friday' => 'Sex', 'Saturday' => 'Sáb'];
        $diasSemanaFull = ['Sunday' => 'Domingo', 'Monday' => 'Segunda-feira', 'Tuesday' => 'Terça-feira', 'Wednesday' => 'Quarta-feira', 'Thursday' => 'Quinta-feira', 'Friday' => 'Sexta-feira', 'Saturday' => 'Sábado'];
    @endphp

    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-8 italic font-sans">

        {{-- 🛰️ CABEÇALHO --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-6">
            <div class="flex items-center gap-5">
                <a href="{{ route('bar.reports.index') }}" class="p-3 bg-gray-900 hover:bg-gray-800 text-orange-500 rounded-2xl border border-gray-800 transition-all shadow-lg group">
                    <span class="group-hover:-translate-x-1 transition-transform inline-block">◀</span>
                </a>
                <div>
                    <h1 class="text-4xl font-black text-white uppercase tracking-tighter italic leading-none">Resumo <span class="text-orange-600">Diário</span></h1>
                    <p class="text-gray-500 font-bold text-[10px] uppercase tracking-[0.2em] mt-1">Análise de rentabilidade Maiatech</p>
                </div>
            </div>
            <form action="{{ route('bar.reports.daily') }}" method="GET" class="bg-gray-900 p-1 rounded-2xl border border-gray-800 shadow-xl">
                <input type="month" name="mes_referencia" value="{{ $mesReferencia }}" onchange="this.form.submit()" class="bg-transparent border-none p-2 font-black text-orange-500 uppercase text-xs focus:ring-0 cursor-pointer">
            </form>
        </div>

        {{-- 📊 CARDS --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
            <div class="bg-gray-900 p-6 rounded-[2rem] border border-gray-800 border-l-4 border-l-blue-500 shadow-2xl">
                <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1">Recorde Diário</p>
                <h3 class="text-3xl font-black text-white italic tracking-tighter font-mono">R$ {{ number_format($maxValorDia, 2, ',', '.') }}</h3>
            </div>
            <div class="bg-gray-900 p-6 rounded-[2rem] border border-gray-800 border-l-4 border-l-orange-500 shadow-2xl">
                <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1">Média Diária</p>
                <h3 class="text-3xl font-black text-white italic tracking-tighter font-mono">R$ {{ number_format($mediaDiaria, 2, ',', '.') }}</h3>
            </div>
            <div class="bg-gray-900 p-6 rounded-[2rem] border border-gray-800 border-l-4 border-l-gray-600 shadow-2xl">
                <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1">Total Acumulado</p>
                <h3 class="text-3xl font-black text-white italic tracking-tighter font-mono opacity-40">R$ {{ number_format($totalGeralMes, 2, ',', '.') }}</h3>
            </div>
            <div class="bg-emerald-600 p-6 rounded-[2rem] shadow-xl shadow-emerald-600/20 flex flex-col justify-center relative overflow-hidden group">
                <div class="absolute -right-2 -top-2 opacity-10 text-5xl font-black text-white">LUCRO</div>
                <p class="text-[10px] font-black text-emerald-100 uppercase tracking-widest mb-1 font-bold">Lucro Líquido Real</p>
                <h3 class="text-3xl font-black text-white italic tracking-tighter font-mono">R$ {{ number_format($lucroGeralMes, 2, ',', '.') }}</h3>
                <span class="text-[8px] text-emerald-100 font-black uppercase mt-1">Margem Global: {{ $totalGeralMes > 0 ? number_format(($lucroGeralMes / $totalGeralMes) * 100, 1) : 0 }}%</span>
            </div>
        </div>

    {{-- 📈 GRÁFICO DE PERFORMANCE DIÁRIA --}}
<div class="bg-gray-900 border border-gray-800 rounded-[3rem] p-8 mb-10 shadow-2xl relative overflow-hidden text-white italic">
    <div class="flex justify-between items-center mb-10">
        <div>
            <h2 class="text-xs font-black text-gray-400 uppercase tracking-widest italic leading-none">
                Performance de Vendas Diárias</h2>
            <p class="text-[8px] text-gray-600 uppercase font-bold mt-1 tracking-widest">Comparativo entre
                faturamento bruto e lucro líquido</p>
        </div>

        <div class="bg-black/40 px-4 py-2 rounded-full border border-gray-800">
            <span class="text-[9px] font-black text-orange-500 uppercase font-mono italic">Ref:
                {{ $mesAtualFiltro }}/{{ date('Y') }}</span>
        </div>
    </div>

    {{-- 🛠️ REMOVIDO: overflow-x-auto e no-scrollbar para evitar o scroll --}}
    <div class="flex items-end justify-between gap-1 h-64 px-2 border-b border-gray-800/50 pb-2 font-mono">

        @php
            $maxReal = collect($datas)
                ->map(function ($d) {
                    return (float) ($d['mesas'] ?? 0) + (float) ($d['pdv'] ?? 0);
                })
                ->max() ?: 1;

            $maxSeguranca = $maxReal * 1.2;
        @endphp

        @foreach ($datas as $dia => $valores)
            @php
                $carbonDia = \Carbon\Carbon::parse($dia);
                $totalDia = (float) ($valores['mesas'] ?? 0) + (float) ($valores['pdv'] ?? 0);
                $lucroDia = (float) ($valores['lucro_mesas'] ?? 0) + (float) ($valores['lucro_pdv'] ?? 0);
                $alturaTotal = ($totalDia / $maxSeguranca) * 100;
                $porcentagemLucro = $totalDia > 0 ? ($lucroDia / $totalDia) * 100 : 0;
            @endphp

            @if ($carbonDia->format('m') == $mesAtualFiltro)
                {{-- 🛠️ AJUSTE: Removido o min-w-[35px] para o gráfico se ajustar à largura total --}}
                <div class="flex-1 flex flex-col items-center group relative">

                    {{-- 🎈 TOOLTIP GOURMET --}}
                    <div class="absolute -top-16 bg-gray-950 border border-gray-800 text-white text-[9px] font-black px-3 py-2 rounded-xl shadow-2xl opacity-0 group-hover:opacity-100 transition-all z-20 whitespace-nowrap pointer-events-none italic">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span>
                            <span>Venda: R$ {{ number_format($totalDia, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-emerald-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span>Lucro: R$ {{ number_format($lucroDia, 2, ',', '.') }}</span>
                        </div>
                        <div class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-gray-950 border-r border-b border-gray-800 rotate-45">
                        </div>
                    </div>

                    {{-- 📊 A BARRA EMPILHADA --}}
                    {{-- 🛠️ AJUSTE: w-full em vez de max-w-[22px] para as barras usarem o espaço disponível --}}
                    <div class="w-full max-w-[18px] rounded-t-sm transition-all duration-700 relative z-10 flex flex-col justify-end overflow-hidden
                        {{ $carbonDia->isToday() ? 'ring-1 ring-orange-500 shadow-[0_0_15px_rgba(234,88,12,0.3)]' : '' }}
                        {{ $totalDia > 0 ? 'bg-gray-800' : 'bg-gray-800/10' }}"
                        style="height: {{ max($alturaTotal, 2) }}%; min-height: {{ $totalDia > 0 ? '4px' : '2px' }};">

                        @if ($totalDia > 0)
                            <div class="w-full bg-orange-600/30 flex-1 relative group-hover:bg-orange-600/50 transition-colors">
                                <div class="absolute top-0 left-0 w-full h-[1px] bg-white/20"></div>
                            </div>

                            <div class="w-full bg-emerald-500 relative"
                                style="height: {{ $porcentagemLucro }}%">
                                <div class="w-full h-[1px] bg-white/30"></div>
                            </div>
                        @endif
                    </div>

                    {{-- Labels (Data e Dia) --}}
                    <span class="mt-3 text-[8px] font-black {{ $carbonDia->isToday() ? 'text-orange-500 underline' : 'text-gray-500' }}">
                        {{ $carbonDia->format('d') }}
                    </span>
                    <span class="text-[5px] font-black uppercase text-gray-700 tracking-tighter italic leading-none mt-1">
                        {{ $diasSemana[$carbonDia->format('l')] ?? '' }}
                    </span>
                </div>
            @endif
        @endforeach
    </div>
</div>

        {{-- 📋 TABELA (ESTA É A PARTE QUE CORRIGE AS LINHAS FALTANTES) --}}
        <div class="bg-gray-900 border border-gray-800 rounded-[3rem] shadow-2xl overflow-hidden">
            <div class="p-8 border-b border-gray-800 bg-black/20 flex justify-between items-center font-mono uppercase italic text-[10px] font-black text-white">
                <h2>Detalhamento Financeiro</h2>
            </div>
            <div class="overflow-x-auto no-scrollbar">
                <table class="w-full text-left border-collapse font-mono italic">
                    <thead>
                        <tr class="bg-black/40 text-gray-500 text-[10px] font-black uppercase tracking-widest">
                            <th class="p-8">Data / Dia</th>
                            <th class="p-8 text-right">Mesas</th>
                            <th class="p-8 text-right">PDV</th>
                            <th class="p-8 text-right text-white">Lucro Líquido</th>
                            <th class="p-8 text-right text-white">Total Bruto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @foreach (array_reverse($datas, true) as $dia => $valores)
                            @php
                                $totalDia = (float)($valores['mesas'] ?? 0) + (float)($valores['pdv'] ?? 0);
                                $lucroDia = (float)($valores['lucro_mesas'] ?? 0) + (float)($valores['lucro_pdv'] ?? 0);
                                $carbon = \Carbon\Carbon::parse($dia);
                            @endphp

                            {{-- CONDIÇÃO AMPLIADA: Mostra se vendeu OU teve lucro/prejuízo OU é hoje --}}
                            @if ($carbon->format('m') == $mesAtualFiltro && ($totalDia != 0 || $lucroDia != 0 || $carbon->isToday()))
                                <tr class="hover:bg-white/[0.02] transition-colors group">
                                    <td class="p-8">
                                        <div class="flex items-center gap-4">
                                            <div class="w-14 h-14 rounded-2xl bg-black border border-gray-800 flex flex-col items-center justify-center text-base font-black {{ $carbon->isToday() ? 'text-orange-500 border-orange-600' : 'text-gray-500' }}">
                                                <span>{{ $carbon->format('d') }}</span>
                                                <span class="text-[8px]">{{ $diasSemana[$carbon->format('l')] }}</span>
                                            </div>
                                            <div>
                                                <span class="text-white font-black block uppercase text-base">{{ $diasSemanaFull[$carbon->format('l')] }}</span>
                                                <span class="text-[10px] text-gray-600 font-bold uppercase">{{ $carbon->format('d/m/Y') }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-8 text-right font-black text-blue-400 text-xs italic">R$ {{ number_format($valores['mesas'] ?? 0, 2, ',', '.') }}</td>
                                    <td class="p-8 text-right font-black text-orange-400 text-xs italic">R$ {{ number_format($valores['pdv'] ?? 0, 2, ',', '.') }}</td>
                                    <td class="p-8 text-right bg-black/10">
                                        <span class="{{ $lucroDia < 0 ? 'text-red-500' : 'text-emerald-500' }} text-xl font-black italic tracking-tighter leading-none">
                                            R$ {{ number_format($lucroDia, 2, ',', '.') }}
                                        </span>
                                        <p class="text-[7px] font-black uppercase mt-1 {{ $lucroDia < 0 ? 'text-red-700' : 'text-emerald-700' }}">
                                            {{ $lucroDia < 0 ? 'Prejuízo' : 'Margem: '.($totalDia > 0 ? number_format(($lucroDia / $totalDia) * 100, 1) : 0).'%' }}
                                        </p>
                                    </td>
                                    <td class="p-8 text-right">
                                        <span class="text-3xl font-black text-white italic tracking-tighter leading-none">R$ {{ number_format($totalDia, 2, ',', '.') }}</span>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-bar-layout>
