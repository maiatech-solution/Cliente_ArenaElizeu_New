{{-- resources/views/bar/cash/modals/closing.blade.php --}}
<div id="modalFecharCaixa"
    class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/95 backdrop-blur-md p-4">
    <div
        class="bg-gray-900 border border-gray-800 w-full max-w-2xl rounded-[2.5rem] shadow-2xl overflow-hidden shadow-orange-900/20">

        {{-- HEADER COMPACTO --}}
        <div class="p-6 border-b border-gray-800 flex items-center justify-between bg-black/20">
            <div class="flex items-center gap-4">
                <div
                    class="w-10 h-10 bg-orange-600/20 rounded-xl flex items-center justify-center border border-orange-600/30">
                    <span class="text-xl">🔒</span>
                </div>
                <h3 class="text-white text-xl font-black uppercase italic tracking-tighter">Encerrar Turno</h3>
            </div>
            <div class="text-right">
                <span class="text-[8px] font-black text-gray-500 uppercase block tracking-widest">Responsável</span>
                <span class="text-gray-300 text-[10px] font-bold uppercase italic">{{ auth()->user()->name }}</span>
            </div>
        </div>

        <div class="p-8">
            {{-- 📊 DASHBOARD DE FECHAMENTO --}}
            <div class="space-y-4 mb-8">
                <div class="grid grid-cols-2 gap-4">
                    {{-- GAVETA (Dinheiro Físico) --}}
                    <div class="bg-black/40 p-5 rounded-3xl border border-gray-800">
                        <span
                            class="text-[9px] font-black text-emerald-500 uppercase block mb-1 tracking-widest leading-tight">
                            Esperado em Espécie<br>(Gaveta Física)
                        </span>
                        <span class="text-white font-black text-2xl italic font-mono">
                            R$ {{ number_format($dinheiroGeral ?? 0, 2, ',', '.') }}
                        </span>
                    </div>

                    {{-- DIGITAL (PIX e Cartões) --}}
                    <div class="bg-black/40 p-5 rounded-3xl border border-gray-800">
                        <span
                            class="text-[9px] font-black text-cyan-400 uppercase block mb-1 tracking-widest leading-tight">
                            Faturamento Digital<br>(PIX/Cartões)
                        </span>
                        <span class="text-blue-400 font-black text-2xl italic font-mono">
                            R$ {{ number_format($faturamentoDigital ?? 0, 2, ',', '.') }}
                        </span>
                    </div>
                </div>

                {{-- BRUTO TOTAL (Vendas Líquidas da Sessão) --}}
                <div
                    class="bg-orange-600/10 p-5 rounded-3xl border border-orange-600/20 flex justify-between items-center px-8 shadow-inner">
                    <div>
                        <span class="text-[10px] font-black text-orange-500 uppercase block tracking-[0.2em]">
                            Faturamento Líquido Total
                        </span>
                        <p class="text-[8px] text-gray-500 font-bold uppercase italic mt-1">
                            (Total de vendas realizadas nesta sessão)
                        </p>
                    </div>
                    <span class="text-green-500 font-black text-4xl italic tracking-tighter font-mono">
                        R$ {{ number_format($totalBruto ?? 0, 2, ',', '.') }}
                    </span>
                </div>
            </div>

            <form action="{{ route('bar.cash.close') }}" method="POST" id="formCloseCash">
                @csrf

                {{-- 🔑 CAMPOS DE ESPELHO --}}
                <input type="hidden" name="supervisor_email"
                    value="{{ in_array(auth()->user()->role, ['admin', 'gestor']) ? auth()->user()->email : '' }}">
                <input type="hidden" name="supervisor_password" id="mirror_password_closing">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- VALOR CONTADO --}}
                    <div>
                        <label class="text-gray-500 uppercase text-[10px] font-black ml-2 mb-2 block tracking-widest">
                            Valor total em caixa (Dinheiro + Digital)
                        </label>
                        <input type="number" name="actual_balance" id="actual_balance_input" step="0.01"
                            min="0" required placeholder="0,00" oninput="calcularDiferenca()"
                            class="w-full bg-black border-2 border-gray-800 rounded-2xl p-6 text-white text-4xl font-black text-center focus:border-orange-600 outline-none transition-all shadow-inner font-mono">

                        {{-- 📊 DISPLAY DE DIFERENÇA EM TEMPO REAL --}}
                        <div id="display_diferenca" class="mt-2 text-center h-4">
                            <span id="msg_diferenca" class="text-[10px] font-black uppercase tracking-widest"></span>
                        </div>
                    </div>

                    {{-- OBSERVAÇÕES --}}
                    <div>
                        <label class="text-gray-500 uppercase text-[10px] font-black ml-2 mb-2 block tracking-widest">
                            Observações do Turno
                        </label>
                        <textarea name="notes" rows="3" placeholder="Ex: Diferença de troco..."
                            class="w-full bg-gray-800 border-none rounded-2xl p-4 text-white placeholder-gray-600 focus:ring-1 focus:ring-orange-600 outline-none text-xs h-[100px]"></textarea>
                    </div>
                </div>

                {{-- 🛡️ CAMPO DE AUTORIZAÇÃO UNIFICADO --}}
                <div class="mt-6 p-5 bg-orange-600/5 border border-orange-600/20 rounded-[2rem] text-center">
                    @if (in_array(auth()->user()->role, ['admin', 'gestor']))
                        {{-- Se for o GESTOR logado --}}
                        <span class="text-[9px] font-black text-orange-500 uppercase block mb-3 tracking-[0.2em]">
                            Confirmação de Segurança
                        </span>
                        <p class="text-[8px] text-gray-600 mb-2 uppercase font-bold italic">
                            Você está logado como {{ auth()->user()->name }}. Confirme sua senha.
                        </p>
                    @else
                        {{-- Se for o COLABORADOR logado --}}
                        <span
                            class="text-[9px] font-black text-orange-500 uppercase block mb-2 tracking-widest animate-pulse">
                            🔒 Autorização do Gestor Necessária
                        </span>
                        <input type="email" id="email_supervisor_fechamento" placeholder="E-MAIL DO GESTOR"
                            class="w-full max-w-xs bg-black border border-gray-800 rounded-xl p-3 text-white text-center text-[10px] mb-2 outline-none focus:border-orange-600 transition-all font-mono uppercase">
                    @endif

                    {{-- Este campo SEMPRE deve existir para o JS não dar erro --}}
                    <input type="password" id="password_direta_gestor" placeholder="SENHA DO GESTOR"
                        class="w-full max-w-xs bg-black border border-gray-800 rounded-xl p-3 text-white text-center text-sm outline-none focus:border-orange-600 transition-all font-mono">
                </div>

                <div class="grid grid-cols-2 gap-4 mt-8">
                    <button type="button" onclick="closeModalClosing()"
                        class="py-4 text-gray-500 font-black rounded-2xl uppercase text-[10px] tracking-widest hover:bg-gray-800 hover:text-white transition-all">
                        Cancelar
                    </button>
                    <button type="button" onclick="enviarComAutorizacao('formCloseCash')"
                        class="py-4 bg-orange-600 hover:bg-orange-500 text-white font-black rounded-2xl uppercase text-[10px] tracking-widest transition-all shadow-lg shadow-orange-900/40 active:scale-95">
                        Encerrar Turno
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openModalClosing() {
        const modal = document.getElementById('modalFecharCaixa');
        if (modal) {
            modal.classList.remove('hidden');
            setTimeout(() => {
                const input = modal.querySelector('input[name="actual_balance"]');
                if (input) input.focus();
            }, 100);
        }
    }

    function closeModalClosing() {
        const modal = document.getElementById('modalFecharCaixa');
        if (modal) {
            modal.classList.add('hidden');
        }
    }


    /**
     * 📊 CÁLCULO DE QUEBRA/SOBRA (Gaveta Física)
     * Compara o valor contado apenas com o que deve existir em espécie.
     */
    function calcularDiferenca() {
        // 🎯 O AJUSTE DE MESTRE:
        // Somamos o Faturamento Líquido ($totalBruto) com o Fundo de Abertura.
        const faturamentoTurno = {{ $totalBruto ?? 0 }};
        const fundoAbertura = {{ $currentSession->opening_balance ?? 0 }};
        const totalEsperadoSistema = faturamentoTurno + fundoAbertura;

        const input = document.getElementById('actual_balance_input');
        const contado = parseFloat(input.value) || 0;
        const display = document.getElementById('msg_diferenca');

        // Se o campo estiver vazio ou zero
        if (input.value === "" || input.value === "0") {
            display.innerText = "DIGITE O TOTAL: VENDAS + TROCO (R$ " + totalEsperadoSistema.toLocaleString('pt-br', {
                minimumFractionDigits: 2
            }) + ")";
            display.className = "text-[10px] font-black uppercase tracking-widest text-orange-500 animate-pulse";
            return;
        }

        // Diferença contra o total absoluto (Vendas + Fundo)
        const diferenca = contado - totalEsperadoSistema;

        if (Math.abs(diferenca) < 0.01) {
            display.innerText = "✅ VALOR TOTAL CONFERIDO";
            display.className = "text-[10px] font-black uppercase tracking-widest text-green-500 font-bold";
        } else if (diferenca > 0) {
            display.innerText = "➕ SOBRA GERAL: R$ " + diferenca.toLocaleString('pt-br', {
                minimumFractionDigits: 2
            });
            display.className = "text-[10px] font-black uppercase tracking-widest text-blue-400";
        } else {
            display.innerText = "⚠️ FALTA NO TOTAL: R$ " + Math.abs(diferenca).toLocaleString('pt-br', {
                minimumFractionDigits: 2
            });
            display.className = "text-[10px] font-black uppercase tracking-widest text-red-500";
        }
    }
</script>
