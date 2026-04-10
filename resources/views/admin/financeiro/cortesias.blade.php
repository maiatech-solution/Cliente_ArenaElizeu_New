<x-app-layout>
    <div class="max-w-[1400px] mx-auto px-6 py-8 italic">

        {{-- 🛡️ HEADER & FILTROS PADRONIZADOS --}}
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end gap-6 mb-10">
            <div class="flex items-center gap-5">
                <a href="{{ route('admin.financeiro.dashboard') }}"
                    class="p-3 bg-white dark:bg-gray-800 text-amber-500 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-lg hover:scale-110 transition-all group">
                    <span class="group-hover:-translate-x-1 transition-transform inline-block">◀</span>
                </a>
                <div>
                    <h1
                        class="text-4xl font-black text-gray-800 dark:text-white uppercase tracking-tighter italic leading-none">
                        Gestão de <span class="text-amber-500">Cortesias</span>
                    </h1>
                    <p class="text-gray-500 font-bold text-[10px] uppercase tracking-[0.2em] mt-1 italic">
                        Rastreamento de vouchers e impacto financeiro
                    </p>
                </div>
            </div>

            {{-- 🔍 FILTROS --}}
            <form action="{{ route('admin.financeiro.relatorio_cortesias') }}" method="GET"
                class="flex flex-wrap items-center gap-4 bg-white dark:bg-gray-800 p-4 rounded-[2rem] border border-gray-100 dark:border-gray-700 shadow-xl print:hidden">

                {{-- Pesquisa por Nome --}}
                <div class="flex flex-col gap-1">
                    <label class="text-[9px] font-black text-gray-400 uppercase ml-2 italic">Pesquisar Cliente</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Nome do cliente..."
                        class="bg-gray-50 dark:bg-gray-900 border-none rounded-xl px-4 py-2 text-gray-700 dark:text-white text-xs font-bold focus:ring-2 focus:ring-amber-500 w-48">
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[9px] font-black text-gray-400 uppercase ml-2 italic">Unidade</label>
                    <select name="arena_id" onchange="this.form.submit()"
                        class="bg-gray-50 dark:bg-gray-900 border-none rounded-xl px-4 py-2 text-gray-700 dark:text-white text-xs font-bold focus:ring-2 focus:ring-amber-500">
                        <option value="">Todas as Unidades</option>
                        @foreach (\App\Models\Arena::all() as $arena)
                            <option value="{{ $arena->id }}"
                                {{ request('arena_id') == $arena->id ? 'selected' : '' }}>
                                {{ $arena->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[9px] font-black text-gray-400 uppercase ml-2 italic">Mês</label>
                    <select name="mes" onchange="this.form.submit()"
                        class="bg-gray-50 dark:bg-gray-900 border-none rounded-xl px-4 py-2 text-gray-700 dark:text-white text-xs font-bold focus:ring-2 focus:ring-amber-500">
                        @foreach (range(1, 12) as $m)
                            <option value="{{ $m }}" {{ $mes == $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create()->month($m)->locale('pt_BR')->translatedFormat('F') }}
                            </option>
                        @endforeach
                        <option value="all" {{ $mes == 'all' ? 'selected' : '' }}>Ano Inteiro</option>
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[9px] font-black text-gray-400 uppercase ml-2 italic">Ano</label>
                    <select name="ano" onchange="this.form.submit()"
                        class="bg-gray-50 dark:bg-gray-900 border-none rounded-xl px-4 py-2 text-gray-700 dark:text-white text-xs font-bold focus:ring-2 focus:ring-amber-500">
                        @for ($y = now()->year; $y >= 2024; $y--)
                            <option value="{{ $y }}" {{ $ano == $y ? 'selected' : '' }}>
                                {{ $y }}</option>
                        @endfor
                    </select>
                </div>

                <button type="submit"
                    class="mt-5 bg-amber-500 hover:bg-amber-600 text-white p-2.5 rounded-xl transition-all shadow-lg shadow-amber-900/20">
                    🔍
                </button>

                <a href="{{ route('admin.financeiro.relatorio_cortesias') }}"
                    class="mt-5 bg-gray-100 dark:bg-gray-700 text-gray-500 p-2.5 rounded-xl transition-all">
                    🔄
                </a>
            </form>
        </div>

        {{-- 📊 CARDS DE RESUMO RÁPIDO --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div
                class="bg-white dark:bg-gray-800 p-6 rounded-[2.5rem] border border-gray-100 dark:border-gray-700 shadow-xl flex justify-between items-center">
                <div>
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest italic">Total em Cortesias
                    </p>
                    <h3 class="text-3xl font-black text-amber-600 font-mono italic">R$
                        {{ number_format($totalValorCortesias, 2, ',', '.') }}</h3>
                </div>
                <div class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-2xl text-amber-600">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                    </svg>
                </div>
            </div>
            <div
                class="bg-white dark:bg-gray-800 p-6 rounded-[2.5rem] border border-gray-100 dark:border-gray-700 shadow-xl flex justify-between items-center">
                <div>
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest italic">Qtd. de Vouchers
                    </p>
                    <h3 class="text-3xl font-black text-gray-700 dark:text-white font-mono italic">
                        {{ $cortesias->count() }} <span class="text-sm">Unid.</span></h3>
                </div>
                <div class="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-2xl text-gray-400">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 7h.01M7 11h.01M7 15h.01M11 7h.01M11 11h.01M11 15h.01M15 7h.01M15 11h.01M15 15h.01" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- 🏆 TABELA DE AUDITORIA --}}
        <div
            class="bg-white dark:bg-gray-800 rounded-[3rem] border border-gray-100 dark:border-gray-700 overflow-hidden shadow-2xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr
                            class="text-gray-400 text-[10px] font-black uppercase tracking-widest border-b dark:border-gray-700 bg-gray-50/50 dark:bg-black/20 italic">
                            <th class="p-6">Data</th>
                            <th class="p-6">Cliente / Reserva</th>
                            <th class="p-6">Unidade</th>
                            <th class="p-6">Operador</th>
                            <th class="p-6 text-right">Valor Abatido</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                        @forelse($cortesias as $item)
                            <tr class="hover:bg-amber-50/30 dark:hover:bg-amber-900/10 transition-colors group">
                                <td class="p-6 text-xs font-bold text-gray-500 italic">
                                    {{ \Carbon\Carbon::parse($item->paid_at)->format('d/m/Y H:i') }}
                                </td>
                                <td class="p-6">
                                    <span
                                        class="text-gray-900 dark:text-white font-black uppercase italic text-sm block leading-none">
                                        {{ $item->reserva->client_name ?? 'N/A' }}
                                    </span>
                                    <span
                                        class="text-[9px] text-amber-600 font-bold uppercase tracking-tighter italic">Reserva
                                        #{{ $item->reserva_id }}</span>
                                </td>
                                <td class="p-6 text-[10px] font-black uppercase text-gray-400 italic">
                                    {{ $item->arena->name ?? 'Global' }}
                                </td>
                                <td class="p-6">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="w-6 h-6 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center text-[10px] font-black text-gray-500">
                                            {{ substr($item->manager->name ?? 'S', 0, 1) }}
                                        </div>
                                        <span
                                            class="text-xs font-bold text-gray-600 dark:text-gray-400 italic">{{ $item->manager->name ?? 'Sistema' }}</span>
                                    </div>
                                </td>
                                <td class="p-6 text-right">
                                    <span
                                        class="text-amber-600 dark:text-amber-400 font-black text-xl italic font-mono tracking-tighter">
                                        R$ {{ number_format($item->amount, 2, ',', '.') }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-24 text-center">
                                    <p
                                        class="text-gray-300 dark:text-gray-700 font-black uppercase tracking-widest italic text-3xl">
                                        Nenhuma cortesia no período</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
