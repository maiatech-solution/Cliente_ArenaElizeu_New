<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                📋 Reservas Pendentes de Aprovação
            </h2>
            {{-- 🎯 FILTRO DE ARENA --}}
            <form action="{{ route('admin.reservas.pendentes') }}" method="GET" class="flex items-center space-x-2">
                <select name="arena_id" onchange="this.form.submit()"
                    class="rounded-md border-gray-300 dark:bg-gray-700 dark:text-white text-sm shadow-sm focus:border-indigo-500">
                    <option value="">Todas as Quadras</option>
                    @foreach (\App\Models\Arena::all() as $arena)
                        <option value="{{ $arena->id }}" {{ request('arena_id') == $arena->id ? 'selected' : '' }}>
                            {{ $arena->name }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>
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

                    @if (session('error'))
                        <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-md border border-red-400 font-bold">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="mb-6">
                        <a href="{{ route('admin.reservas.index') }}"
                            class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-800 uppercase tracking-widest hover:bg-gray-300 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Voltar ao Painel de Reservas
                        </a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Cliente</th>
                                    {{-- 🏟️ NOVA COLUNA ARENA --}}
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Quadra</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Data/Horário</th>
                                    <th
                                        class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Reputação</th>
                                    <th
                                        class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-1/4">
                                        Ações</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($reservas as $reserva)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">

                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium">{{ $reserva->client_name }}</div>
                                            <div class="text-xs text-gray-500">{{ $reserva->client_contact }}</div>
                                        </td>

                                        {{-- 🏟️ IDENTIFICAÇÃO DA ARENA --}}
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span
                                                class="px-2 py-1 text-xs font-bold rounded-md bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200 border border-indigo-200">
                                                {{ $reserva->arena->name ?? 'N/A' }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-bold">
                                            {{-- 1. Data da Reserva --}}
                                            {{ \Carbon\Carbon::parse($reserva->date)->format('d/m/Y') }}<br>

                                            {{-- 2. Horário --}}
                                            <span class="text-indigo-600 dark:text-indigo-400">
                                                {{ \Carbon\Carbon::parse($reserva->start_time)->format('H:i') }} -
                                                {{ \Carbon\Carbon::parse($reserva->end_time)->format('H:i') }}
                                            </span>

                                            {{-- 3. Status do Caixa (Alerta Crítico) --}}
                                            @if (\App\Http\Controllers\FinanceiroController::isCashClosed($reserva->date->toDateString()))
                                                <div
                                                    class="mt-1 flex items-center text-[10px] text-red-600 dark:text-red-400 font-extrabold uppercase tracking-wider">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                    </svg>
                                                    Caixa Fechado
                                                </div>
                                            @endif

                                            {{-- 4. Checagem de Conflitos (Sua lógica original) --}}
                                            @php
                                                $sameTimeReservasCount = \App\Models\Reserva::where(
                                                    'id',
                                                    '!=',
                                                    $reserva->id,
                                                )
                                                    ->where('arena_id', $reserva->arena_id)
                                                    ->where('date', $reserva->date)
                                                    ->where(function ($q) use ($reserva) {
                                                        $q->where('start_time', '<', $reserva->end_time)->where(
                                                            'end_time',
                                                            '>',
                                                            $reserva->start_time,
                                                        );
                                                    })
                                                    ->where('status', 'pending')
                                                    ->count();
                                            @endphp

                                            @if ($sameTimeReservasCount > 0)
                                                <div
                                                    class="mt-1 text-xs text-red-600 font-normal flex items-center italic">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path
                                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z">
                                                        </path>
                                                    </svg>
                                                    +{{ $sameTimeReservasCount }} interessado(s) nesta quadra
                                                </div>
                                            @endif
                                        </td>

                                        <td class="px-4 py-4 whitespace-nowrap text-center text-sm">
                                            {!! $reserva->user->status_tag ?? '<span class="text-gray-400">N/A</span>' !!}
                                        </td>

                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="flex flex-col space-y-2 items-center">
                                                @php
                                                    $cashIsClosed = \App\Http\Controllers\FinanceiroController::isCashClosed(
                                                        $reserva->date->toDateString(),
                                                    );
                                                @endphp

                                                @if ($cashIsClosed)
                                                    {{-- BOTÃO BLOQUEADO --}}
                                                    <button type="button" disabled
                                                        title="Caixa fechado para este dia. Reabra o caixa para confirmar."
                                                        class="w-full bg-gray-400 dark:bg-gray-600 text-white px-3 py-1.5 rounded-md text-xs font-bold shadow cursor-not-allowed flex items-center justify-center opacity-60">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1"
                                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                        </svg>
                                                        Bloqueado
                                                    </button>
                                                @else
                                                    {{-- BOTÃO ATIVO: Passando arena_id e start_time para o JS --}}
                                                    <button type="button"
                                                        onclick="openConfirmModal(
                    '{{ $reserva->id }}',
                    '{{ $reserva->client_name }}',
                    '{{ \Carbon\Carbon::parse($reserva->date)->format('d/m/Y') }} às {{ \Carbon\Carbon::parse($reserva->start_time)->format('H:i') }}',
                    '{{ $reserva->price }}',
                    '{{ $reserva->arena_id }}',
                    '{{ $reserva->start_time }}'
                )"
                                                        class="w-full bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-md text-xs font-bold transition flex items-center justify-center shadow">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1"
                                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        Confirmar
                                                    </button>
                                                @endif

                                                {{-- Botão Rejeitar --}}
                                                <form action="{{ route('admin.reservas.rejeitar', $reserva->id) }}"
                                                    method="POST" class="w-full">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="rejection_reason"
                                                        value="Rejeitada pela administração - Horário indisponível ou selecionado outro cliente.">
                                                    <button type="submit"
                                                        onclick="return confirm('Rejeitar esta solicitação?')"
                                                        class="w-full bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-md text-xs font-bold transition shadow">
                                                        Rejeitar
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5"
                                            class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                            <div class="flex flex-col items-center italic">
                                                <svg class="w-12 h-12 mb-2 text-gray-300" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="1.5"
                                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                                    </path>
                                                </svg>
                                                Não há reservas pendentes para os filtros selecionados.
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $reservas->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('admin.reservas.confirmation_modal')
</x-app-layout>
