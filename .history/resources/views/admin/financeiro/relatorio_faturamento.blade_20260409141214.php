<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-4">
                <a href="{{ route('admin.financeiro.dashboard', ['arena_id' => request('arena_id')]) }}"
                    class="flex items-center gap-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-all font-bold text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Painel
                </a>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    💰 Faturamento:
                    {{ request('arena_id') ? \App\Models\Arena::find(request('arena_id'))?->name : 'Todas as Unidades' }}
                </h2>
            </div>
        </div>
    </x-slot>

    @php
        $traducaoMetodos = [
            // 💰 Dinheiro (Gaveta)
            'dinheiro' => 'Dinheiro',
            'money' => 'Dinheiro',
            'cash' => 'Dinheiro',
            'especie' => 'Dinheiro',

            // 📱 Digital (Banco)
            'pix' => 'PIX',
            'transfer' => 'Transferência',
            'transferencia' => 'Transferência',
            'bank' => 'Transferência',

            // 💳 Cartões
            'cartao' => 'Cartão',
            'credit_card' => 'Cartão de Crédito',
            'debit_card' => 'Cartão de Débito',
            'card' => 'Cartão',

            // ⚙️ Ajustes e Outros
            'outro' => 'Outros / Ajustes',
            'other' => 'Outros / Ajustes',
            'cash_out' => 'Saída de Caixa',
            'retained_funds' => 'Fundo Retido',
            'voucher' => 'Voucher / Cortesia',
            'cortesia' => 'Voucher / Cortesia',
        ];

        $traducaoTipos = [
            'signal' => 'Sinal',
            'payment' => 'Pagamento',
            'full_payment' => 'Total',
            'payment_settlement' => 'Acerto',
            'refund' => 'Estorno',
            'reten_noshow_comp' => 'Multa No-Show',
            'reten_canc_comp' => 'Taxa Cancelamento',
            'sangria' => 'Sangria',
            'reforco' => 'Reforço',
        ];
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- 🔍 FILTROS COM BUSCA, FLUXO E MULTIQUADRA --}}
            <div
                class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 print:hidden">
                <form id="fatFilterForm" method="GET" action="{{ route('admin.financeiro.relatorio_faturamento') }}"
                    class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">

                    <div class="md:col-span-1">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Buscar Cliente / ID</label>
                        <input type="text" name="search" value="{{ request('search') }}"
                            placeholder="Nome ou #ID..."
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-300 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Unidade</label>
                        <select name="arena_id" onchange="this.form.submit()"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-300 font-bold text-sm">
                            <option value="">Todas as Arenas</option>
                            @foreach (\App\Models\Arena::all() as $arena)
                                <option value="{{ $arena->id }}"
                                    {{ request('arena_id') == $arena->id ? 'selected' : '' }}>{{ $arena->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- NOVO: FILTRO DE FLUXO --}}
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Fluxo</label>
                        <select name="fluxo" onchange="this.form.submit()"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-300 font-bold text-sm">
                            <option value="">Todos</option>
                            <option value="entrada" {{ request('fluxo') == 'entrada' ? 'selected' : '' }}>🟢 Entradas
                            </option>
                            <option value="saida" {{ request('fluxo') == 'saida' ? 'selected' : '' }}>🔴 Saídas
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Início</label>
                        <input type="date" name="data_inicio" value="{{ $dataInicio->format('Y-m-d') }}"
                            onchange="this.form.submit()"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-300 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Fim</label>
                        <input type="date" name="data_fim" value="{{ $dataFim->format('Y-m-d') }}"
                            onchange="this.form.submit()"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-300 text-sm">
                    </div>

                    <div class="flex space-x-2">
                        <button type="button" onclick="window.print()"
                            class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-md font-bold hover:bg-gray-200 transition flex-1 text-sm uppercase">
                            🖨️
                        </button>
                    </div>
                </form>
            </div>

            {{-- 📊 CARDS DE RESUMO --}}
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                {{-- Card Principal: Faturamento Real (Dinheiro que entrou) --}}
                <div class="bg-indigo-600 p-6 rounded-xl shadow-lg text-white col-span-1 md:col-span-2">
                    <p class="text-[10px] opacity-80 font-black uppercase tracking-widest">Faturamento Real
                        (Caixa/Banco)</p>
                    <p class="text-2xl font-black mt-1 italic">R$ {{ number_format($faturamentoTotal, 2, ',', '.') }}
                    </p>
                </div>

                {{-- Iteração dos métodos --}}
              @foreach ($totaisPorMetodo as $metodo => $valor)
    {{-- 🚫 ESCONDER CARDS ZERADOS --}}
    @if ($valor != 0)
        @php
            // Estilização especial para o Voucher para destacar que é cortesia
            $isVoucher = $metodo === 'voucher';
            $cardClass = $isVoucher
                ? 'bg-amber-50 border-amber-500 dark:bg-amber-900/20'
                : 'bg-white dark:bg-gray-800 border-emerald-500';

            if ($valor < 0) {
                $cardClass = 'bg-white dark:bg-gray-800 border-red-500';
            }
        @endphp

        <div class="p-4 rounded-xl border-b-4 shadow-sm {{ $cardClass }}">
            <p class="text-[10px] text-gray-500 dark:text-gray-400 font-black uppercase tracking-tighter">
                {{ $isVoucher ? '🎟️ ' : '' }}{{ $traducaoMetodos[strtolower($metodo)] ?? ucfirst($metodo) }}
            </p>
            <p class="text-xl font-black text-gray-800 dark:text-white mt-1">
                R$ {{ number_format($valor, 2, ',', '.') }}
            </p>
        </div>
    @endif
