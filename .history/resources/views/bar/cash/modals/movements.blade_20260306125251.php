{{-- resources/views/bar/cash/modals/movements.blade.php --}}
<div id="modalMovement"
    class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/95 backdrop-blur-md p-4">
    <div
        class="bg-gray-900 border border-gray-800 w-full max-w-lg rounded-[3rem] shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200 shadow-orange-900/10">

        <div class="p-8 border-b border-gray-800 text-center bg-black/20">
            <h3 id="modalTitle" class="text-white text-2xl font-black uppercase italic tracking-tighter">Movimentação</h3>
            <p class="text-gray-500 text-[10px] font-bold uppercase tracking-widest mt-1">Registro de movimentação manual
                ({{ auth()->user()->name }})</p>
        </div>

        <form action="{{ route('bar.cash.movement') }}" method="POST" class="p-8" id="formMovement">
            @csrf

            {{-- 🔑 CAMPOS DE AUTORIZAÇÃO --}}
            {{-- Injetamos o e-mail do gestor logado se for o caso, ou deixamos para o JS preencher --}}
            <input type="hidden" name="supervisor_email"
                value="{{ in_array(auth()->user()->role, ['admin', 'gestor']) ? auth()->user()->email : '' }}">

            {{-- O JS da Index vai preencher este campo com a senha digitada no input visível --}}
            <input type="hidden" name="supervisor_password">

            {{-- Tipo: Sangria ou Reforço (preenchido pelo JS) --}}
            <input type="hidden" name="type" id="movementType">

            <div class="space-y-6">
                {{-- VALOR --}}
                <div>
                    <label
                        class="text-gray-500 uppercase text-[9px] font-black ml-4 mb-2 block tracking-widest text-center">
                        Valor da Operação (R$)
                    </label>
                    <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0,00"
                        class="w-full bg-black border-2 border-gray-800 rounded-3xl p-6 text-white text-4xl font-black text-center focus:border-orange-500 focus:outline-none transition-all shadow-inner font-mono">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    {{-- MÉTODO --}}
                    <div>
                        <label
                            class="text-gray-500 uppercase text-[9px] font-black ml-4 mb-2 block tracking-widest text-center">
                            Origem/Destino
                        </label>
                        <select name="payment_method" required
                            class="w-full bg-gray-800 border border-gray-700 rounded-2xl p-4 text-white font-black text-xs uppercase outline-none focus:ring-2 focus:ring-orange-500 appearance-none text-center">
                            <option value="dinheiro" selected>💵 Dinheiro (Gaveta)</option>
                            <option value="pix">📱 PIX (Conta)</option>
                        </select>
                    </div>
                    {{-- SENHA --}}
                    <div>
                        <label
                            class="text-gray-500 uppercase text-[9px] font-black ml-4 mb-2 block tracking-widest text-center italic text-orange-500">
                            Autorização Gestor
                        </label>
                        {{-- ID crucial para o window.enviarComAutorizacao --}}
                        <input type="password" id="password_direta_movimentacao" placeholder="******"
                            class="w-full bg-black border-2 border-gray-800 rounded-2xl p-4 text-white text-center text-sm outline-none focus:border-orange-500 font-mono">
                    </div>
                </div>

                {{-- DESCRIÇÃO --}}
                <div>
                    <label
                        class="text-gray-500 uppercase text-[9px] font-black ml-4 mb-2 block tracking-widest text-center">
                        Descrição / Motivo Justificado
                    </label>
                    <input type="text" name="description" required placeholder="Ex: Sangria para depósito bancário"
                        class="w-full bg-gray-800 border border-gray-700 rounded-2xl p-4 text-white focus:ring-2 focus:ring-orange-500 outline-none font-medium text-center text-sm">
                </div>
            </div>

            <div class="flex gap-4 mt-10">
                <button type="button" onclick="closeModal('modalMovement')"
                    class="flex-1 py-4 bg-gray-800 text-gray-500 font-black rounded-2xl uppercase text-[10px] tracking-widest hover:text-white transition-all">
                    Cancelar
                </button>

                <button type="button" onclick="enviarComAutorizacao('formMovement')" id="btnSubmit"
                    class="flex-1 py-4 bg-orange-600 text-white font-black rounded-2xl uppercase text-[10px] tracking-widest shadow-lg active:scale-95 transition-all">
                    Confirmar Registro
                </button>
            </div>
        </form>
    </div>
</div>
