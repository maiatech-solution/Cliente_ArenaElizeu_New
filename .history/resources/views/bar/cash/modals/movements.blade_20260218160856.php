{{-- MODAL GENÉRICO DE MOVIMENTAÇÃO --}}
<div id="modalMovement"
    class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/90 backdrop-blur-sm p-4">
    <div
        class="bg-gray-900 border border-gray-800 w-full max-w-lg rounded-[3rem] shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">

        <div class="p-8 border-b border-gray-800 text-center">
            <h3 id="modalTitle" class="text-white text-2xl font-black uppercase italic">Movimentação</h3>
            <p class="text-gray-500 text-[10px] font-bold uppercase tracking-widest mt-1">Registro de movimentação manual do caixa</p>
        </div>

        <form action="{{ route('bar.cash.movement') }}" method="POST" class="p-8" id="formMovement">
            @csrf

            {{-- 🔑 CAMPOS EXIGIDOS PELA CONTROLLER --}}
            {{-- Injetamos o e-mail do gestor logado automaticamente para facilitar --}}
            <input type="hidden" name="supervisor_email"
                value="{{ in_array(auth()->user()->role, ['admin', 'gestor']) ? auth()->user()->email : '' }}">

            {{-- Este ID deve ser 'supervisor_password' para o script da index encontrar --}}
            <input type="hidden" name="supervisor_password">

            <input type="hidden" name="type" id="movementType">

            <div class="space-y-6">
                <div>
                    <label class="text-gray-500 uppercase text-[10px] font-black ml-4 mb-2 block tracking-widest text-center">
                        Valor (R$)
                    </label>
                    <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0,00"
                        class="w-full bg-black border-2 border-gray-800 rounded-3xl p-6 text-white text-4xl font-black text-center focus:border-orange-500 focus:outline-none transition-all shadow-inner font-mono">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-gray-500 uppercase text-[10px] font-black ml-4 mb-2 block tracking-widest text-center">
                            Método
                        </label>
                        <select name="payment_method" required
                            class="w-full bg-gray-800 border-none rounded-2xl p-4 text-white font-black text-xs uppercase outline-none focus:ring-2 focus:ring-orange-500 appearance-none text-center">
                            <option value="dinheiro" selected>💵 Dinheiro</option>
                            <option value="pix">📱 PIX</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-gray-500 uppercase text-[10px] font-black ml-4 mb-2 block tracking-widest text-center italic text-orange-500">
                            Senha Gestor
                        </label>
                        {{-- O ID deve ser 'password_direta_movimentacao' para bater com o script da sua Index --}}
                        <input type="password" id="password_direta_movimentacao" placeholder="******"
                            class="w-full bg-black border-2 border-gray-800 rounded-2xl p-4 text-white text-center text-sm outline-none focus:border-orange-500 font-mono">
                    </div>
                </div>

                <div>
                    <label class="text-gray-500 uppercase text-[10px] font-black ml-4 mb-2 block tracking-widest text-center">
                        Descrição / Motivo
                    </label>
                    <input type="text" name="description" required placeholder="Ex: Pagamento Fornecedor"
                        class="w-full bg-gray-800 border-none rounded-2xl p-4 text-white focus:ring-2 focus:ring-orange-500 outline-none font-medium text-center text-sm">
                </div>
            </div>

            <div class="flex gap-4 mt-10">
                <button type="button" onclick="closeModal('modalMovement')"
                    class="flex-1 py-4 bg-gray-800 text-gray-400 font-black rounded-2xl uppercase text-[10px] tracking-widest">
                    Cancelar
                </button>

                {{-- Chamamos a função 'enviarComAutorizacao' que já está na sua Index --}}
                <button type="button" onclick="enviarComAutorizacao('formMovement')" id="btnSubmit"
                    class="flex-1 py-4 bg-orange-600 text-white font-black rounded-2xl uppercase text-[10px] tracking-widest shadow-lg active:scale-95 transition-all">
                    Confirmar
                </button>
            </div>
        </form>
    </div>
</div>
