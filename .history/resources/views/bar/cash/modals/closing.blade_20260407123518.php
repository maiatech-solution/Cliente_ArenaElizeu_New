{{-- resources/views/bar/cash/modals/closing.blade.php --}}
<div id="modalFecharCaixa"
    class="hidden fixed inset-0 z-50 flex items-center justify-center bg-gray-950/70 backdrop-blur-sm p-4 overflow-hidden">
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
            {{-- 📊 DASHBOARD DE FECHAMENTO DETALHADO --}}
            <div class="space-y-4 mb-8">

                {{-- 📊 MATEMÁTICA DO CAIXA GERAL (Versão Compacta) --}}
                <div class="bg-black/40 p-5 rounded-3xl border border-gray-800 shadow-inner">
                    <span
                        class="text-[9px] font-black text-gray-500 uppercase block mb-3 tracking-[0.2em] border-b border-gray-800 pb-2">
                        🧮 CONFERÊNCIA UNIFICADA
                    </span>

                    {{-- Linhas secundárias em grid para economizar altura --}}
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1 mb-4 pb-3 border-b border-gray-800/50">
                        <div class="flex justify-between text-[10px]">
                            <span class="text-gray-500 uppercase italic">Abertura:</span>
                            <span class="text-white font-mono">R$
                                {{ number_format($currentSession->opening_balance ?? 0, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-[10px]">
                            <span class="text-gray-500 uppercase italic">Reforços:</span>
                            <span class="text-blue-500 font-mono">+
                                {{ number_format($reforcos ?? 0, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-[10px]">
                            <span class="text-emerald-500 uppercase italic font-bold">Dinheiro:</span>
                            <span class="text-emerald-500 font-mono">+
                                {{ number_format($vendasDinheiro ?? 0, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-[10px]">
                            <span class="text-red-500 uppercase italic font-bold">Sangrias:</span>
                            <span class="text-red-500 font-mono">-
                                {{ number_format($sangrias ?? 0, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-[10px] col-span-2 mt-1 pt-1 border-t border-gray-800/30">
                            <span class="text-cyan-400 uppercase italic font-bold">Digital (PIX/Cartão):</span>
                            <span class="text-cyan-400 font-mono">+ R$
                                {{ number_format($faturamentoDigital ?? 0, 2, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- Totalizador --}}
                    <div class="flex justify-between items-center">
                        <span class="text-[8px] font-black text-orange-500 uppercase tracking-widest leading-tight">
                            TOTAL ESPERADO<br>(GERAL)
                        </span>
                        <span class="text-white font-black text-3xl italic font-mono tracking-tighter">
                            R$
                            {{ number_format(($currentSession->opening_balance ?? 0) + ($vendasDinheiro ?? 0) + ($faturamentoDigital ?? 0) + ($reforcos ?? 0) - ($sangrias ?? 0), 2, ',', '.') }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    {{-- CARD 1: DIGITAL (Dinheiro que já caiu na conta) --}}
                    <div class="bg-black/40 p-5 rounded-3xl border border-gray-800">
                        <span
                            class="text-[9px] font-black text-cyan-400 uppercase block mb-1 tracking-widest leading-tight">
                            PIX / CARTÕES<br>(CONFERIR NO EXTRATO)
                        </span>
                        <span class="text-blue-400 font-black text-2xl italic font-mono">
                            R$ {{ number_format($faturamentoDigital ?? 0, 2, ',', '.') }}
                        </span>
                    </div>

                    {{-- CARD 2: FÍSICO (Dinheiro que tem que estar na gaveta agora) --}}
                    <div class="bg-orange-600/10 p-5 rounded-3xl border border-orange-600/20">
                        <span
                            class="text-[9px] font-black text-orange-500 uppercase block mb-1 tracking-widest leading-tight">
                            GAVETA FÍSICA<br>(NOTAS E MOEDAS)
                        </span>
                        <span class="text-green-500 font-black text-2xl italic font-mono">
                            {{-- Fórmula da Gaveta: Abertura + Vendas Cash + Reforços - Sangrias --}}
                            R$
                            {{ number_format(($currentSession->opening_balance ?? 0) + ($vendasDinheiro ?? 0) + ($reforcos ?? 0) - ($sangrias ?? 0), 2, ',', '.') }}
                        </span>
                    </div>
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
                            Valor Total do Turno (Gaveta + Digital)
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

                {{-- 🛡️ CAMPO DE AUTORIZAÇÃO UNIFICADO (AJUSTADO) --}}
                <div class="mt-6 p-5 bg-orange-600/5 border border-orange-600/20 rounded-[2rem] text-center">
                    @if (in_array(auth()->user()->role, ['admin', 'gestor']))
                        <span class="text-[9px] font-black text-orange-500 uppercase block mb-3 tracking-[0.2em]">
                            Confirmação de Segurança
                        </span>
                        <p class="text-[8px] text-gray-600 mb-2 uppercase font-bold italic">
                            Gestor: {{ auth()->user()->name }}. Confirme sua senha.
                        </p>
                        <input type="password" id="password_direta_gestor" placeholder="SUA SENHA"
                            class="w-full max-w-xs bg-black border border-gray-800 rounded-xl p-3 text-white text-center text-sm outline-none focus:border-orange-600 transition-all font-mono">
                    @else
                        {{-- Para colaborador, removemos o aviso de senha e o campo de e-mail --}}
                        <span class="text-[10px] font-black text-gray-500 uppercase block mb-2 tracking-widest">
                            Autorização Direta Habilitada
                        </span>
                        {{-- Campo oculto com valor "AUTO" para o JavaScript não barrar o envio --}}
                        <input type="hidden" id="password_direta_gestor" value="AUTO">
                    @endif
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
     * 📊 CÁLCULO DE QUEBRA/SOBRA TOTALIZADOR (Gaveta + Banco)
     * Considera: Saldo Inicial + Vendas Dinheiro + Vendas Digital + Reforços - Sangrias
     */
    function calcularDiferenca() {
        // 1. Captura as variáveis do Blade enviadas pela Controller
        const abertura = parseFloat("{{ $currentSession->opening_balance ?? 0 }}");
        const vendasDinheiro = parseFloat("{{ $vendasDinheiro ?? 0 }}");
        const faturamentoDigital = parseFloat("{{ $faturamentoDigital ?? 0 }}"); // <-- Adicionado
        const reforcos = parseFloat("{{ $reforcos ?? 0 }}");
        const sangrias = parseFloat("{{ $sangrias ?? 0 }}");

        // 2. Cálculo do Valor Esperado Geral (O que deve ter no total do turno)
        const totalEsperadoGeral = (abertura + vendasDinheiro + faturamentoDigital + reforcos) - sangrias;

        const input = document.getElementById('actual_balance_input');
        const contado = parseFloat(input.value) || 0;
        const display = document.getElementById('msg_diferenca');

        // 3. Estado Inicial: Quando o input está vazio
        if (input.value === "" || input.value === "0") {
            display.innerText = "INFORME O TOTAL (DINHEIRO + PIX). ESPERADO: R$ " + totalEsperadoGeral.toLocaleString(
                'pt-br', {
                    minimumFractionDigits: 2
                });
            display.className = "text-[10px] font-black uppercase tracking-widest text-orange-500 animate-pulse";
            return;
        }

        // 4. Cálculo da Diferença Real
        const diferenca = contado - totalEsperadoGeral;

        // 5. Lógica de Feedback Visual
        if (Math.abs(diferenca) < 0.01) {
            // Caso o valor bata exatamente
            display.innerText = "✅ CAIXA GERAL CONFERIDO (DINHEIRO + PIX)";
            display.className = "text-[10px] font-black uppercase tracking-widest text-green-500 font-bold";
        } else if (diferenca > 0) {
            // Caso haja sobra
            display.innerText = "➕ SOBRA NO CAIXA GERAL: R$ " + diferenca.toLocaleString('pt-br', {
                minimumFractionDigits: 2
            });
            display.className = "text-[10px] font-black uppercase tracking-widest text-blue-400";
        } else {
            // Caso haja falta (Quebra)
            display.innerText = "⚠️ QUEBRA NO CAIXA GERAL: R$ " + Math.abs(diferenca).toLocaleString('pt-br', {
                minimumFractionDigits: 2
            });
            display.className = "text-[10px] font-black uppercase tracking-widest text-red-500";
        }
    }
</script>
