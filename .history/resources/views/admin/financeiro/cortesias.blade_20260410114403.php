<x-app-layout>
    <div class="max-w-6xl mx-auto px-4 py-8 italic">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-black text-gray-800 dark:text-white uppercase tracking-tighter">
                    🎁 Relatório de <span class="text-amber-500">Cortesias</span>
                </h1>
                <p class="text-xs text-gray-400 font-bold uppercase italic">Rastreamento de Vouchers e Isenções</p>
            </div>
            <div class="flex gap-4">
                <div class="bg-amber-50 dark:bg-amber-900/20 p-4 rounded-2xl border border-amber-100 dark:border-amber-800">
                    <span class="block text-[10px] font-black text-amber-600 uppercase">Total Concedido</span>
                    <span class="text-2xl font-black text-amber-700 dark:text-amber-400 font-mono">
                        R$ {{ number_format($totalValorCortesias, 2, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Tabela de Auditoria --}}
        <div class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b dark:border-gray-700 bg-gray-50/50 dark:bg-black/20">
                        <th class="p-6">Data</th>
                        <th class="p-6">Cliente</th>
                        <th class="p-6">Unidade</th>
                        <th class="p-6">Autorizado por</th>
                        <th class="p-6 text-right">Valor Abatido</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    @forelse($cortesias as $item)
                        <tr class="hover:bg-amber-50/30 dark:hover:bg-amber-900/10 transition-colors group">
                            <td class="p-6 text-sm font-bold text-gray-600 dark:text-gray-400">
                                {{ \Carbon\Carbon::parse($item->paid_at)->format('d/m/Y H:i') }}
                            </td>
                            <td class="p-6">
                                <span class="text-gray-800 dark:text-white font-black uppercase text-sm block">
                                    {{ $item->reserva->client_name ?? 'N/A' }}
                                </span>
                                <span class="text-[10px] text-gray-400 font-bold uppercase italic">Reserva #{{ $item->reserva_id }}</span>
                            </td>
                            <td class="p-6">
                                <span class="text-[10px] font-black px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-500 uppercase">
                                    {{ $item->arena->name ?? 'Global' }}
                                </span>
                            </td>
                            <td class="p-6 text-sm font-bold text-gray-500">
                                {{ $item->manager->name ?? 'Sistema' }}
                            </td>
                            <td class="p-6 text-right font-mono font-black text-amber-600 dark:text-amber-400 text-lg">
                                R$ {{ number_format($item->amount, 2, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-20 text-center text-gray-400 font-black uppercase italic">Nenhuma cortesia identificada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
