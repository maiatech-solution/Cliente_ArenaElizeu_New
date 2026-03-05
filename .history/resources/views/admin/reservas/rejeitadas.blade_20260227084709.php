<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            🚫 Histórico de Rejeições e Cancelamentos
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">

                    @if (session('success'))
                    <div class="mb-4 p-4 bg-green-100 text-green-700 rounded-md border border-green-400 font-bold">
                        {{ session('success') }}
                    </div>
                    @endif

                    <div class="mb-8 flex flex-col space-y-4 bg-gray-50 dark:bg-gray-700/30 p-5 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
                        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                            <div class="flex flex-col space-y-4 w-full">
                                {{-- Botão Voltar --}}
                                <a href="{{ route('admin.reservas.index') }}" class="inline-flex items-center w-fit px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700 shadow-sm transition">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                    </svg>
                                    Painel de Reservas
                                </a>

                                {{-- Formulário de Filtros --}}
                                <form method="GET" action="{{ route('admin.reservas.rejeitadas') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 w-full">
                                    <div>
                                        <label for="search" class="block text-[10px] font-bold text-gray-500 uppercase mb-1 ml-1 tracking-widest">Buscar Cliente:</label>
                                        <input type="text" name="search" id="search" value="{{ request('search') }}"
                                            placeholder="Nome ou contato..."
                                            class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-800 rounded-lg shadow-sm focus:ring-red-500 focus:border-red-500 transition">
                                    </div>

                                    <div>
                                        <label for="arena_id" class="block text-[10px] font-bold text-gray-500 uppercase mb-1 ml-1 tracking-widest">Filtrar Quadra:</label>
                                        <select name="arena_id" id="arena_id" class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-800 rounded-lg shadow-sm focus:ring-red-500">
                                            <option value="">Todas as Quadras</option>
                                            @foreach($arenas as $arena)
                                            <option value="{{ $arena->id }}" {{ request('arena_id') == $arena->id ? 'selected' : '' }}>{{ $arena->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label for="status_filter" class="block text-[10px] font-bold text-gray-500 uppercase mb-1 ml-1 tracking-widest">Ver apenas:</label>
                                        <select name="status_filter" id="status_filter" onchange="this.form.submit()" class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-800 rounded-lg shadow-sm focus:ring-red-500 font-bold text-red-600">
                                            <option value="rejected" {{ request('status_filter', 'rejected') == 'rejected' ? 'selected' : '' }}>🚫 Rejeitadas (Gestor)</option>
                                            <option value="cancelled" {{ request('status_filter') == 'cancelled' ? 'selected' : '' }}>⚠️ Canceladas (Cliente)</option>
                                            <option value="all" {{ request('status_filter') == 'all' ? 'selected' : '' }}>📂 Ver Todas</option>
                                        </select>
                                    </div>

                                    <div class="flex space-x-2 items-end">
                                        <button type="submit" class="bg-gray-800 dark:bg-gray-600 hover:bg-gray-900 text-white px-4 py-2 rounded-lg shadow-md transition flex-1 flex justify-center items-center font-bold text-xs uppercase">
                                            Filtrar
                                        </button>
                                        @if(request()->anyFilled(['search', 'arena_id', 'status_filter']))
                                        <a href="{{ route('admin.reservas.rejeitadas') }}" class="bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 p-2 rounded-lg hover:bg-gray-300 transition flex justify-center items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </a>
                                        @endif
                                    </div>
                                </form>
                            </div>

                            <div class="text-right border-t md:border-t-0 md:border-l border-gray-200 dark:border-gray-600 pt-4 md:pt-0 md:pl-6 flex-shrink-0">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-1">Resultados</span>
                                <span class="text-3xl font-black text-red-600 leading-none">{{ $reservas->total() }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cliente</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quadra</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Data / Horário</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider w-1/4">Motivo</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Responsável</th>
                                    {{-- 🎯 NOVA COLUNA --}}
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($reservas as $reserva)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition duration-150">
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $reserva->client_name }}</div>
                                        <div class="text-[11px] text-gray-500">{{ $reserva->client_contact }}</div>
                                    </td>

                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-[10px] font-black uppercase rounded bg-indigo-100 text-indigo-700 border border-indigo-200">
                                            {{ $reserva->arena->name ?? 'N/A' }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ \Carbon\Carbon::parse($reserva->date)->format('d/m/Y') }}</div>
                                        <div class="text-xs text-indigo-600 dark:text-indigo-400 font-bold">{{ $reserva->start_time }} - {{ $reserva->end_time }}</div>
                                    </td>

                                    <td class="px-4 py-4 whitespace-nowrap text-center">
                                        @if($reserva->status === 'rejected')
                                        <span class="px-2 py-0.5 rounded text-[9px] font-black bg-red-100 text-red-700 border border-red-200 uppercase">Rejeitada</span>
                                        @else
                                        <span class="px-2 py-0.5 rounded text-[9px] font-black bg-orange-100 text-orange-700 border border-orange-200 uppercase">Cancelada</span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-4 text-sm">
                                        <div class="text-gray-600 dark:text-gray-400 italic text-[12px] leading-tight line-clamp-2">
                                            "{{ $reserva->cancellation_reason ?? 'Sem justificativa' }}"
                                        </div>
                                        <div class="text-[9px] text-gray-400 mt-1 uppercase font-bold tracking-tighter">
                                            Processado em: {{ $reserva->updated_at->format('d/m/Y H:i') }}
                                        </div>
                                    </td>

                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ $reserva->manager->name ?? 'Sistema' }}
                                    </td>

                                    {{-- 🎯 BOTÃO PARA A VIEW DE DETALHES --}}
                                    <td class="px-4 py-4 whitespace-nowrap text-center">
                                        <a href="{{ route('admin.reservas.show', $reserva->id) }}"
                                            class="inline-flex items-center px-3 py-1.5 bg-gray-900 dark:bg-gray-700 text-white dark:text-gray-200 rounded-lg font-bold text-[10px] uppercase hover:bg-indigo-600 transition-all shadow-sm">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            Detalhes
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-16 text-center text-gray-500 dark:text-gray-400 italic">
                                        Nenhum registro encontrado para este filtro.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-8">
                        {{ $reservas->links() }}
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>