@endforeach
            </div>

            {{-- 📄 TABELA DETALHADA --}}
            <div
                class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-xl overflow-hidden border border-gray-100 dark:border-gray-700">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase italic">
                                    Data/Pagto</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase italic">Unidade
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase italic">Cliente
                                    / Descrição</th>
                                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase italic">
                                    Tipo/Método</th>
                                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase italic">Valor
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($transacoes as $t)
                                @php
                                    $isVoucher = $t->payment_method === 'voucher';

                                    $tipoMapeado = [
                                        'signal' => ['label' => 'Sinal', 'class' => 'bg-blue-100 text-blue-700'],
                                        'payment' => [
                                            'label' => 'Pagamento',
                                            'class' => 'bg-emerald-100 text-emerald-700',
                                        ],
                                        'full_payment' => [
                                            'label' => 'Total',
                                            'class' => 'bg-emerald-100 text-emerald-700',
                                        ],
                                        'refund' => ['label' => 'Estorno', 'class' => 'bg-red-100 text-red-700'],
                                        'sangria' => [
                                            'label' => 'Sangria',
                                            'class' => 'bg-red-50 text-red-600 border border-red-100',
                                        ],
                                        'reforco' => [
                                            'label' => 'Reforço',
                                            'class' => 'bg-emerald-50 text-emerald-600 border border-emerald-100',
                                        ],
                                    ];

                                    $chaveTipo = strtolower($t->type);
                                    $exibicao = $tipoMapeado[$chaveTipo] ?? [
                                        'label' => strtoupper($t->type),
                                        'class' => 'bg-gray-100 text-gray-600',
                                    ];

                                    // Se for Voucher, forçamos uma cor neutra na badge de tipo
                                    if ($isVoucher) {
                                        $exibicao['class'] = 'bg-amber-100 text-amber-700 border border-amber-200';
                                    }

                                    // Garante cor vermelha para saídas (exceto estorno)
                                    if ($t->amount < 0 && $chaveTipo != 'refund') {
                                        $exibicao['class'] = 'bg-red-50 text-red-600 border border-red-100';
                                    }
                                @endphp

                                <tr
                                    class="text-sm hover:bg-gray-50 dark:hover:bg-gray-900/50 transition {{ $isVoucher ? 'opacity-80' : '' }}">
                                    {{-- Data --}}
                                    <td class="px-6 py-4 dark:text-gray-400 font-mono text-xs whitespace-nowrap italic">
                                        {{ $t->paid_at->format('d/m/Y H:i') }}
                                    </td>

                                    {{-- Unidade --}}
                                    <td class="px-6 py-4">
                                        <span
                                            class="px-2 py-1 rounded-md text-[10px] font-black uppercase bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-600">
                                            {{ $t->arena?->name ?? 'Geral' }}
                                        </span>
                                    </td>

                                    {{-- Cliente / Descrição --}}
                                    <td class="px-6 py-4">
                                        <div class="font-bold dark:text-gray-200">
                                            @if ($t->reserva)
                                                {{ $t->reserva->client_name }}
                                            @else
                                                <span class="text-indigo-600 dark:text-indigo-400">
                                                    {{ $t->description ?? ($t->amount < 0 ? 'Saída de Caixa' : 'Entrada Avulsa') }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-[10px] uppercase font-bold tracking-tight italic">
                                            @if ($t->reserva_id)
                                                <span class="text-gray-500">Reserva #{{ $t->reserva_id }}</span>
                                            @else
                                                <span class="text-amber-500">⚡ Movimentação Manual</span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Badge de Tipo e Método --}}
                                    <td class="px-6 py-4 text-center">
                                        <span
                                            class="px-2 py-0.5 rounded text-[10px] font-black uppercase {{ $exibicao['class'] }}">
                                            {{ $isVoucher ? 'CORTESIA' : $exibicao['label'] }}
                                        </span>

                                        <div
                                            class="text-[10px] mt-1 font-bold uppercase italic tracking-tighter {{ $isVoucher ? 'text-amber-600' : 'text-gray-400' }}">
                                            {!! $isVoucher ? '🎟️ ' : '' !!}
                                            {{ $traducaoMetodos[strtolower($t->payment_method)] ?? str_replace('_', ' ', $t->payment_method) }}
                                        </div>
                                    </td>

                                    {{-- Valor Dinâmico --}}
                                    <td class="px-6 py-4 text-right font-mono font-bold">
                                        @if ($isVoucher)
                                            <span class="text-gray-400 italic">
                                                R$ {{ number_format(abs($t->amount), 2, ',', '.') }}
                                            </span>
                                        @else
                                            <span class="{{ $t->amount < 0 ? 'text-red-500' : 'text-emerald-600' }}">
                                                {{ $t->amount < 0 ? '-' : '' }} R$
                                                {{ number_format(abs($t->amount), 2, ',', '.') }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5"
                                        class="px-6 py-12 text-center text-gray-500 dark:text-gray-400 italic text-sm">
                                        Nenhuma transação encontrada para os filtros aplicados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Paginação mantendo os filtros --}}
                @if ($transacoes->hasPages())
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                        {{ $transacoes->appends(request()->query())->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
