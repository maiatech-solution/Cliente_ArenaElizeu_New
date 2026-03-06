<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-4">
                {{-- 🧠 UX: Botão Voltar Inteligente --}}
                {{-- Garante o retorno para o dia e arena específicos no calendário --}}
                <a href="{{ route('admin.reservas.index', ['arena_id' => $reserva->arena_id, 'date' => \Carbon\Carbon::parse($reserva->date)->format('Y-m-d')]) }}"
                    class="bg-white dark:bg-gray-800 p-2 rounded-full shadow-sm border border-gray-200 dark:border-gray-700 hover:bg-gray-50 transition-all flex items-center justify-center">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>

                <h2 class="font-black text-xl text-gray-800 dark:text-gray-200 uppercase tracking-tighter">
                    Detalhes da Reserva #{{ $reserva->id }}
                </h2>
            </div>

            @php
            $statusClass =
            [
            'pending' => 'bg-orange-100 text-orange-700 border-orange-200',
            'confirmed' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
            'cancelled' => 'bg-red-100 text-red-700 border-red-200',
            'rejected' => 'bg-gray-100 text-gray-700 border-gray-200',
            'expired' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
            'no_show' => 'bg-black text-white border-gray-900',
            'maintenance' => 'bg-pink-100 text-pink-700 border-pink-200',
            ][$reserva->status] ?? 'bg-gray-100 text-gray-700';

            $statusLabels = [
            'pending' => 'Pendente',
            'confirmed' => 'Confirmado',
            'cancelled' => 'Cancelado',
            'rejected' => 'Rejeitado',
            'expired' => 'Expirado',
            'no_show' => 'Falta',
            'maintenance' => 'Manutenção',
            ];
            @endphp

            <span class="px-4 py-1 rounded-full text-xs font-black uppercase border {{ $statusClass }}">
                {{ $statusLabels[$reserva->status] ?? $reserva->status }}
            </span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- 🟦 COLUNA DA ESQUERDA E DIREITA --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- INFORMAÇÕES E EXTRATO --}}
                <div class="lg:col-span-2 space-y-6">
                    <div
                        class="bg-white dark:bg-gray-800 p-8 rounded-[2.5rem] shadow-sm border border-gray-100 dark:border-gray-700">
                        <div class="flex justify-between items-start mb-8 border-b dark:border-gray-700 pb-6">
                            <div class="space-y-1">
                                <label
                                    class="text-[10px] font-black text-indigo-500 uppercase tracking-widest block">Responsável</label>

                                <div class="flex items-center gap-4">
                                    <h3
                                        class="text-3xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">
                                        {{ $reserva->client_name }}
                                    </h3>

                                    {{-- Botão de Sincronizar --}}
                                    @if ($reserva->user_id)
                                    <form action="{{ route('admin.reservas.sincronizar', $reserva->id) }}"
                                        method="POST" class="inline">
                                        @csrf
                                        <button type="submit"
                                            class="inline-flex items-center gap-1 text-[10px] font-black uppercase bg-blue-50 text-blue-600 px-3 py-1 rounded-full hover:bg-blue-600 hover:text-white transition-all shadow-sm border border-blue-100"
                                            title="Atualizar dados com base no cadastro do cliente">
                                            🔄 Sincronizar Dados
                                        </button>
                                    </form>
                                    @endif
                                </div>

                                <div class="flex items-center gap-2 mt-2">
                                    <p class="text-sm text-gray-500 font-mono">
                                        📞 {{ $reserva->client_contact ?? 'Não informado' }}
                                    </p>

                                    {{-- Botão de WhatsApp --}}
                                    @if ($reserva->client_contact && in_array($reserva->status, ['confirmed', 'pending', 'maintenance', 'completed']))
                                    <a href="https://wa.me/55{{ preg_replace('/\D/', '', $reserva->client_contact) }}"
                                        target="_blank" title="Conversar com cliente"
                                        class="inline-flex items-center justify-center w-6 h-6 bg-emerald-100 text-emerald-600 rounded-full hover:bg-emerald-500 hover:text-white transition-all shadow-sm">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                            <path
                                                d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.414 0 .018 5.393 0 12.029c0 2.119.554 4.188 1.605 6.03l-1.706 6.23 6.376-1.674c1.776.968 3.774 1.478 5.811 1.48h.005c6.632 0 12.029-5.396 12.032-12.033.002-3.216-1.25-6.237-3.535-8.524">
                                            </path>
                                        </svg>
                                    </a>
                                    @endif
                                </div>
                            </div>

                            <div class="text-right">
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Data e
                                    Horário</label>
                                <p class="text-xl font-bold dark:text-gray-200">
                                    {{ \Carbon\Carbon::parse($reserva->date)->format('d/m/Y') }}
                                </p>
                                <p class="text-indigo-600 font-black">
                                    {{ \Carbon\Carbon::parse($reserva->start_time)->format('H:i') }}h -
                                    {{ \Carbon\Carbon::parse($reserva->end_time)->format('H:i') }}h
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div
                                class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-2xl text-center border border-gray-100 dark:border-gray-800">
                                <span class="text-[9px] font-black text-gray-400 uppercase block">Arena/Quadra</span>
                                <span
                                    class="font-bold text-indigo-600 dark:text-indigo-400 text-sm uppercase">{{ $reserva->arena->name ?? 'Quadra Padrão' }}</span>
                            </div>
                            <div
                                class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-2xl text-center border border-gray-100 dark:border-gray-800">
                                <span class="text-[9px] font-black text-gray-400 uppercase block">Atendido por</span>
                                <span
                                    class="font-bold dark:text-white text-sm uppercase">{{ $reserva->manager->name ?? 'Sistema' }}</span>
                            </div>
                            <div
                                class="bg-indigo-50 dark:bg-indigo-900/20 p-4 rounded-2xl border border-indigo-100 dark:border-indigo-800 text-center">
                                <span class="text-[9px] font-black text-indigo-500 uppercase block">Tipo</span>
                                <span
                                    class="font-black text-indigo-700 dark:text-indigo-400 text-sm uppercase">{{ $reserva->is_recurrent ? '📅 Mensalista' : '⚡ Pontual' }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- HISTÓRICO FINANCEIRO --}}
                    <div
                        class="bg-white dark:bg-gray-800 rounded-[2.5rem] shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="px-8 py-5 bg-gray-50 dark:bg-gray-700/50 border-b dark:border-gray-700">
                            <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest">Extrato Financeiro
                            </h4>
                        </div>
                        <table class="w-full text-left">
                            <tbody class="divide-y dark:divide-gray-700">
                                @forelse($reserva->transactions as $transacao)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                                    <td class="px-8 py-4 font-mono text-[11px] dark:text-gray-400">
                                        {{ $transacao->paid_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-8 py-4 uppercase text-[10px] font-bold dark:text-gray-300">
                                        {{ $transacao->payment_method }}
                                    </td>
                                    <td class="px-8 py-4 text-right font-black text-emerald-600">R$
                                        {{ number_format($transacao->amount, 2, ',', '.') }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3"
                                        class="px-8 py-10 text-center text-gray-400 uppercase text-[10px] font-black italic tracking-widest">
                                        Sem movimentações</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- COLUNA DA DIREITA --}}
                <div class="space-y-6">
                    {{-- CARD DE BALANÇO FINANCEIRO --}}
                    <div class="bg-gray-900 text-white p-8 rounded-[2.5rem] shadow-xl relative overflow-hidden">
                        <p class="text-[10px] font-black opacity-50 uppercase tracking-[0.2em] mb-6">Balanço Final</p>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center opacity-70">
                                <span class="text-xs uppercase font-bold tracking-tighter">Preço do Jogo</span>
                                <span class="font-mono font-bold">R$
                                    {{ number_format($reserva->price, 2, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between items-center text-emerald-400">
                                <span class="text-xs uppercase font-bold tracking-tighter">Já Recebido</span>
                                <span class="font-mono font-black">R$
                                    {{ number_format($reserva->total_paid ?? 0, 2, ',', '.') }}</span>
                            </div>
                            <hr class="border-white/10 my-4">
                            <div class="flex flex-col">
                                <span class="text-[10px] font-black uppercase tracking-widest opacity-50">Saldo a
                                    Pagar</span>
                                @php $saldoFinalCalculado = $reserva->price - ($reserva->total_paid ?? 0); @endphp
                                <span
                                    class="text-4xl font-black font-mono mt-1 {{ $saldoFinalCalculado > 0 ? 'text-orange-400' : 'text-emerald-400' }}">
                                    R$ {{ number_format(max(0, $saldoFinalCalculado), 2, ',', '.') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- CARD DE AÇÕES DA RESERVA --}}
                    <div
                        class="bg-white dark:bg-gray-800 p-6 rounded-[2.5rem] shadow-sm border border-gray-100 dark:border-gray-700 space-y-3">
                        @php
                        $isColab = auth()->user()->role === 'colaborador';
                        $totalPaidVal = $reserva->total_paid ?? 0;
                        $isClosedCashier = \App\Models\Cashier::where('date', $reserva->date)
                        ->where('status', 'closed')
                        ->exists();
                        @endphp

                        {{-- 📱 NOTIFICAÇÕES WHATSAPP --}}
                        @if (session('whatsapp_link'))
                        <div id="waNotificationCard"
                            class="p-4 bg-emerald-50 dark:bg-emerald-900/20 border-2 border-emerald-100 dark:border-emerald-800 rounded-2xl mb-2 flex items-center justify-between group">
                            <div class="flex items-center gap-3">
                                <span class="text-xl">📱</span>
                                <div>
                                    <p class="text-[9px] font-black text-emerald-600 uppercase tracking-widest">
                                        Notificação Pronta</p>
                                    <p class="text-[11px] text-emerald-800 dark:text-emerald-200 font-bold">Avisar
                                        cliente?</p>
                                </div>
                            </div>
                            <a id="waNotificationBtn" href="{{ session('whatsapp_link') }}" target="_blank"
                                class="bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase transition-all shadow-md">Enviar</a>
                        </div>
                        @endif

                        <div id="waNotificationCardManual"
                            class="hidden p-4 bg-emerald-50 dark:bg-emerald-900/20 border-2 border-emerald-100 dark:border-emerald-800 rounded-2xl mb-2 flex items-center justify-between group">
                            <div class="flex items-center gap-3">
                                <span class="text-xl">📱</span>
                                <div>
                                    <p class="text-[9px] font-black text-emerald-600 uppercase tracking-widest">
                                        Notificação Pronta</p>
                                    <p class="text-[11px] text-emerald-800 dark:text-emerald-200 font-bold">Avisar
                                        cliente?</p>
                                </div>
                            </div>
                            <a id="waNotificationBtnManual" href="#" target="_blank"
                                class="bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase transition-all shadow-md">Enviar</a>
                        </div>

                        {{-- 🛠️ AÇÃO SE FOR MANUTENÇÃO --}}
                        @if ($reserva->status === 'maintenance')
                        <div class="space-y-3">
                            <div
                                class="p-4 bg-pink-50 dark:bg-pink-900/20 border border-pink-100 dark:border-pink-800 rounded-2xl text-center">
                                <p class="text-xs font-bold text-pink-700 dark:text-pink-300 uppercase">Horário em
                                    Manutenção</p>
                            </div>
                            <button type="button"
                                onclick="{{ $isColab ? 'window.requisitarAutorizacao(token => { if(token) openReactivateModal() })' : 'openReactivateModal()' }}"
                                class="w-full bg-indigo-600 text-white py-4 rounded-2xl font-black text-xs uppercase hover:bg-indigo-700 transition shadow-lg">
                                🔄 Finalizar Manutenção / Liberar
                            </button>
                        </div>
                        @endif

                        {{-- 1️⃣ STATUS PENDENTE --}}
                        @if ($reserva->status === 'pending')
                        <form method="POST" action="{{ route('admin.reservas.confirmar', $reserva) }}">
                            @csrf @method('PATCH')
                            <button type="submit"
                                class="w-full bg-emerald-600 text-white py-4 rounded-2xl font-black text-xs uppercase hover:bg-emerald-700 transition shadow-lg">
                                Aprovar Reserva
                            </button>
                        </form>
                        @endif

                        {{-- 2️⃣ STATUS CONFIRMADO OU CONCLUÍDO --}}
                        @if (in_array($reserva->status, ['confirmed', 'completed']))
                        <div class="space-y-3">
                            @php $saldoRestanteAcoes = $reserva->price - $totalPaidVal; @endphp

                            @if ($saldoRestanteAcoes > 0)
                            <a href="{{ route('admin.payment.index', ['reserva_id' => $reserva->id, 'date' => \Carbon\Carbon::parse($reserva->date)->format('Y-m-d'), 'arena_id' => $reserva->arena_id]) }}"
                                class="w-full bg-indigo-600 text-white py-4 rounded-2xl font-black text-xs uppercase hover:bg-indigo-700 transition shadow-lg flex items-center justify-center gap-2 block text-center">
                                <span>💶 Receber Saldo (R$
                                    {{ number_format($saldoRestanteAcoes, 2, ',', '.') }})</span>
                            </a>
                            @else
                            <div
                                class="w-full bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 py-3 rounded-2xl font-black text-[10px] uppercase text-center border border-emerald-100 dark:border-emerald-800">
                                ✅ Jogo Totalmente Pago
                            </div>
                            @endif

                            @if (!$isClosedCashier)
                            <button type="button"
                                onclick="{{ $isColab ? "window.requisitarAutorizacao(token => { if(token) openMaintenanceModal('{$reserva->id}', '{$totalPaidVal}') })" : "openMaintenanceModal('{$reserva->id}', '{$totalPaidVal}')" }}"
                                class="w-full bg-pink-50 text-pink-600 py-3 rounded-2xl font-black text-[10px] uppercase hover:bg-pink-600 hover:text-white transition border border-pink-100 mt-2">
                                🛠️ Bloquear p/ Manutenção
                            </button>
                            @endif
                        </div>

                        @endif

                        {{-- 3️⃣ MOTIVO SE CANCELADO/REJEITADO --}}
                        @if ($reserva->status === 'cancelled' || $reserva->status === 'rejected')
                        <div
                            class="p-4 bg-red-50 dark:bg-red-900/20 rounded-2xl border border-red-100 dark:border-red-800">
                            <label class="text-[9px] font-black text-red-500 uppercase block mb-1">Motivo da
                                Inatividade</label>
                            <p class="text-xs text-red-700 dark:text-red-300 italic">
                                "{{ $reserva->cancellation_reason ?? 'Não informado' }}"
                            </p>
                        </div>
                        @endif
                    </div>

                </div> {{-- FIM DA COLUNA DA DIREITA --}}
            </div>
        </div>
    </div>

    {{-- 🛠️ MODAIS --}}

    {{-- 1. MODAL MANUTENÇÃO --}}
    <div id="maintenanceModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="closeMaintenanceModal()"></div>
            <div
                class="relative bg-white dark:bg-gray-800 rounded-[2.5rem] max-w-md w-full p-8 shadow-2xl border border-pink-100 transform transition-all">

                <div class="mb-6">
                    <div class="w-12 h-12 bg-pink-100 rounded-full flex items-center justify-center mb-4">
                        <span class="text-2xl">🛠️</span>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">
                        Manutenção
                    </h3>
                    <p class="text-sm text-gray-500 mt-2">O cliente será removido para bloqueio da quadra.</p>
                </div>

                <form id="maintenanceForm">
                    {{-- Campo para armazenar o token de autorização do gerente --}}
                    <input type="hidden" id="maintenance_auth_token" name="auth_token">

                    <div id="financeActionSection" class="mb-6 space-y-4">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block px-1">
                            Ação Financeira
                        </label>
                        <div class="grid grid-cols-1 gap-2">

                            {{-- OPÇÃO 1: ESTORNO (Padrão para Pontuais) --}}
                            <label
                                class="relative flex flex-col p-4 border-2 border-gray-100 rounded-2xl cursor-pointer hover:bg-gray-50 transition has-[:checked]:border-pink-500 has-[:checked]:bg-pink-50/30">
                                <div class="flex items-center gap-3">
                                    <input type="radio" name="finance_action" value="refund" checked
                                        class="text-pink-600 focus:ring-pink-500">
                                    <span class="font-black text-xs uppercase text-gray-700 dark:text-gray-300">
                                        Devolver Valor (Estorno)
                                    </span>
                                </div>
                                <p class="text-[10px] text-pink-600 mt-2 font-bold uppercase italic">
                                    ⚠️ R$ <span class="paid-amount-label">0,00</span> sairá do caixa.
                                </p>
                            </label>

                            {{-- OPÇÃO 2: CRÉDITO (Apenas Mensalistas) --}}
                            @if ($reserva->is_recurrent)
                            <label
                                class="relative flex flex-col p-4 border-2 border-gray-100 rounded-2xl cursor-pointer hover:bg-gray-50 transition has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50/30">
                                <div class="flex items-center gap-3">
                                    <input type="radio" name="finance_action" value="credit"
                                        class="text-indigo-600 focus:ring-indigo-500">
                                    <span class="font-black text-xs uppercase text-gray-700 dark:text-gray-300">
                                        Transferir p/ Próximo Jogo
                                    </span>
                                </div>
                                <p class="text-[10px] text-indigo-600 mt-2 font-bold uppercase italic">
                                    ✅ O valor será movido para a reserva da semana seguinte.
                                </p>
                            </label>
                            @endif
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-2 px-1">
                            Motivo do Bloqueio
                        </label>
                        <textarea id="maintenance_reason" required rows="2"
                            class="w-full rounded-2xl border-gray-200 dark:bg-gray-900 dark:border-gray-700 text-sm dark:text-white focus:ring-pink-500 focus:border-pink-500"
                            placeholder="Ex: Reparo na iluminação, troca de rede..."></textarea>
                    </div>

                    <div class="flex gap-3">
                        <button type="button" onclick="closeMaintenanceModal()"
                            class="flex-1 py-4 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-300 rounded-2xl font-black uppercase text-[10px] hover:bg-gray-200 transition">
                            Cancelar
                        </button>
                        <button type="submit" id="btnConfirmMaintenance"
                            class="flex-[2] py-4 bg-pink-600 text-white rounded-2xl font-black uppercase text-[10px] shadow-lg hover:bg-pink-700 transition active:scale-95">
                            Confirmar Bloqueio
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- 2. MODAL REATIVAÇÃO (FINALIZAR MANUTENÇÃO) --}}
    <div id="reactivateDecisionModal" class="fixed inset-0 z-[60] hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="closeReactivateModal()"></div>

            <div
                class="relative bg-white dark:bg-gray-800 rounded-[2.5rem] max-w-md w-full p-8 shadow-2xl border border-indigo-100 transform transition-all">
                <div class="mb-6 text-center">
                    <div
                        class="w-16 h-16 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <span class="text-3xl">🔄</span>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">
                        Concluir Manutenção
                    </h3>
                    <p class="text-sm text-gray-500 mt-2">Como deseja liberar este horário?</p>
                </div>

                <form id="reactivateForm" method="POST"
                    action="{{ route('admin.reservas.reativar_manutencao', $reserva->id) }}" class="space-y-3">
                    @csrf
                    @method('PATCH')

                    {{-- Campo para armazenar o token de autorização do gerente --}}
                    <input type="hidden" id="reactivate_auth_token" name="auth_token">
                    <input type="hidden" name="status" value="maintenance">

                    {{-- Opção 1: Restaurar o agendamento anterior --}}
                    <button type="submit" name="action" value="restore_client" id="btnRestoreClientAction"
                        class="w-full p-4 border-2 border-indigo-50 dark:border-gray-700 rounded-2xl hover:bg-indigo-50 dark:hover:bg-gray-700 transition text-left flex items-start gap-4 group">
                        <div class="bg-indigo-100 dark:bg-indigo-900 p-2 rounded-lg text-xl">👤</div>
                        <div>
                            <span class="block font-black text-xs uppercase text-indigo-700 dark:text-indigo-400">
                                Restaurar Cliente Original
                            </span>
                            <span class="block text-[10px] text-gray-500">
                                O horário volta para o cliente anterior como 'Confirmado'.
                            </span>
                        </div>
                    </button>

                    {{-- Opção 2: Deletar o bloqueio e deixar vago --}}
                    <button type="submit" name="action" value="release_slot"
                        class="w-full p-4 border-2 border-emerald-50 dark:border-gray-700 rounded-2xl hover:bg-emerald-50 dark:hover:bg-gray-700 transition text-left flex items-start gap-4 group">
                        <div class="bg-emerald-100 dark:bg-emerald-900 p-2 rounded-lg text-xl">🔓</div>
                        <div>
                            <span class="block font-black text-xs uppercase text-emerald-700 dark:text-emerald-400">
                                Liberar Horário (Vago)
                            </span>
                            <span class="block text-[10px] text-gray-500">
                                Deixa a quadra livre para novos agendamentos.
                            </span>
                        </div>
                    </button>

                    <button type="button" onclick="closeReactivateModal()"
                        class="w-full py-2 text-gray-400 text-[10px] font-bold uppercase hover:text-gray-600 transition">
                        Cancelar
                    </button>
                </form>
            </div>
        </div>
    </div>


    <script>
        window.currentReservaMaintenanceId = "{{ $reserva->id }}";
        // Variável global para armazenar o token obtido no modal de autorização
        window.currentAuthToken = '';

        // Dados da reserva (Constantes para mensagens e alertas)
        const clienteNome = "{{ $reserva->client_name }}".replace('🛠️ MANUTENÇÃO (', '').replace(')', '');
        const clienteContato = "{{ preg_replace('/\D/', '', $reserva->client_contact) }}";
        const reservaData = "{{ \Carbon\Carbon::parse($reserva->date)->format('d/m') }}";
        const reservaHora = "{{ \Carbon\Carbon::parse($reserva->start_time)->format('H:i') }}";
        const valorTotal = "{{ number_format($reserva->price, 2, ',', '.') }}";
        // Captura o valor pago atual da reserva (será 0 se foi transferido/estornado)
        const jaPagoNoAto = parseFloat("{{ $reserva->total_paid ?? 0 }}");

        function safeAddEventListener(id, event, callback) {
            const el = document.getElementById(id);
            if (el) el.addEventListener(event, callback);
        }

        function goBackAndReload() {
            if (document.referrer && document.referrer !== window.location.href) {
                window.location.href = document.referrer;
            } else {
                window.location.reload();
            }
        }


        // =========================================================================
        // 🛠️ LÓGICA DE MANUTENÇÃO (BLOQUEIO)
        // =========================================================================
        function openMaintenanceModal(reservaId, paidAmount) {
            const amount = parseFloat(paidAmount);
            const financeSection = document.getElementById('financeActionSection');

            document.querySelectorAll('.paid-amount-label').forEach(el => {
                el.textContent = amount.toLocaleString('pt-BR', {
                    minimumFractionDigits: 2
                });
            });

            if (amount <= 0) {
                if (financeSection) financeSection.classList.add('hidden');
            } else {
                if (financeSection) financeSection.classList.remove('hidden');
            }

            window.currentReservaMaintenanceId = reservaId;
            document.getElementById('maintenanceModal').classList.remove('hidden');
        }

        function closeMaintenanceModal() {
            document.getElementById('maintenanceModal').classList.add('hidden');
            document.getElementById('maintenance_reason').value = '';
        }

        safeAddEventListener('maintenanceForm', 'submit', function(e) {
            e.preventDefault();
            const reason = document.getElementById('maintenance_reason').value;
            const financeAction = this.querySelector('input[name="finance_action"]:checked')?.value || 'none';
            const submitBtn = document.getElementById('btnConfirmMaintenance');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            if (reason.length < 5) {
                alert('Por favor, descreva o motivo (mínimo 5 caracteres).');
                return;
            }

            submitBtn.disabled = true;
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'PROCESSANDO...';

            const url = "{{ route('admin.reservas.mover_manutencao', ':id') }}".replace(':id', window
                .currentReservaMaintenanceId);

            fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-AUTHORIZATION-TOKEN': window.currentAuthToken || '', // Envia o token se houver
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        reason: reason,
                        finance_action: financeAction
                    })
                })
                .then(async response => {
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.message || 'Erro no servidor');

                    if (data.success) {
                        if (data.whatsapp_link) localStorage.setItem('pending_wa_link', data.whatsapp_link);
                        setTimeout(() => {
                            window.location.reload();
                        }, 150);
                    }
                })
                .catch(error => {
                    alert('Falha na operação: ' + error.message);
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                });
        });

        // =========================================================================
        // 🔄 LÓGICA DE REATIVAÇÃO (FINALIZAR MANUTENÇÃO)
        // =========================================================================
        function openReactivateModal() {
            document.getElementById('reactivateDecisionModal').classList.remove('hidden');
        }

        function closeReactivateModal() {
            document.getElementById('reactivateDecisionModal').classList.add('hidden');
        }

        // Interceptar clique de restauração com alerta financeiro inteligente
        document.querySelectorAll('button[value="restore_client"]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                let subtituloFinanceiro = "";

                if (jaPagoNoAto <= 0) {
                    subtituloFinanceiro =
                        `\n\n⚠️ NOTA FINANCEIRA:\nComo o valor original foi transferido para outro horário ou estornado, esta reserva voltará com SALDO DEVEDOR TOTAL de R$ ${valorTotal}.`;
                }

                const confirmar = confirm(
                    `Deseja restaurar o horário das ${reservaHora}h para ${clienteNome}?` +
                    subtituloFinanceiro);

                if (!confirmar) {
                    e.preventDefault();
                } else {
                    // Se for um formulário POST comum (não fetch), injeta o token no campo hidden
                    const tokenInput = document.getElementById('reactivate_auth_token');
                    if (tokenInput) tokenInput.value = window.currentAuthToken;
                }
            });
        });

        // =========================================================================
        // 📱 WHATSAPP PENDENTE (PÓS-RELOAD)
        // =========================================================================
        window.addEventListener('load', () => {
            const pendingWA = localStorage.getItem('pending_wa_link');
            const manualCard = document.getElementById('waNotificationCardManual');
            const manualBtn = document.getElementById('waNotificationBtnManual');

            if (pendingWA && manualCard) {
                manualCard.classList.remove('hidden');
                manualBtn.href = pendingWA;
                localStorage.removeItem('pending_wa_link');
            }
        });
    </script>
</x-app-layout>