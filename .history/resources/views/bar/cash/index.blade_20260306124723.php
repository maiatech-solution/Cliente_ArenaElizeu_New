<x-bar-layout>
    <div class="max-w-[1600px] mx-auto px-6 py-8">

        @if (isset($caixaVencido) && $caixaVencido)
            <div
                class="mb-8 bg-red-600 border-2 border-white/20 p-6 rounded-[2rem] flex items-center justify-between animate-pulse">
                <div class="flex items-center gap-4">
                    <span class="text-3xl">⚠️</span>
                    <div>
                        <h4 class="text-white font-black uppercase italic leading-none">
                            Atenção: Turno de {{ $openSession->user->name ?? 'Operador' }} Vencido!
                        </h4>
                        <p class="text-white/80 text-[10px] font-bold uppercase tracking-widest mt-1">
                            Este caixa foi aberto em
                            {{ \Carbon\Carbon::parse($openSession->opened_at)->format('d/m \à\s H:i') }}.

                            @if ($openSession->user_id !== auth()->id())
                                <span class="text-yellow-300 underline font-black">
                                    Aguarde o encerramento do turno do colega pelo Gestor.
                                </span>
                            @else
                                Finalize seu movimento pendente antes de iniciar o de hoje.
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Só permite clicar no botão de fechar se for o dono do caixa ou admin --}}
                @if ($openSession->user_id === auth()->id() || in_array(auth()->user()->role, ['admin', 'gestor']))
                    <button onclick="tentarEncerrarTurno()"
                        class="bg-white text-red-600 px-6 py-2 rounded-xl font-black uppercase text-[10px] hover:scale-105 transition-all shadow-lg">
                        Fechar Agora
                    </button>
                @endif
            </div>
        @endif

        {{-- HEADER COM FILTRO DE DATA --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-6 mb-10">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-white text-4xl font-black uppercase italic tracking-tighter">Gestão de <span
                            class="text-orange-500">Caixa</span></h1>
                    <span
                        class="px-3 py-1 bg-gray-800 text-gray-500 text-[10px] font-black rounded-lg uppercase border border-gray-700">Módulo
                        Bar</span>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <form action="{{ route('bar.cash.index') }}" method="GET" id="filterForm"
                        class="flex items-center gap-2">
                        <input type="date" name="date" value="{{ $date ?? date('Y-m-d') }}"
                            onchange="document.getElementById('filterForm').submit()"
                            class="bg-gray-900 border-2 border-gray-800 rounded-xl px-4 py-2 text-white text-xs font-black outline-none focus:border-green-500 transition-all">

                        @if (isset($date) && $date != date('Y-m-d'))
                            <a href="{{ route('bar.cash.index') }}"
                                class="text-[10px] font-black text-orange-500 uppercase underline tracking-widest ml-2">Voltar
                                para Hoje</a>
                        @endif
                    </form>
                </div>
            </div>

            {{-- BOTÕES DE AÇÃO COM TRAVA DE SEGURANÇA --}}
            @if ($openSession)
                <div class="flex flex-wrap gap-3">
                    <button onclick="requisitarAutorizacao(() => openModalMovement('sangria'))"
                        class="px-6 py-3 bg-red-600/10 border border-red-600/20 text-red-500 font-bold rounded-2xl uppercase text-xs hover:bg-red-600 hover:text-white transition-all shadow-lg">
                        🔻 Sangria
                    </button>

                    <button onclick="requisitarAutorizacao(() => openModalMovement('reforco'))"
                        class="px-6 py-3 bg-blue-600/10 border border-blue-600/20 text-blue-500 font-bold rounded-2xl uppercase text-xs hover:bg-blue-600 hover:text-white transition-all shadow-lg">
                        🔺 Reforço
                    </button>

                    {{-- Na index.blade.php --}}
                    <button type="button" onclick="tentarEncerrarTurno()"
                        class="px-8 py-3 bg-white text-black font-black rounded-2xl uppercase text-[10px] tracking-widest hover:scale-105 transition-all shadow-xl border-b-4 border-gray-300">
                        🔒 Encerrar Turno
                    </button>

                    <script>
                        /**
                         * 🛡️ Verificação de Pré-fechamento
                         * Impede que o modal de autorização abra se houver pendências
                         */
                        function tentarEncerrarTurno() {
                            // Pega a variável injetada pelo PHP
                            const mesasAbertas = {{ $mesasAbertasCount }};

                            if (mesasAbertas > 0) {
                                // Exibe o erro e mata a execução aqui
                                alert("⚠️ OPERAÇÃO BLOQUEADA\n\nExistem " + mesasAbertas +
                                    " mesa(s) aberta(s) no sistema.\nVocê precisa finalizar todos os pagamentos antes de fechar o caixa."
                                );
                                return;
                            }

                            // Se não houver mesas, segue o fluxo normal de autorização
                            requisitarAutorizacao(() => openModalClosing());
                        }
                    </script>
                </div>
            @endif
        </div>

        {{-- LÓGICA DE EXIBIÇÃO CENTRAL --}}
        @if (!$openSession && $date == date('Y-m-d'))
            <div class="max-w-xl mx-auto mt-20 text-center animate-in fade-in slide-in-from-bottom-4 duration-500">
                <div class="bg-gray-900 rounded-[3rem] p-12 border border-gray-800 shadow-2xl shadow-green-900/5">
                    <div
                        class="w-20 h-20 bg-gray-800 rounded-3xl flex items-center justify-center mx-auto mb-6 border border-gray-700 text-4xl text-gray-400">
                        🔓</div>
                    <h2 class="text-white text-2xl font-black uppercase mb-2">Novo Turno</h2>
                    <p class="text-gray-500 mb-8 uppercase text-[10px] font-bold tracking-widest leading-relaxed px-10">
                        Não há sessões de caixa ativas no momento. <br>Inicie um novo turno para processar vendas.
                    </p>

                    <form action="{{ route('bar.cash.open') }}" method="POST" id="formOpenCash">
                        @csrf

                        {{-- 🔑 CAMPOS DE ESPELHO --}}
                        <input type="hidden" name="supervisor_email"
                            value="{{ in_array(auth()->user()->role, ['admin', 'gestor']) ? auth()->user()->email : '' }}">
                        <input type="hidden" name="supervisor_password">

                        <div class="text-left mb-6">
                            <label
                                class="text-gray-500 uppercase text-[10px] font-black ml-4 mb-2 block tracking-widest">
                                Troco Inicial de Gaveta
                            </label>
                            <input type="number" name="opening_balance" step="0.01" value="0.00" required
                                class="w-full bg-black border-2 border-gray-800 rounded-3xl p-6 text-white text-3xl font-black text-center focus:border-green-500 outline-none transition-all shadow-inner font-mono">
                        </div>

                        {{-- 🛡️ CAMPO DE AUTORIZAÇÃO (Aberto para todos, mas com lógica inteligente) --}}
                        <div class="mb-6 p-4 bg-gray-800/50 border border-gray-800 rounded-3xl text-center">
                            @if (in_array(auth()->user()->role, ['admin', 'gestor']))
                                {{-- Se for o dono/gestor logado --}}
                                <span
                                    class="text-[9px] font-black text-orange-500 uppercase block mb-2 tracking-widest">Confirme
                                    sua Senha</span>
                            @else
                                {{-- Se for o colaborador logado --}}
                                <span
                                    class="text-[9px] font-black text-orange-500 uppercase block mb-2 tracking-widest animate-pulse">🔒
                                    Senha do Gestor Necessária</span>
                                <input type="email" id="email_supervisor_abertura" placeholder="E-MAIL DO GESTOR"
                                    class="w-full max-w-xs bg-black border border-gray-800 rounded-xl p-3 text-white text-center text-[10px] mb-2 outline-none focus:border-orange-500 transition-all font-mono">
                            @endif

                            <input type="password" id="password_direta_abertura" placeholder="DIGITE A SENHA"
                                class="w-full max-w-xs bg-black border border-gray-800 rounded-xl p-3 text-white text-center text-sm outline-none focus:border-orange-500 transition-all font-mono">
                        </div>

                        {{-- 🚀 BOTÃO --}}
                        <button type="button" onclick="enviarComAutorizacao('formOpenCash')"
                            class="w-full py-6 bg-green-600 hover:bg-green-500 text-white font-black rounded-3xl uppercase tracking-widest shadow-lg shadow-green-900/40 transition-all active:scale-95">
                            Abrir Turno de Trabalho
                        </button>
                    </form>
                </div>
            </div>
        @elseif(!$currentSession)
            <div class="py-20 text-center opacity-20">
                <p class="text-gray-600 font-black uppercase tracking-widest italic text-3xl">Nenhum registo nesta data
                </p>
            </div>
        @else
            {{-- CARDS FINANCEIROS --}}
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-10">
                {{-- 💵 DINHEIRO REAL NA MÃO --}}
                <div
                    class="bg-gray-900 p-8 rounded-[2.5rem] border border-gray-800 relative shadow-2xl border-l-4 border-l-emerald-500">
                    <span
                        class="text-[10px] font-black text-gray-500 uppercase tracking-widest block mb-2 italic">Dinheiro
                        em Gaveta</span>
                    <span class="text-4xl font-black text-white italic tracking-tighter font-mono">
                        R$ {{ number_format($dinheiroGeral ?? 0, 2, ',', '.') }}
                    </span>
                </div>

                {{-- ⚡ PIX RECEBIDO --}}
                @php
                    $vendasPix = $movements->where('payment_method', 'pix')->where('type', 'venda')->sum('amount');
                    $estornosPix = $movements->where('payment_method', 'pix')->where('type', 'estorno')->sum('amount');
                    $totalPix = max(0, $vendasPix - $estornosPix);
                @endphp
                <div
                    class="bg-gray-900 p-8 rounded-[2.5rem] border border-gray-800 shadow-2xl border-l-4 border-l-cyan-400">
                    <span class="text-[10px] font-black text-cyan-400 uppercase tracking-widest block mb-2 italic">Total
                        em PIX</span>
                    <span class="text-4xl font-black text-white italic tracking-tighter font-mono">
                        R$ {{ number_format($totalPix, 2, ',', '.') }}
                    </span>
                </div>

                {{-- 💳 CARTÕES (DÉBITO/CRÉDITO/MISTO) --}}
                @php
                    $metodosCartao = ['cartao', 'debito', 'credito', 'misto'];
                    $vendasCartao = $movements
                        ->whereIn('payment_method', $metodosCartao)
                        ->where('type', 'venda')
                        ->sum('amount');
                    $estornosCartao = $movements
                        ->whereIn('payment_method', $metodosCartao)
                        ->where('type', 'estorno')
                        ->sum('amount');
                    $totalCartao = max(0, $vendasCartao - $estornosCartao);
                @endphp
                <div
                    class="bg-gray-900 p-8 rounded-[2.5rem] border border-gray-800 shadow-2xl border-l-4 border-l-purple-500">
                    <span
                        class="text-[10px] font-black text-purple-400 uppercase tracking-widest block mb-2 italic">Cartões
                        (Líquido)</span>
                    <span class="text-4xl font-black text-white italic tracking-tighter font-mono">
                        R$ {{ number_format($totalCartao, 2, ',', '.') }}
                    </span>
                </div>

                {{-- 🔻 TOTAL DE SANGRIAS --}}
                <div
                    class="bg-gray-900 p-8 rounded-[2.5rem] border border-gray-800 shadow-2xl border-l-4 border-l-red-500">
                    <span
                        class="text-[10px] font-black text-gray-500 uppercase tracking-widest block mb-2 italic">Sangrias
                        / Saídas</span>
                    <span class="text-4xl font-black text-white italic tracking-tighter font-mono">
                        R$ {{ number_format($sangrias ?? 0, 2, ',', '.') }}
                    </span>
                </div>

                {{-- 🚫 RESUMO DE CANCELAMENTOS --}}
                @php $totalEstornos = $movements->where('type', 'estorno')->sum('amount'); @endphp
                <div
                    class="bg-gray-900 p-8 rounded-[2.5rem] border border-gray-800 shadow-2xl border-l-4 {{ $totalEstornos > 0 ? 'border-l-orange-600' : 'border-l-gray-700 opacity-50' }}">
                    <span
                        class="text-[10px] font-black {{ $totalEstornos > 0 ? 'text-orange-500' : 'text-gray-500' }} uppercase tracking-widest block mb-2 italic">Total
                        Estornado</span>
                    <span
                        class="text-4xl font-black {{ $totalEstornos > 0 ? 'text-white' : 'text-gray-600' }} italic tracking-tighter font-mono">
                        R$ {{ number_format($totalEstornos, 2, ',', '.') }}
                    </span>
                </div>
            </div>

            {{-- HISTÓRICO DE MOVIMENTAÇÕES --}}
            <div class="bg-gray-900 rounded-[3rem] border border-gray-800 overflow-hidden shadow-2xl">
                <div class="p-8 border-b border-gray-800 flex justify-between items-center bg-gray-800/20">
                    <h3 class="text-white font-black uppercase italic tracking-widest text-lg">Histórico do Turno</h3>
                    <div class="flex items-center gap-4">
                        <span
                            class="text-[10px] text-gray-600 font-bold uppercase tracking-tighter italic font-black underline decoration-green-500/30 underline-offset-4">
                            Faturado: R$ {{ number_format($totalBruto ?? 0, 2, ',', '.') }}
                        </span>
                        <span
                            class="{{ $currentSession->status == 'open' ? 'text-green-500 animate-pulse' : 'text-red-500' }} text-[10px] font-black uppercase tracking-widest flex items-center gap-2">
                            <span
                                class="w-2 h-2 {{ $currentSession->status == 'open' ? 'bg-green-500' : 'bg-red-500' }} rounded-full"></span>
                            {{ $currentSession->status == 'open' ? 'Aberto' : 'Fechado' }}
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr
                                class="text-gray-500 text-[10px] font-black uppercase tracking-widest border-b border-gray-800 bg-black/20">
                                <th class="p-6">Hora</th>
                                <th class="p-6">Descrição</th>
                                <th class="p-6">Operador</th>
                                <th class="p-6 text-right font-black">Valor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/50">
                            @forelse($movements as $mov)
                                @php
                                    // 1. Lógica de cores e sinais
                                    $isSaida = in_array($mov->type, ['sangria', 'estorno']);
                                    $isVenda = in_array($mov->type, ['venda', 'reforco']);

                                    $corValor = 'text-white';
                                    if ($isSaida) {
                                        $corValor = 'text-red-500';
                                    }
                                    if ($isVenda) {
                                        $corValor = 'text-green-500';
                                    }

                                    // 2. Lógica visual para a Forma de Pagamento
                                    $metodo = strtolower($mov->payment_method);
                                    $bgMetodo = 'bg-gray-800 text-gray-400 border-gray-700';

                                    if ($metodo == 'dinheiro') {
                                        $bgMetodo = 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
                                    } elseif (in_array($metodo, ['pix', 'transferencia'])) {
                                        $bgMetodo = 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20';
                                    } elseif (in_array($metodo, ['cartao', 'debito', 'credito', 'misto'])) {
                                        $bgMetodo = 'bg-purple-500/10 text-purple-400 border-purple-500/20';
                                    }

                                    // 3. Lógica de Auditoria (Desmembrando Título | Motivo | Autorizador)
                                    $partesMotivo = explode(' | MOTIVO: ', $mov->description);
                                    $tituloDescricao = $partesMotivo[0];

                                    $resto = $partesMotivo[1] ?? '';
                                    $partesAutorizador = explode(' | POR: ', $resto);

                                    $motivoTexto = $partesAutorizador[0] ?? null;
                                    $autorizadorNome = $partesAutorizador[1] ?? null;
                                @endphp
                                <tr class="hover:bg-white/[0.02] transition-colors group">
                                    <td class="p-6 text-gray-500 font-bold text-xs">
                                        {{ $mov->created_at->format('H:i') }}
                                    </td>
                                    <td class="p-6">
                                        {{-- Título Principal --}}
                                        <span class="text-white block font-black text-xs uppercase tracking-tight">
                                            {{ $tituloDescricao }}
                                        </span>

                                        {{-- Exibição do Motivo (Laranja) --}}
                                        @if ($motivoTexto)
                                            <span class="text-[10px] text-orange-400 font-bold italic block mt-1">
                                                💬 Motivo: {{ $motivoTexto }}
                                            </span>
                                        @endif

                                        {{-- Exibição do Autorizador (Índigo/Roxo) --}}
                                        @if ($autorizadorNome)
                                            <span
                                                class="text-[9px] text-indigo-400 font-black uppercase tracking-widest block mt-1">
                                                🔐 Autorizado por: {{ $autorizadorNome }}
                                            </span>
                                        @endif

                                        <div class="flex items-center gap-2 mt-2">
                                            {{-- Badge do Tipo --}}
                                            <span
                                                class="text-[8px] uppercase font-black px-2 py-0.5 rounded border {{ $isSaida ? 'bg-red-500/10 text-red-500 border-red-500/20' : 'bg-blue-500/10 text-blue-500 border-blue-500/20' }}">
                                                {{ $mov->type }}
                                            </span>

                                            {{-- Badge da Forma de Pagamento --}}
                                            @if ($mov->payment_method)
                                                <span
                                                    class="text-[8px] uppercase font-black px-2 py-0.5 rounded border {{ $bgMetodo }}">
                                                    💳 {{ $mov->payment_method }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td
                                        class="p-6 text-gray-400 text-[10px] font-bold uppercase italic tracking-widest">
                                        {{ $mov->user->name }}
                                    </td>
                                    <td class="p-6 text-right font-black italic text-xl {{ $corValor }}">
                                        {{ $isSaida ? '-' : ($isVenda ? '+' : '') }} R$
                                        {{ number_format($mov->amount, 2, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="p-24 text-center opacity-20">
                                        <p class="text-gray-600 font-black uppercase tracking-widest italic text-3xl">
                                            Sem movimentações
                                        </p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    @include('bar.cash.modals.movements')
    @include('bar.cash.modals.closing')

    <script>
        // 🧠 MEMÓRIA GLOBAL PARA CAPTURA DE CREDENCIAIS
        window.supervisorMemoriaEmail = "";
        window.supervisorMemoriaPass = "";

        // 1. MONITOR DE INPUT (Captura em tempo real)
        document.addEventListener('input', function(e) {
            const t = e.target;
            if (t.type === 'email' || t.name === 'supervisor_email') {
                window.supervisorMemoriaEmail = t.value;
            }
            if (t.type === 'password' || t.name === 'supervisor_password' || t.id.includes('password_direta')) {
                window.supervisorMemoriaPass = t.value;
            }
        });

        /**
         * 2. TRAVA DE SEGURANÇA: MESAS ABERTAS
         */
        function tentarEncerrarTurno() {
            const mesasAbertas = {{ $mesasAbertasCount ?? 0 }};
            if (mesasAbertas > 0) {
                alert("⚠️ OPERAÇÃO BLOQUEADA\n\nExistem " + mesasAbertas +
                    " mesa(s) aberta(s). Finalize as contas antes de fechar o caixa.");
                return false;
            }
            // Abre o modal de fechamento
            openModalClosing();
        }

        /**
         * 3. CONTROLE DE MODAIS
         */
        function openModalMovement(type) {
            const modal = document.getElementById('modalMovement');
            const title = document.getElementById('modalTitle');
            const typeInput = document.getElementById('movementType');
            const btnSubmit = document.getElementById('btnSubmit');

            if (modal) {
                typeInput.value = type;
                title.innerText = (type === 'sangria') ? '🔻 Sangria de Caixa' : '🔺 Reforço (Aporte)';
                btnSubmit.className = (type === 'sangria') ?
                    "flex-1 py-4 bg-red-600 text-white font-black rounded-2xl uppercase text-[10px] tracking-widest shadow-lg" :
                    "flex-1 py-4 bg-blue-600 text-white font-black rounded-2xl uppercase text-[10px] tracking-widest shadow-lg";

                modal.classList.remove('hidden');
            }
        }

        function openModalClosing() {
            const modal = document.getElementById('modalFecharCaixa');
            if (modal) {
                modal.classList.remove('hidden');
                setTimeout(() => {
                    const input = modal.querySelector('input[name="actual_balance"]');
                    if (input) input.focus();
                }, 200);
            }
        }

        function closeModal(id) {
            const modal = document.getElementById(id);
            if (modal) modal.classList.add('hidden');
        }

        /**
         * 4. 🚀 ENVIO COM AUTORIZAÇÃO (Onde estava o erro)
         */
        function enviarComAutorizacao(idFormulario) {
            const form = document.getElementById(idFormulario);
            if (!form) return;

            // IDs dos campos de senha visíveis em cada contexto
            const camposSenha = {
                'formCloseCash': 'password_direta_gestor',
                'formOpenCash': 'password_direta_abertura',
                'formMovement': 'password_direta_movimentacao' // ID que está no seu modal de sangria
            };

            const inputSenhaVisivel = document.getElementById(camposSenha[idFormulario]);

            // Prioridade 1: Senha digitada agora | Prioridade 2: Memória global
            const passFinal = (inputSenhaVisivel && inputSenhaVisivel.value) ?
                inputSenhaVisivel.value :
                window.supervisorMemoriaPass;

            // Puxa o email (seja do campo oculto ou do usuário logado)
            const emailFinal = form.querySelector('input[name="supervisor_email"]')?.value || window.supervisorMemoriaEmail;

            if (!passFinal || passFinal.trim() === "") {
                alert("⚠️ Autorização necessária: Digite a senha de GESTOR.");
                if (inputSenhaVisivel) inputSenhaVisivel.focus();
                return;
            }

            // Injeta os dados no formulário antes de enviar
            const mEmail = form.querySelector('input[name="supervisor_email"]');
            const mPass = form.querySelector('input[name="supervisor_password"]');

            if (mEmail && mPass) {
                mEmail.value = emailFinal;
                mPass.value = passFinal;
                form.submit();
            } else {
                alert("Erro: Campos de supervisor não encontrados no formulário.");
            }
        }

        // Exporta para o escopo global
        window.tentarEncerrarTurno = tentarEncerrarTurno;
        window.openModalMovement = openModalMovement;
        window.openModalClosing = openModalClosing;
        window.closeModal = closeModal;
        window.enviarComAutorizacao = enviarComAutorizacao;
    </script>
</x-bar-layout>
