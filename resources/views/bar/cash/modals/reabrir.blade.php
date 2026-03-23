{{-- 🔓 MODAL DE REABERTURA --}}
<div id="modalReabrirCaixa" class="fixed inset-0 bg-black/95 backdrop-blur-md z-[100] hidden flex items-center justify-center p-4">
    <div class="bg-gray-900 border-2 border-orange-600 w-full max-w-md rounded-[3.5rem] p-10 shadow-2xl shadow-orange-900/20">
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-orange-600/10 rounded-3xl flex items-center justify-center mx-auto mb-6 text-4xl shadow-inner">🔓</div>
            <h3 class="text-white text-2xl font-black uppercase italic tracking-tighter">Reabrir Turno</h3>
            <p id="infoReabertura" class="text-gray-500 text-[10px] font-bold uppercase tracking-widest mt-3 px-6 leading-relaxed"></p>
        </div>

        <form action="{{ route('bar.cash.reopen') }}" method="POST" id="formReopen">
            @csrf
            <input type="hidden" name="session_id" id="reopen_session_id">
            <input type="hidden" name="supervisor_email" value="{{ auth()->user()->email }}">
            <input type="hidden" name="supervisor_password" id="reopen_supervisor_password_hidden">

            <div class="mb-8 p-6 bg-black/40 rounded-3xl border border-gray-800">
                <label class="text-[9px] font-black text-orange-500 uppercase block mb-4 text-center tracking-widest italic">Confirmação de Gestor</label>
                <input type="password" id="password_reabertura_visivel" placeholder="SENHA DO GESTOR"
                    class="w-full bg-black border-2 border-gray-800 rounded-2xl p-5 text-white text-center text-lg outline-none focus:border-orange-600 transition-all font-mono">
            </div>

            <div class="flex gap-4">
                <button type="button" onclick="closeModal('modalReabrirCaixa')"
                    class="flex-1 py-5 bg-gray-800 text-gray-500 font-black rounded-2xl uppercase text-[10px] tracking-widest">Cancelar</button>
                <button type="button" onclick="executarReabertura()"
                    class="flex-1 py-5 bg-orange-600 text-white font-black rounded-2xl uppercase text-[10px] tracking-widest shadow-lg">Confirmar</button>
            </div>
        </form>
    </div>
</div>
