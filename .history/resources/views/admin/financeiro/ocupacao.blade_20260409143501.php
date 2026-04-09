<x-app-layout>
    <style>
        @media print {

            /* Mantém seu código original */
            .break-inside-avoid {
                page-break-inside: avoid;
            }

            body {
                background-color: white !important;
            }

            .print\:hidden {
                display: none !important;
            }

            /* As duas linhas mágicas para não cortar páginas extras */
            html,
            body {
                height: auto !important;
                overflow: visible !important;
            }

            .sticky {
                position: relative !important;
            }
        }
    </style>

    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-4">
                {{-- BOTÃO VOLTAR PRESERVANDO O CONTEXTO --}}
                <a href="{{ route('admin.financeiro.dashboard', ['arena_id' => request('arena_id')]) }}"
                    class="flex items-center gap-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-all font-bold text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Painel
                </a>
                <h2 class="font-black text-xl text-gray-800 dark:text-gray-200 uppercase tracking-tighter italic">
                    {{-- Melhoria: Proteção Null Safe --}}
                    🗓️ Ocupação:
                    {{ request('arena_id') ? \App\Models\Arena::find(request('arena_id'))?->name ?? 'Unidade não encontrada' : 'Todas as Unidades' }}
                </h2>
            </div>
            <button onclick="window.print()"
                class="print:hidden bg-black text-white px-6 py-2 rounded-full font-bold text-xs uppercase tracking-widest hover:bg-gray-800 transition-all shadow-lg">
                🖨️ Imprimir Mapa
            </button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- 🔍 FILTRO DE BUSCA AVANÇADA --}}
            <div
                class="bg-white dark:bg-gray-800 p-6 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700 print:hidden">
                <form id="ocupacaoFilterForm" method="GET" action="{{ route('admin.financeiro.relatorio_ocupacao') }}"
                    class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">

                    {{-- Seleção de Arena --}}
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-2 italic">🏟️ Unidade:</label>
                        <select name="arena_id" onchange="this.form.submit()"
                            class="w-full rounded-2xl border-none bg-gray-100 dark:bg-gray-900 dark:text-gray-300 focus:ring-2 focus:ring-indigo-500 font-bold text-sm">
                            <option value="">Todas as Arenas</option>
                            @foreach (\App\Models\Arena::all() as $arena)
                                <option value="{{ $arena->id }}"
                                    {{ request('arena_id') == $arena->id ? 'selected' : '' }}>{{ $arena->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-2 italic">📅 De:</label>
                        <input type="date" name="data_inicio" value="{{ $dataInicio->format('Y-m-d') }}"
                            onchange="this.form.submit()"
                            class="w-full rounded-2xl border-none bg-gray-100 dark:bg-gray-900 dark:text-gray-300 focus:ring-2 focus:ring-indigo-500 font-bold text-sm">
                    </div>

                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase ml-2 italic">📅 Até:</label>
                        <input type="date" name="data_fim" value="{{ $dataFim->format('Y-m-d') }}"
                            onchange="this.form.submit()"
                            class="w-full rounded-2xl border-none bg-gray-100 dark:bg-gray-900 dark:text-gray-300 focus:ring-2 focus:ring-indigo-500 font-bold text-sm">
                    </div>

                    <div
                        class="bg-indigo-50 dark:bg-indigo-900/20 p-3 rounded-2xl text-center border border-indigo-100 dark:border-indigo-800">
                        <span class="text-[10px] font-black text-indigo-600 dark:text-indigo-400 uppercase italic">Total
                            no Período:</span>
                        <span class="ml-2 font-black text-indigo-900 dark:text-white">{{ $reservas->count() }}
                            Jogos</span>
                    </div>
                </form>
            </div>

            {{-- 📄 TIMELINE DE JOGOS --}}
            <div id="reportContent" class="space-y-8 transition-opacity duration-300">
                @forelse($reservas->groupBy('date') as $data => $lista)
                    <div class="relative break-inside-avoid">

                        {{-- IDENTIFICADOR DO DIA --}}
                        <div
                            class="sticky top-0 z-10 flex items-center gap-4 mb-4 py-2 bg-gray-50/80 dark:bg-gray-900/80 backdrop-blur-md print:bg-white">
                            <div
                                class="bg-white dark:bg-gray-800 px-4 py-2 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 text-center min-w-[70px]">
                                <span
                                    class="block text-2xl font-black text-gray-800 dark:text-white leading-none italic">{{ \Carbon\Carbon::parse($data)->format('d') }}</span>
                                <span
                                    class="text-[10px] font-black text-indigo-600 uppercase">{{ \Carbon\Carbon::parse($data)->locale('pt_BR')->translatedFormat('M') }}</span>
                            </div>
                            <div>
                                <h3
                                    class="font-black text-gray-800 dark:text-white uppercase tracking-tight text-lg italic">
                                    {{ \Carbon\Carbon::parse($data)->locale('pt_BR')->translatedFormat('l') }}
                                </h3>
                                <p class="text-[10px] text-gray-400 font-bold uppercase">{{ $lista->count() }} Partidas
                                    Realizadas/Confirmadas</p>
                            </div>
                        </div>

                        {{-- GRID DE JOGOS DO DIA --}}
                        <div
                            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 pl-4 border-l-2 border-gray-200 dark:border-gray-700 ml-8">
                            @foreach ($lista as $r)
                                @php
                                    // 1. Definição de variáveis financeiras
                                    $totalPago = (float) ($r->total_paid ?? 0);
                                    $precoFinal = (float) ($r->final_price ?? $r->price);
                                    $statusReserva = $r->status;

                                    // 2. Verificação de Voucher (Garante que o Maia veja o que é cortesia)
                                    $hasVoucher = $r->transactions->where('payment_method', 'voucher')->count() > 0;

                                    // 3. Classe dinâmica para o fundo do card
                                    $cardBgClass = $hasVoucher
                                        ? 'bg-amber-50/50 border-amber-100 dark:bg-amber-900/10 dark:border-amber-900/30'
                                        : 'bg-white dark:bg-gray-800 border-gray-100 dark:border-gray-700';
                                @endphp

                                <div
                                    class="p-4 rounded-3xl border shadow-sm hover:shadow-md transition-all relative overflow-hidden group {{ $cardBgClass }}">

                                    {{-- INDICADOR DE STATUS FINANCEIRO E OPERACIONAL --}}
                                    <div class="absolute top-0 right-0">
                                        @if ($statusReserva == 'no_show')
                                            {{-- Caso de Falta --}}
                                            <span
                                                class="bg-red-600 text-white text-[8px] font-black px-3 py-1 rounded-bl-xl uppercase tracking-widest shadow-sm">
                                                Falta (No-Show)
                                            </span>
                                        @elseif($statusReserva == 'rejected')
                                            {{-- Caso de Rejeitada --}}
                                            <span
                                                class="bg-gray-700 text-white text-[8px] font-black px-3 py-1 rounded-bl-xl uppercase tracking-widest shadow-sm">
                                                Rejeitada
                                            </span>
                                        @elseif($hasVoucher)
                                            {{-- Caso de Cortesia --}}
                                            <span
                                                class="bg-amber-500 text-white text-[8px] font-black px-3 py-1 rounded-bl-xl uppercase tracking-widest shadow-sm italic">
                                                🎟️ Cortesia
                                            </span>
                                        @elseif($totalPago >= $precoFinal && $precoFinal > 0)
                                            {{-- Pago Total --}}
                                            <span
                                                class="bg-emerald-500 text-white text-[8px] font-black px-3 py-1 rounded-bl-xl uppercase tracking-widest shadow-sm">
                                                Pago
                                            </span>
                                        @elseif($totalPago > 0)
                                            {{-- Pago Parcial --}}
                                            <span
                                                class="bg-blue-500 text-white text-[8px] font-black px-3 py-1 rounded-bl-xl uppercase tracking-widest shadow-sm">
                                                Parcial
                                            </span>
                                        @else
                                            {{-- Pendente --}}
                                            <span
                                                class="bg-gray-400 text-white text-[8px] font-black px-3 py-1 rounded-bl-xl uppercase tracking-widest shadow-sm">
                                                Pendente
                                            </span>
                                        @endif
                                    </div>

                                    {{-- INFO DO JOGO --}}
                                    <div class="text-xs font-black text-indigo-600 mb-1 flex items-center gap-1 italic">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        {{ \Carbon\Carbon::parse($r->start_time)->format('H:i') }}h
                                    </div>

                                    <div
                                        class="font-black text-gray-800 dark:text-white truncate pr-6 uppercase text-sm leading-tight mb-4 italic">
                                        {{ $r->client_name }}
                                    </div>

                                    {{-- RODAPÉ DO CARD --}}
                                    <div
                                        class="flex items-center justify-between border-t border-gray-50 dark:border-gray-700 pt-3">
                                        <div
                                            class="text-[9px] font-black text-gray-400 uppercase tracking-tighter italic">
                                            Arena:
                                            <span
                                                class="text-indigo-600 dark:text-indigo-400 font-bold bg-indigo-50 dark:bg-indigo-900/30 px-1.5 py-0.5 rounded-md">
                                                {{ $r->arena?->name ?? '---' }}
                                            </span>
                                        </div>

                                        <div
                                            class="text-[10px] font-mono font-black italic {{ $hasVoucher ? 'text-amber-600' : 'text-gray-700 dark:text-gray-300' }}">
                                            R$ {{ number_format($precoFinal, 2, ',', '.') }}
                                            @if ($hasVoucher)
                                                <span
                                                    class="text-[7px] block text-right uppercase leading-none mt-0.5 font-sans">Via
                                                    Voucher</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div
                        class="text-center py-32 bg-white dark:bg-gray-800 rounded-[3rem] border-2 border-dashed border-gray-200 dark:border-gray-700">
                        <div class="text-5xl mb-4">🏜️</div>
                        <p class="text-gray-400 font-black uppercase tracking-widest text-xs italic">
                            Nenhuma atividade registrada para este intervalo de tempo.
                        </p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <script>
        document.getElementById('ocupacaoFilterForm').addEventListener('change', () => {
            document.getElementById('reportContent').style.opacity = '0.3';
        });
    </script>
</x-app-layout>
