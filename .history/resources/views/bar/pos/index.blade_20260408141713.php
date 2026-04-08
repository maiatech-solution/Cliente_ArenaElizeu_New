<x-bar-layout>

    <div class="max-w-[1600px] mx-auto px-6 sm:px-8 lg:px-10 py-6">

        <div class="flex flex-col md:flex-row gap-8 overflow-hidden">

            <div
                class="flex-1 flex flex-col min-h-0 bg-gray-900 rounded-[2.5rem] border border-gray-800 shadow-2xl overflow-hidden">

                <div class="p-8 border-b border-gray-800 bg-gray-900/50 space-y-6">
                    <div class="flex flex-col lg:flex-row gap-6 items-center">
                        <div class="flex items-center gap-4">
                            <a href="{{ route('bar.dashboard') }}"
                                class="p-3 bg-gray-800 hover:bg-gray-700 text-white rounded-2xl transition border border-gray-700 shadow-lg group"
                                title="Voltar ao Início">
                                <span
                                    class="group-hover:-translate-x-1 transition-transform duration-200 inline-block">◀</span>
                            </a>
                            <div>
                                <h2 class="text-xl font-black text-white uppercase tracking-tighter italic">Venda <span
                                        class="text-orange-500">Direta</span></h2>
                            </div>
                        </div>

                        <div class="flex-1 relative">
                            {{-- 1. BLOCO ISCA (Invisível): O navegador 'descarrega' os dados salvos aqui --}}
                            <div style="position: absolute; left: -9999px; top: -9999px;">
                                <input type="text" name="fake_search_user">
                                <input type="password" name="fake_search_pass">
                            </div>

                            {{-- 2. CAMPO DE BUSCA REAL --}}
                            <input type="text" id="mainSearch" onkeyup="liveSearch()" autocomplete="new-password"
                                {{-- Reforço para o navegador não sugerir nada --}} placeholder="🔍 Nome ou Código de Barras (Bipagem)..."
                                class="w-full bg-gray-950 border-gray-800 rounded-2xl text-white p-4 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-all">
                        </div>

                        <div class="md:w-64">
                            <select id="catFilter" onchange="liveSearch()"
                                class="w-full bg-gray-950 border-gray-800 rounded-2xl text-white p-4 focus:border-orange-500 outline-none uppercase font-black text-xs tracking-widest cursor-pointer">
                                <option value="all">📁 TODAS CATEGORIAS</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3 no-scrollbar"
                    id="productsGrid">
                    @foreach ($products as $product)
                        <button
                            onclick="addToCart({{ $product->id }}, '{{ $product->name }}', {{ $product->sale_price }}, {{ $product->stock_quantity }}, {{ $product->manage_stock }})"
                            data-category="{{ $product->bar_category_id }}" data-name="{{ strtolower($product->name) }}"
                            data-barcode="{{ $product->barcode }}"
                            class="product-card group relative p-3 bg-gray-800 hover:bg-orange-600 rounded-2xl border border-gray-700 hover:border-orange-500 transition-all flex flex-col items-center justify-center text-center active:scale-95 shadow-md h-28">

                            <div class="absolute top-1.5 right-1.5">
                                <span
                                    class="text-[7px] font-black px-1.5 py-0.5 rounded bg-gray-950/60 {{ $product->manage_stock && $product->stock_quantity <= $product->min_stock ? 'text-red-500 animate-pulse' : 'text-gray-400' }}">
                                    {{ $product->stock_quantity }}
                                </span>
                            </div>

                            <div class="text-xl mb-1 group-hover:scale-110 transition-transform">📦</div>

                            <div class="w-full">
                                <h3
                                    class="text-[9px] font-black text-white uppercase leading-tight line-clamp-2 mb-1 group-hover:text-white px-1">
                                    {{ $product->name }}
                                </h3>
                                <span class="text-orange-500 font-black text-xs group-hover:text-white">
                                    R$ {{ number_format($product->sale_price, 2, ',', '.') }}
                                </span>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>

            <div
                class="w-full md:w-96 flex flex-col bg-gray-900 rounded-3xl border border-gray-800 shadow-2xl overflow-hidden">
                <div class="p-6 border-b border-gray-800 flex items-center justify-between">
                    <h3 class="text-lg font-black text-white uppercase italic">🧾 Carrinho</h3>
                    <button onclick="clearCart()"
                        class="text-[10px] font-black text-red-500 hover:underline uppercase tracking-tighter">Limpar</button>
                </div>

                <div class="flex-1 overflow-y-auto p-6 space-y-3 no-scrollbar" id="cartItems">
                    <div id="emptyCart" class="text-center py-20">
                        <span class="text-5xl block mb-4 opacity-20">🛒</span>
                        <p class="text-gray-500 text-[10px] font-black uppercase tracking-widest">Aguardando Produtos
                        </p>
                    </div>
                </div>

                <div class="p-6 bg-gray-950 border-t border-gray-800 space-y-4">

                    {{-- 1. CAMPO DE DESCONTO COM TRAVA DE SEGURANÇA (INTEGRADO COM REQUISITARAUTORIZACAO) --}}
                    <div class="flex items-center gap-3 bg-gray-900 p-3 rounded-2xl border border-gray-800 group">
                        <div class="flex-1">
                            <label class="block text-[8px] font-black text-gray-500 uppercase tracking-widest mb-1">
                                Desconto Especial
                            </label>
                            <div class="flex items-center">
                                <span class="text-gray-600 font-black text-xs mr-1">R$</span>
                                {{-- O campo só nasce habilitado se o usuário for admin ou gestor --}}
                                <input type="number" id="cartDiscount" oninput="renderCart()" value="0.00"
                                    step="0.01"
                                    {{ in_array(auth()->user()->role, ['admin', 'gestor']) ? '' : 'disabled' }}
                                    class="w-full bg-transparent text-white font-black text-sm outline-none disabled:opacity-30 disabled:cursor-not-allowed"
                                    placeholder="0.00">
                            </div>
                        </div>

                        {{-- Botão de Cadeado: Só aparece se o usuário NÃO for gestor/admin --}}
                        @if (!in_array(auth()->user()->role, ['admin', 'gestor']))
                            <button type="button" id="btnAuthDiscountPDV"
                                onclick="requisitarAutorizacao(() => liberarCampoDescontoPDV())"
                                class="p-2 bg-gray-800 text-orange-500 rounded-xl hover:bg-orange-600 hover:text-white transition-all shadow-lg active:scale-90">
                                🔒
                            </button>
                        @endif
                    </div>

                    {{-- 2. Totais e Alertas --}}
                    <div class="space-y-1">
                        <div class="flex justify-between items-end">
                            <div class="flex flex-col">
                                <span class="text-gray-500 text-[10px] font-black uppercase italic"
                                    id="subtotalLabel">Total com Desconto</span>
                                <span class="text-3xl font-black text-white" id="cartTotalText">R$ 0,00</span>
                            </div>
                        </div>

                        <div id="paymentFeedback"
                            class="hidden flex justify-between items-center px-3 py-2 rounded-xl border font-black text-[10px] uppercase tracking-widest transition-colors">
                            <span id="feedbackLabel">Troco:</span>
                            <span id="feedbackValue">R$ 0,00</span>
                        </div>
                    </div>

                    {{-- 3. Lista de Pagamentos Registrados --}}
                    <div id="paymentsList" class="hidden space-y-2 py-2 border-t border-gray-900">
                        <p class="text-[8px] font-black text-gray-600 uppercase tracking-[0.2em]">Pagamentos
                            Confirmados:</p>
                        <div id="paymentsApplied" class="space-y-1"></div>
                    </div>

                    {{-- 4. Calculadora de Recebimento --}}
                    <div id="paymentCalculator"
                        class="hidden bg-gray-900 border border-gray-800 rounded-2xl p-4 animate-slide-in space-y-3">
                        <div>
                            <label id="inputLabel"
                                class="block text-[9px] font-black text-gray-500 uppercase mb-2">Valor Recebido</label>
                            <div class="relative">
                                <div style="position: absolute; left: -9999px; top: -9999px;">
                                    <input type="text" name="fake_payment_user">
                                    <input type="password" name="fake_payment_pass">
                                </div>
                                <span
                                    class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-black text-sm">R$</span>
                                <input type="number" id="amountReceived" name="amount_received_field" step="0.01"
                                    oninput="calculatePayment()" autocomplete="new-password"
                                    class="w-full bg-gray-950 border-gray-800 rounded-xl text-white p-3 pl-10 focus:border-orange-500 outline-none font-black text-lg">
                            </div>
                        </div>
                        <button id="addPartialBtn" onclick="addPayment()"
                            class="hidden w-full py-3 bg-orange-600 hover:bg-orange-500 text-white text-[10px] font-black uppercase rounded-xl transition-all shadow-lg shadow-orange-600/20 active:scale-95">
                            + Adicionar este Pagamento
                        </button>
                    </div>

                    {{-- 5. Métodos de Pagamento Padronizados (MAIATECH PDV) --}}
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (\App\Models\Bar\BarCashMovement::getPaymentMethods() as $key => $label)
                            {{-- Extraímos o emoji e o texto para manter o layout dos botões --}}
                            @php
                                $icon = '💰'; // Ícone padrão
                                if (str_contains($label, '📱')) {
                                    $icon = '📱';
                                }
                                if (str_contains($label, '💵')) {
                                    $icon = '💵';
                                }
                                if (str_contains($label, '💳')) {
                                    $icon = '💳';
                                }
                                if (str_contains($label, '🎟️')) {
                                    $icon = '🎟️';
                                }
                                if (str_contains($label, '🏦')) {
                                    $icon = '🏦';
                                }

                                // Removemos o emoji do label para não repetir no texto do botão
                                $cleanLabel = trim(str_replace($icon, '', $label));
                            @endphp

                            <button type="button" onclick="setPaymentMethod('{{ $key }}')"
                                class="pay-btn flex items-center gap-2 p-3 rounded-2xl border border-gray-800 bg-gray-900 text-gray-500 transition-all font-black text-[10px] uppercase hover:border-orange-500/50"
                                id="btn-{{ $key }}">
                                <span>{{ $icon }}</span>
                                {{ $cleanLabel }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Campo oculto que armazena a escolha para o formulário --}}
                    <input type="hidden" name="payment_method" id="selected_payment_method" required>

                    <button onclick="checkout()" id="finishBtn" disabled
                        class="w-full py-5 bg-gray-800 text-gray-500 font-black rounded-2xl uppercase tracking-[0.2em] text-xs transition-all cursor-not-allowed shadow-xl">
                        Finalizar Venda
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="receiptModal" onclick="if(event.target === this) closeReceipt()"
        class="hidden fixed inset-0 bg-black/95 backdrop-blur-md z-[200] flex items-center justify-center p-4 cursor-pointer">

        <div class="bg-white p-8 rounded-lg w-full max-w-[320px] shadow-2xl text-black font-mono text-sm cursor-default"
            id="receiptContent">

            <div class="text-center border-b border-dashed border-gray-400 pb-4 mb-4">
                <h2 class="text-xl font-black uppercase tracking-tighter">{{ config('app.name', 'BAR DO MAIA') }}</h2>
                <p class="text-[10px] opacity-70">CUPOM NÃO FISCAL</p>
                <p class="text-[10px]" id="receiptDate"></p>
            </div>

            {{-- Itens vendidos --}}
            <div id="receiptItems" class="space-y-2 mb-4 border-b border-dashed border-gray-400 pb-4 italic">
            </div>

            {{-- BLOCO FINANCEIRO DETALHADO --}}
            <div class="space-y-1 text-right mb-6 border-b border-dashed border-gray-400 pb-4">
                {{-- NOVO: Subtotal Bruto (Soma dos itens antes do desconto) --}}
                <div class="flex justify-between text-[11px] opacity-70" id="receiptSubtotalRow">
                    <span>SUBTOTAL:</span>
                    <span id="receiptSubtotalValue">R$ 0,00</span>
                </div>

                {{-- NOVO: Valor do Desconto (Só aparece se for > 0) --}}
                <div class="flex justify-between text-[11px] text-red-600 font-bold" id="receiptDiscountRow">
                    <span>DESCONTO:</span>
                    <span id="receiptDiscountValue">- R$ 0,00</span>
                </div>

                <div class="flex justify-between font-bold text-lg pt-1">
                    <span>TOTAL PAGO:</span>
                    <span id="receiptTotal"></span>
                </div>

                <div class="flex justify-between text-[11px] opacity-70 pt-2">
                    <span>RECEBIDO:</span>
                    <span id="receiptReceived">R$ 0,00</span>
                </div>

                <div class="flex justify-between text-[11px] font-bold">
                    <span>TROCO:</span>
                    <span id="receiptChange">R$ 0,00</span>
                </div>
            </div>

            <div class="mb-6">
                <p class="text-[10px] font-bold uppercase">PAGAMENTO: <span id="receiptPayment"></span></p>
            </div>

            <div class="flex flex-col gap-2 no-print">
                <button onclick="window.print()"
                    class="w-full py-3 bg-gray-900 text-white font-black rounded-xl uppercase text-[10px] tracking-widest active:scale-95 shadow-lg shadow-gray-900/20 transition-all">
                    🖨️ Imprimir
                </button>

                <button onclick="shareWhatsApp()"
                    class="w-full py-3 bg-green-600 text-white font-black rounded-xl uppercase text-[10px] tracking-widest active:scale-95 shadow-lg shadow-green-600/20 transition-all">
                    📱 WhatsApp
                </button>

                <button onclick="closeReceipt()"
                    class="w-full py-3 text-gray-400 font-black uppercase text-[10px] hover:text-black transition-colors border border-transparent hover:border-gray-200 rounded-xl">
                    Nova Venda
                </button>
            </div>

            <p class="text-center text-[8px] text-gray-400 mt-6 uppercase tracking-widest">Obrigado pela preferência!
            </p>
        </div>
    </div>


    <script>
        let cart = [];
        let payments = [];
        let paymentMethod = null;
        let currentCartTotal = 0;

        // --- 🛡️ FUNÇÕES DE AUTORIZAÇÃO INTEGRATADAS (PDV) ---

        // Libera o campo de desconto após a senha do gestor
        function liberarCampoDescontoPDV() {
            const input = document.getElementById('cartDiscount');
            const btn = document.getElementById('btnAuthDiscountPDV');
            if (input) {
                input.disabled = false;
                input.readOnly = false;
                input.classList.remove('opacity-50', 'cursor-not-allowed');
                input.classList.add('text-green-500');
                input.focus();
                input.select();
            }
            if (btn) {
                btn.innerHTML = '✅';
                btn.classList.replace('text-orange-500', 'text-green-500');
            }
        }

        // --- 🔄 LÓGICA DO CARRINHO ---

        function updateVisualStock() {
            const productCards = document.querySelectorAll('.product-card');
            productCards.forEach(card => {
                const onClickAttr = card.getAttribute('onclick');
                const match = onClickAttr.match(/addToCart\((\d+),\s*'.*?',\s*[\d.]+,\s*(\d+)/);

                if (match) {
                    const productId = parseInt(match[1]);
                    const originalStock = parseInt(match[2]);
                    const cartItem = cart.find(item => item.id === productId);
                    const qtyInCart = cartItem ? cartItem.quantity : 0;
                    const remainingStock = originalStock - qtyInCart;
                    const stockBadge = card.querySelector('span');

                    if (stockBadge) {
                        stockBadge.innerText = remainingStock;
                        if (remainingStock <= 0) {
                            stockBadge.classList.add('text-red-500', 'font-black');
                            card.style.opacity = '0.6';
                        } else {
                            stockBadge.classList.remove('text-red-500');
                            card.style.opacity = '1';
                        }
                    }
                }
            });
        }

        function addToCart(id, name, price, stock, manageStock) {
            const existingItem = cart.find(item => item.id === id);
            if (existingItem) {
                if (manageStock && existingItem.quantity >= stock) {
                    alert('🚫 Limite de estoque atingido!');
                    return;
                }
                existingItem.quantity += 1;
            } else {
                if (manageStock && stock <= 0) {
                    alert('🚫 Produto sem estoque disponível!');
                    return;
                }
                cart.push({
                    id,
                    name,
                    price,
                    quantity: 1,
                    stock,
                    manageStock
                });
            }
            renderCart();
        }

        function changeQty(index, delta) {
            const item = cart[index];
            if (delta > 0) {
                if (item.manageStock && item.quantity >= item.stock) {
                    alert('🚫 Limite de estoque atingido!');
                    return;
                }
                item.quantity += 1;
            } else if (delta < 0 && item.quantity > 1) {
                item.quantity -= 1;
            }
            renderCart();
        }

        function removeFromCart(index) {
            // 🛡️ Integrado com o seu requisitarAutorizacao das Mesas
            requisitarAutorizacao(() => {
                cart.splice(index, 1);
                renderCart();
            });
        }

        function clearCart() {
            // 🛡️ Integrado com o seu requisitarAutorizacao das Mesas
            requisitarAutorizacao(() => {
                resetSale();
            });
        }

        function renderCart() {
            const container = document.getElementById('cartItems');
            const totalText = document.getElementById('cartTotalText');

            // 💰 Captura o desconto para o cálculo
            const discountInput = document.getElementById('cartDiscount');
            const discountValue = parseFloat(discountInput.value) || 0;

            if (cart.length === 0) {
                container.innerHTML =
                    '<div id="emptyCart" class="text-center py-20"><span class="text-5xl block mb-4 opacity-20">🛒</span><p class="text-gray-500 text-[10px] font-black uppercase tracking-widest">Aguardando Produtos</p></div>';
                totalText.innerText = 'R$ 0,00';
                currentCartTotal = 0;
                resetSale();
                updateVisualStock();
                return;
            }

            container.innerHTML = cart.map((item, index) => `
            <div class="flex items-center justify-between bg-gray-800 p-3 rounded-2xl border border-gray-700 animate-slide-in">
                <div class="flex flex-col flex-1">
                    <span class="text-white text-[10px] font-black uppercase truncate w-32">${item.name}</span>
                    <span class="text-orange-500 text-xs font-bold">R$ ${(item.price * item.quantity).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                </div>
                <div class="flex items-center gap-3 bg-gray-950 px-3 py-2 rounded-xl border border-gray-800">
                    <button onclick="changeQty(${index}, -1)" class="text-orange-500 font-black">-</button>
                    <span class="text-white text-[11px] font-black w-6 text-center">x${item.quantity}</span>
                    <button onclick="changeQty(${index}, 1)" class="text-orange-500 font-black">+</button>
                </div>
                <button onclick="removeFromCart(${index})" class="ml-3 text-red-500 hover:text-white transition-colors">✕</button>
            </div>
        `).join('');

            // CÁLCULO FINAL: Subtotal - Desconto
            const subtotal = cart.reduce((acc, item) => acc + (item.price * item.quantity), 0);
            currentCartTotal = Math.max(0, subtotal - discountValue);

            totalText.innerText = `R$ ${currentCartTotal.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;

            calculatePayment();
            updateVisualStock();
        }

        function liveSearch() {
            const query = document.getElementById('mainSearch').value.toLowerCase();
            const catId = document.getElementById('catFilter').value;
            const cards = document.querySelectorAll('.product-card');
            cards.forEach(card => {
                const matchesSearch = card.dataset.name.includes(query) || card.dataset.barcode.includes(query);
                const matchesCat = catId === 'all' || card.dataset.category === catId;
                card.classList.toggle('hidden', !(matchesSearch && matchesCat));
            });
        }

        // --- 💰 PAGAMENTOS ---

        function setPaymentMethod(method) {
            paymentMethod = method;

            // Reset visual dos botões
            document.querySelectorAll('.pay-btn').forEach(btn => {
                btn.classList.remove('bg-orange-600', 'text-white', 'border-orange-500', 'shadow-lg');
                btn.classList.add('bg-gray-900', 'text-gray-500');
            });

            // Ativa o botão selecionado
            const activeBtn = document.getElementById('btn-' + method);
            activeBtn.classList.replace('bg-gray-900', 'bg-orange-600');
            activeBtn.classList.replace('text-gray-500', 'text-white');

            const totalPaidSoFar = payments.reduce((acc, p) => acc + p.value, 0);
            const remaining = currentCartTotal - totalPaidSoFar;

            const calcDiv = document.getElementById('paymentCalculator');
            calcDiv.classList.remove('hidden');
            document.getElementById('inputLabel').innerText = `VALOR (${method.toUpperCase()})`;

            const amountInput = document.getElementById('amountReceived');

            // --- LÓGICA DO VOUCHER ---
            if (method === 'voucher') {
                // Se for voucher, o valor recebido é 0, pois é cortesia
                amountInput.value = "0.00";
                // Opcional: Desabilita o input para não alterarem o valor do voucher
                amountInput.readOnly = true;
            } else {
                // Para outros métodos, sugere o valor restante
                amountInput.value = remaining > 0 ? remaining.toFixed(2) : "";
                amountInput.readOnly = false;
            }

            amountInput.focus();
            amountInput.select();

            calculatePayment();
        }

        function calculatePayment() {
            // Pegamos o valor digitado ou 0
            const currentInput = parseFloat(document.getElementById('amountReceived').value) || 0;
            const totalPaidSoFar = payments.reduce((acc, p) => acc + p.value, 0);

            // --- AJUSTE VOUCHER ---
            // Se for voucher, simulamos que o valor inserido é exatamente o que falta para fechar a conta
            let effectiveInput = currentInput;
            if (paymentMethod === 'voucher') {
                effectiveInput = currentCartTotal - totalPaidSoFar;
            }

            const totalWithInput = totalPaidSoFar + effectiveInput;
            const feedbackDiv = document.getElementById('paymentFeedback');
            const label = document.getElementById('feedbackLabel');
            const valueSpan = document.getElementById('feedbackValue');
            const addBtn = document.getElementById('addPartialBtn');

            const diff = totalWithInput - currentCartTotal;

            feedbackDiv.classList.remove('hidden', 'bg-green-900/20', 'text-green-500', 'bg-orange-900/20',
                'text-orange-500');

            // Lógica de exibição de Troco / Falta Pagar
            // Se for VOUCHER, não mostramos feedback de erro/pendência
            if (paymentMethod === 'voucher') {
                feedbackDiv.classList.add('hidden');
                addBtn.classList.add('hidden');
            } else if (diff > 0.005) {
                feedbackDiv.classList.add('bg-green-900/20', 'text-green-500');
                label.innerText = "Troco:";
                valueSpan.innerText = `R$ ${diff.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
                addBtn.classList.add('hidden');
            } else if (diff < -0.005) {
                feedbackDiv.classList.add('bg-orange-900/20', 'text-orange-500');
                label.innerText = "Falta Pagar:";
                valueSpan.innerText = `R$ ${Math.abs(diff).toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
                addBtn.classList.toggle('hidden', currentInput <= 0);
            } else {
                feedbackDiv.classList.add('hidden');
                addBtn.classList.add('hidden');
            }

            // Valida o checkout com o valor total (se for voucher, totalWithInput será igual ao currentCartTotal)
            validateCheckout(totalWithInput);
        }

        function addPayment() {
            const val = parseFloat(document.getElementById('amountReceived').value) || 0;
            if (val <= 0 || !paymentMethod) return;

            payments.push({
                method: paymentMethod,
                value: val
            });
            document.getElementById('paymentsList').classList.remove('hidden');
            renderPaymentsUI();

            paymentMethod = null;
            document.getElementById('amountReceived').value = "";
            document.getElementById('paymentCalculator').classList.add('hidden');
            document.querySelectorAll('.pay-btn').forEach(btn => {
                btn.classList.remove('bg-orange-600', 'text-white');
                btn.classList.add('bg-gray-900', 'text-gray-500');
            });

            calculatePayment();
        }

        function removePayment(i) {
            payments.splice(i, 1);
            if (payments.length === 0) document.getElementById('paymentsList').classList.add('hidden');
            renderPaymentsUI();
            calculatePayment();
        }

        function renderPaymentsUI() {
            const appliedDiv = document.getElementById('paymentsApplied');
            appliedDiv.innerHTML = payments.map((p, i) => `
            <div class="flex justify-between items-center bg-gray-800 p-2 rounded-xl border border-gray-700 animate-slide-in">
                <span class="text-[9px] text-white font-black uppercase">${p.method}</span>
                <span class="text-[10px] text-orange-500 font-black">R$ ${p.value.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                <button onclick="removePayment(${i})" class="text-red-500 ml-2">✕</button>
            </div>
        `).join('');
        }

        function validateCheckout(totalPaid) {
            const finishBtn = document.getElementById('finishBtn');

            // REGRA: É válido se tiver itens no carrinho E (o valor bater OU for um Voucher)
            const isVoucher = (paymentMethod === 'voucher');
            const isValid = cart.length > 0 && (isVoucher || totalPaid >= (currentCartTotal - 0.01));

            finishBtn.disabled = !isValid;

            if (isValid) {
                // Se for voucher, podemos até usar uma cor diferente (como laranja) ou manter o verde
                finishBtn.classList.replace('bg-gray-800', 'bg-green-600');
                finishBtn.classList.replace('text-gray-500', 'text-white');
                finishBtn.classList.add('cursor-pointer', 'shadow-lg', 'shadow-green-600/30');
            } else {
                finishBtn.classList.replace('bg-green-600', 'bg-gray-800');
                finishBtn.classList.replace('text-white', 'text-gray-500');
                finishBtn.classList.remove('cursor-pointer', 'shadow-lg');
            }
        }

        async function checkout() {
            const amountInputVal = parseFloat(document.getElementById('amountReceived').value) || 0;
            const totalPaid = payments.reduce((acc, p) => acc + p.value, 0) + amountInputVal;
            const discountVal = parseFloat(document.getElementById('cartDiscount').value) || 0;

            // --- 🎟️ AJUSTE MAIATECH: EXCEÇÃO PARA VOUCHER ---
            const isVoucher = (paymentMethod === 'voucher');

            // Agora a condição diz: "Se o carrinho estiver vazio OU (NÃO for voucher E o valor for insuficiente)"
            if (cart.length === 0 || (!isVoucher && totalPaid < (currentCartTotal - 0.01))) {
                alert("Valor insuficiente para finalizar.");
                return;
            }

            const btn = document.getElementById('finishBtn');
            btn.disabled = true;
            btn.innerText = 'PROCESSANDO...';

            let finalPayments = [...payments];

            // Se for Voucher, adicionamos ele com valor 0 para registrar a cortesia no banco
            if (isVoucher) {
                finalPayments.push({
                    method: 'voucher',
                    value: 0
                });
            } else if (amountInputVal > 0 && paymentMethod) {
                finalPayments.push({
                    method: paymentMethod,
                    value: amountInputVal
                });
            }

            try {
                const url = "{{ route('bar.pos.store') }}";
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        items: cart,
                        payments: finalPayments,
                        total_value: currentCartTotal,
                        discount_value: discountVal
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // No recibo, se for voucher, passamos o total do carrinho para o cupom sair bonito
                    showReceipt(isVoucher ? currentCartTotal : totalPaid);
                } else {
                    alert('❌ ERRO: ' + data.message);
                    btn.disabled = false;
                    btn.innerText = 'Finalizar Venda';
                }
            } catch (e) {
                alert('Erro na conexão. Verifique se o servidor está online.');
                btn.disabled = false;
                btn.innerText = 'Finalizar Venda';
            }
        }

        // --- 🧾 RECIBO E FINALIZAÇÃO ---

        function showReceipt(totalPaid) {
            const discountVal = parseFloat(document.getElementById('cartDiscount').value) || 0;
            const subtotalBruto = currentCartTotal + discountVal;

            // --- 🚨 AJUSTE PARA VOUCHER (CORTESIA) ---
            // Verificamos se o método principal ou algum dos pagamentos parciais é voucher
            const isVoucher = (paymentMethod === 'voucher' || payments.some(p => p.method === 'voucher'));

            // Se for voucher, o recebido e o total financeiro são ZERO
            const totalExibicao = isVoucher ? 0 : currentCartTotal;
            const recebidoExibicao = isVoucher ? 0 : totalPaid;
            const change = isVoucher ? 0 : (recebidoExibicao - totalExibicao);

            document.getElementById('receiptDate').innerText = new Date().toLocaleString('pt-BR');

            const subtotalElem = document.getElementById('receiptSubtotalValue');
            const discountElem = document.getElementById('receiptDiscountValue');
            const subtotalRow = document.getElementById('receiptSubtotalRow');
            const discountRow = document.getElementById('receiptDiscountRow');

            if (subtotalElem) subtotalElem.innerText =
                `R$ ${subtotalBruto.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
            if (discountElem) discountElem.innerText =
                `- R$ ${discountVal.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;

            if (subtotalRow) subtotalRow.style.display = discountVal > 0 ? 'flex' : 'none';
            if (discountRow) discountRow.style.display = discountVal > 0 ? 'flex' : 'none';

            // 4. Preenche os campos corrigidos
            // Se for Voucher, ele mostra R$ 0,00 e adicionamos um aviso de CORTESIA
            document.getElementById('receiptTotal').innerText = isVoucher ? 'R$ 0,00 (CORTESIA)' :
                `R$ ${totalExibicao.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
            document.getElementById('receiptReceived').innerText =
                `R$ ${recebidoExibicao.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
            document.getElementById('receiptChange').innerText =
                `R$ ${Math.max(0, change).toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;

            const methodsUsed = isVoucher ? '🎟️ VOUCHER / CORTESIA' : (payments.length > 0 ? payments.map(p => p.method)
                .join(' + ') : (paymentMethod || 'Dinheiro'));
            document.getElementById('receiptPayment').innerText = methodsUsed;

            document.getElementById('receiptItems').innerHTML = cart.map(item => `
        <div class="flex justify-between text-[11px]">
            <span class="flex-1">${item.quantity}x ${item.name}</span>
            <span class="ml-2">R$ ${(item.price * item.quantity).toFixed(2)}</span>
        </div>
    `).join('');

            document.getElementById('receiptModal').classList.remove('hidden');
        }

        function shareWhatsApp() {
            let phone = prompt("Número do cliente (DDD + Número):", "");
            if (phone) phone = phone.replace(/\D/g, '');

            // Captura os valores de desconto e totais
            const discountVal = parseFloat(document.getElementById('cartDiscount').value) || 0;
            const subtotalBruto = currentCartTotal + discountVal;

            let text = `*{{ config('app.name') }} - RECIBO*\n`;
            text += `_Data: ${new Date().toLocaleString('pt-BR')}_\n\n`;

            // Lista de Itens
            cart.forEach(item => {
                text += `• ${item.quantity}x ${item.name} = R$ ${(item.price * item.quantity).toFixed(2)}\n`;
            });

            text += `\n------------------------------\n`;

            // Se houver desconto, detalha o financeiro na mensagem
            if (discountVal > 0) {
                text += `*SUBTOTAL:* R$ ${subtotalBruto.toLocaleString('pt-BR', {minimumFractionDigits: 2})}\n`;
                text += `*DESCONTO:* - R$ ${discountVal.toLocaleString('pt-BR', {minimumFractionDigits: 2})}\n`;
            }

            text += `*TOTAL PAGO: ${document.getElementById('receiptTotal').innerText}*\n`;
            text += `*PAGO EM: ${document.getElementById('receiptPayment').innerText.toUpperCase()}*\n`;
            text += `------------------------------\n`;
            text += `_Obrigado pela preferência!_`;

            const waUrl = phone ? `https://api.whatsapp.com/send?phone=55${phone}` : `https://api.whatsapp.com/send`;
            window.open(`${waUrl}&text=${encodeURIComponent(text)}`, '_blank');
        }

        function closeReceipt() {
            document.getElementById('receiptModal').classList.add('hidden');
            window.location.reload();
        }

        function resetSale() {
            cart = [];
            payments = [];
            paymentMethod = null;

            // Reseta o estado do desconto
            const discInput = document.getElementById('cartDiscount');
            discInput.value = "0.00";
            discInput.readOnly = true;
            discInput.disabled = true;
            discInput.classList.add('opacity-50', 'cursor-not-allowed');

            const discBtn = document.getElementById('btnAuthDiscountPDV');
            if (discBtn) {
                discBtn.innerHTML = '🔒';
                discBtn.classList.replace('text-green-500', 'text-orange-500');
            }

            document.getElementById('paymentsList').classList.add('hidden');
            resetPaymentUI();
            renderCart();
        }

        function resetPaymentUI() {
            document.getElementById('paymentCalculator').classList.add('hidden');
            document.getElementById('paymentFeedback').classList.add('hidden');
            document.getElementById('amountReceived').value = "";
            document.querySelectorAll('.pay-btn').forEach(btn => {
                btn.classList.remove('bg-orange-600', 'text-white', 'border-orange-500');
                btn.classList.add('bg-gray-900', 'text-gray-500', 'border-gray-800');
            });
            const btn = document.getElementById('finishBtn');
            btn.innerText = 'Finalizar Venda';
            btn.disabled = true;
        }
    </script>

    <style>
        /* 1. Reset de Barras de Rolagem (Mantém o visual limpo) */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* 2. Animação de Entrada dos Itens no Carrinho */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .animate-slide-in {
            animation: slideIn 0.2s ease-out forwards;
        }

        /* 3. Configurações Específicas para Impressão (Cupom Térmico) */
        @media print {

            /* Esconde absolutamente tudo na página */
            body * {
                visibility: hidden;
                margin: 0;
                padding: 0;
            }

            /* Torna apenas o Modal e o Recibo visíveis */
            #receiptModal,
            #receiptContent,
            #receiptContent * {
                visibility: visible;
            }

            /* Força o recibo a ocupar o topo da página branca */
            #receiptModal {
                position: fixed;
                inset: 0;
                background: white !important;
                display: flex;
                align-items: start;
                justify-content: center;
                z-index: 9999;
                padding: 0;
                margin: 0;
            }

            /* Garante que o conteúdo do recibo não tenha sombras ou bordas na impressão */
            #receiptContent {
                box-shadow: none !important;
                border: none !important;
                width: 100% !important;
                max-width: 80mm;
                /* Padrão aproximado de impressoras térmicas */
                padding: 10px !important;
            }

            /* Esconde botões de ação (Imprimir, WhatsApp, Fechar) no papel */
            .no-print {
                display: none !important;
            }

            /* Remove cabeçalhos e rodapés automáticos do navegador */
            @page {
                margin: 0;
            }
        }
    </style>
</x-bar-layout>
