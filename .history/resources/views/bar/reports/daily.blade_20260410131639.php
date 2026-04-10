<x-bar-layout>
    @php
        // 1. CÁLCULOS TÉCNICOS INICIAIS
        $totalGeralMes = 0;
        $lucroGeralMes = 0;
        $maxValorDia = 0;
        $mesAtualFiltro = date('m', strtotime($mesReferencia));

        foreach ($datas as $dia => $valores) {
            $carbonDia = \Carbon\Carbon::parse($dia);
            if ($carbonDia->format('m') == $mesAtualFiltro) {
                // Faturamento
                $vendaDia = ($valores['mesas'] ?? 0) + ($valores['pdv'] ?? 0);

                // 🎯 BUSCA DO LUCRO (Sincronizado com a lógica do Ranking de Produtos)
                // Se a sua Controller ainda não envia 'lucro_mesas', o sistema usará 0 por segurança
                $lucroDia = ($valores['lucro_mesas'] ?? 0) + ($valores['lucro_pdv'] ?? 0);

                $totalGeralMes += $vendaDia;
                $lucroGeralMes += $lucroDia;

                if ($vendaDia > $maxValorDia) {
                    $maxValorDia = $vendaDia;
                }
            }
        }

        $diasComVenda = collect($datas)->filter(fn($d) => ($d['mesas'] ?? 0) + ($d['pdv'] ?? 0) > 0)->count();
        $mediaDiaria = $diasComVenda > 0 ? $totalGeralMes / $diasComVenda : 0;

        $diasSemana = [
            'Sunday' => 'Dom',
            'Monday' => 'Seg',
            'Tuesday' => 'Ter',
            'Wednesday' => 'Qua',
            'Thursday' => 'Qui',
            'Friday' => 'Sex',
            'Saturday' => 'Sáb',
        ];
        $diasSemanaFull = [
            'Sunday' => 'Domingo',
            'Monday' => 'Segunda-feira',
            'Tuesday' => 'Terça-feira',
            'Wednesday' => 'Quarta-feira',
            'Thursday' => 'Quinta-feira',
            'Friday' => 'Sexta-feira',
            'Saturday' => 'Sábado',
        ];
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
                    <p class="text-gray-500 font-bold text-[10px] uppercase tracking-[0.2em] mt-1 italic">
                        Análise de faturamento e rentabilidade líquida
                    </p>
                </div>
            </div>

            <form action="{{ route('bar.reports.daily') }}" method="GET"
                class="bg-gray-900 p-1 rounded-2xl border border-gray-800 shadow-xl">
                <input type="month" name="mes_referencia" value="{{ $mesReferencia }}" onchange="this.form.submit()"
                    class="bg-transparent border-none p-2 font-black text-orange-500 uppercase text-xs focus:ring-0 cursor-pointer">
            </form>
        </div>

        {{-- 📊 CARDS DE RESUMO RÁPIDO NO TOPO --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
            <div class="bg-gray-900 p-6 rounded-[2rem] border border-gray-800 border-l-4 border-l-blue-500 shadow-2xl">
                <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1 italic">Recorde Diário</p>
                <h3 class="text-3xl font-black text-white italic tracking-tighter font-mono">
                    R$ {{ number_format($maxValorDia, 2, ',', '.') }}
                </h3>
            </div>

            <div
                class="bg-gray-900 p-6 rounded-[2rem] border border-gray-800 border-l-4 border-l-orange-500 shadow-2xl">
                <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1 italic">Média Diária</p>
                <h3 class="text-3xl font-black text-white italic tracking-tighter font-mono">
                    R$ {{ number_format($mediaDiaria, 2, ',', '.') }}
                </h3>
            </div>

            <div class="bg-gray-900 p-6 rounded-[2rem] border border-gray-800 border-l-4 border-l-gray-600 shadow-2xl">
                <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1 italic">Total Acumulado
                </p>
                <h3 class="text-3xl font-black text-white italic tracking-tighter font-mono opacity-40">
                    R$ {{ number_format($totalGeralMes, 2, ',', '.') }}
                </h3>
            </div>

            <div
                class="bg-emerald-600 p-6 rounded-[2rem] shadow-xl shadow-emerald-600/20 flex flex-col justify-center relative overflow-hidden group">
                <div
                    class="absolute -right-2 -top-2 opacity-10 text-5xl group-hover:scale-110 transition-transform italic font-black">
                    LUCRO</div>
                <p class="text-[10px] font-black text-emerald-100 uppercase tracking-widest mb-1 italic font-bold">Lucro
                    Líquido Real</p>
                <h3 class="text-3xl font-black text-white italic tracking-tighter font-mono">
                    R$ {{ number_format($lucroGeralMes, 2, ',', '.') }}
                </h3>
                <span class="text-[8px] text-emerald-100 font-black uppercase mt-1 tracking-widest">
                    Margem Global:
                    {{ $totalGeralMes > 0 ? number_format(($lucroGeralMes / $totalGeralMes) * 100, 1) : 0 }}%
                </span>
            </div>
        </div>

        {{-- 📈 GRÁFICO DE BARRAS COM DEBUG ATIVO --}}
        <div
            class="bg-gray-900 border border-gray-800 rounded-[3rem] p-8 mb-10 shadow-2xl relative overflow-hidden text-white">
            <div class="flex justify-between items-center mb-10">
                <h2 class="text-xs font-black text-gray-400 uppercase tracking-widest italic">Performance de Vendas
                    Diárias</h2>

                {{-- 🛠️ MINI PAINEL DE DEBUG (Remova após funcionar) --}}
                <div
                    class="flex gap-4 text-[8px] font-mono text-orange-500 bg-black/40 px-4 py-2 rounded-full border border-gray-800">
                    <span>MAX DETECTADO: R$ {{ number_format($maxValorDia, 2) }}</span>
                    <span>MES FILTRO: {{ $mesAtualFiltro }}</span>
                </div>
            </div>

            <div
                class="flex items-end justify-between gap-1 h-64 px-2 border-b border-gray-800/50 pb-2 overflow-x-auto no-scrollbar font-mono">

                {{-- Forçamos o recálculo do MAX aqui dentro caso a variável global tenha falhado --}}
                @php
                    $maxSeguranca =
                        collect($datas)->max(function ($d) {
                            return ($d['mesas'] ?? 0) + ($d['pdv'] ?? 0);
                        }) ?:
                        1;
                @endphp

                @foreach ($datas as $dia => $valores)
                    @php
                        $carbonDia = \Carbon\Carbon::parse($dia);
                        $totalDia = (float) ($valores['mesas'] ?? 0) + (float) ($valores['pdv'] ?? 0);

                        // Usamos o MAX de segurança calculado na hora
                        $altura = ($totalDia / $maxSeguranca) * 100;
                    @endphp

                    {{-- 🎯 DEBUG: Se o dia for 08, vamos ver o que o PHP está lendo --}}
                    @if ($carbonDia->format('d') == '08')
                    @endif

                    @if ($carbonDia->format('m') == $mesAtualFiltro)
                        <div class="flex-1 min-w-[35px] flex flex-col items-center group relative">

                            {{-- Tooltip --}}
                            <div
                                class="absolute -top-12 bg-orange-600 text-white text-[10px] font-black px-3 py-1.5 rounded-lg shadow-2xl opacity-0 group-hover:opacity-100 transition-all z-20 whitespace-nowrap pointer-events-none">
                                R$ {{ number_format($totalDia, 2, ',', '.') }}
                                <div
                                    class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-orange-600 rotate-45">
                                </div>
                            </div>

                            {{-- Barra --}}
                            <div class="w-full max-w-[22px] rounded-t-lg transition-all duration-700 relative
                        {{ $carbonDia->isToday() ? 'bg-orange-500 shadow-[0_0_25px_rgba(234,88,12,0.5)]' : ($totalDia > 0 ? 'bg-gray-700 group-hover:bg-orange-600' : 'bg-gray-800/20') }}"
                                style="height: {{ max($altura, 3) }}%">

                                @if ($totalDia > 0)
                                    <div class="absolute top-0 left-0 w-full h-[2px] bg-white/30 rounded-t-lg"></div>
                                @endif
                            </div>

                            <span
                                class="mt-3 text-[9px] font-black {{ $carbonDia->isToday() ? 'text-orange-500 underline' : 'text-gray-500' }}">
                                {{ $carbonDia->format('d') }}
                            </span>
                            <span
                                class="text-[6px] font-black uppercase text-gray-700 tracking-tighter italic leading-none">
                                {{ $diasSemana[$carbonDia->format('l')] ?? '' }}
                            </span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- 📋 TABELA DETALHADA --}}
        <div class="bg-gray-900 border border-gray-800 rounded-[3rem] shadow-2xl overflow-hidden">
            <div class="p-8 border-b border-gray-800 bg-black/20 flex justify-between items-center font-mono">
                <h2 class="text-[10px] font-black text-white uppercase tracking-widest italic">Detalhamento Financeiro
                    do Turno</h2>
                <div class="flex gap-6">
                    <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-blue-500"></span><span
                            class="text-[8px] text-gray-500 font-black uppercase tracking-widest">Mesas</span></div>
                    <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-orange-500"></span><span
                            class="text-[8px] text-gray-500 font-black uppercase tracking-widest">PDV / Balcão</span>
                    </div>
                    <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-emerald-500"></span><span
                            class="text-[8px] text-gray-500 font-black uppercase tracking-widest italic font-bold">Lucro</span>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto no-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-black/40 text-white">
                            <th class="p-8 text-[10px] font-black text-gray-500 uppercase tracking-widest">Data / Dia da
                                Semana</th>
                            <th class="p-8 text-[10px] font-black text-gray-500 uppercase tracking-widest text-right">
                                Faturamento Mesas</th>
                            <th class="p-8 text-[10px] font-black text-gray-500 uppercase tracking-widest text-right">
                                Faturamento PDV</th>
                            <th
                                class="p-8 text-[10px] font-black text-emerald-500 uppercase tracking-widest text-right italic font-bold">
                                Lucro Líquido</th>
                            <th
                                class="p-8 text-[10px] font-black text-white uppercase tracking-widest text-right italic font-bold">
                                Total Bruto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40 font-mono">
                        @foreach (array_reverse($datas, true) as $dia => $valores)
                            @php
                                $totalDia = ($valores['mesas'] ?? 0) + ($valores['pdv'] ?? 0);
                                $lucroDia = ($valores['lucro_mesas'] ?? 0) + ($valores['lucro_pdv'] ?? 0);
                                $carbon = \Carbon\Carbon::parse($dia);
                            @endphp

                            @if ($carbon->format('m') == $mesAtualFiltro && ($totalDia > 0 || $carbon->isToday()))
                                <tr
                                    class="hover:bg-white/[0.02] transition-colors group {{ $carbon->isToday() ? 'bg-orange-600/[0.03]' : '' }}">
                                    <td class="p-8">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="w-14 h-14 rounded-2xl bg-black border border-gray-800 flex flex-col items-center justify-center text-base font-black {{ $carbon->isToday() ? 'text-orange-500 border-orange-600/30 shadow-[0_0_15px_rgba(234,88,12,0.1)]' : 'text-gray-500' }} uppercase italic shadow-inner">
                                                <span>{{ $carbon->format('d') }}</span>
                                                <span
                                                    class="text-[8px] tracking-tighter leading-none">{{ $diasSemana[$carbon->format('l')] }}</span>
                                            </div>
                                            <div>
                                                <span
                                                    class="text-white font-black block uppercase text-base tracking-tighter group-hover:text-orange-500 transition-colors">
                                                    {{ $diasSemanaFull[$carbon->format('l')] }}
                                                </span>
                                                <span
                                                    class="text-[10px] text-gray-600 font-bold uppercase tracking-[0.2em] leading-tight">{{ $carbon->format('d/m/Y') }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-8 text-right font-black text-blue-400 text-xs italic">R$
                                        {{ number_format($valores['mesas'] ?? 0, 2, ',', '.') }}</td>
                                    <td class="p-8 text-right font-black text-orange-400 text-xs italic">R$
                                        {{ number_format($valores['pdv'] ?? 0, 2, ',', '.') }}</td>
                                    <td class="p-8 text-right bg-emerald-500/[0.02]">
                                        <span class="text-xl font-black text-emerald-500 italic tracking-tighter">
                                            R$ {{ number_format($lucroDia, 2, ',', '.') }}
                                        </span>
                                        <p class="text-[7px] text-emerald-600 font-black uppercase mt-1">Margem:
                                            {{ $totalDia > 0 ? number_format(($lucroDia / $totalDia) * 100, 1) : 0 }}%
                                        </p>
                                    </td>
                                    <td class="p-8 text-right">
                                        <span class="text-3xl font-black text-white italic tracking-tighter">
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
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        input[type="month"] {
            color-scheme: dark;
        }
    </style>
</x-bar-layout>
