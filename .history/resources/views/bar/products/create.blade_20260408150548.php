<x-bar-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8" x-data="{ isCombo: {{ old('is_combo') ? 'true' : 'false' }}, comboItems: [] }">

        <div class="mb-8">
            <a href="{{ route('bar.products.index') }}"
                class="text-orange-500 hover:text-orange-400 text-sm font-bold flex items-center gap-2 mb-4 transition-colors">
                ⬅️ VOLTAR PARA LISTAGEM
            </a>
            <h2 class="text-3xl font-black text-white uppercase tracking-tighter">🆕 Cadastrar <span
                    class="text-orange-500">Novo Produto</span></h2>
        </div>

        <div class="bg-gray-900 rounded-3xl border border-gray-800 p-8 shadow-2xl">
            <form action="{{ route('bar.products.store') }}" method="POST">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- 1. DADOS BÁSICOS --}}
                    <div class="md:col-span-1">
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-2 tracking-widest">Nome do
                            Produto</label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                            class="w-full bg-gray-950 border-gray-800 rounded-xl text-white focus:border-orange-500 focus:ring-orange-500 p-3"
                            placeholder="Ex: Cerveja Heineken 600ml">
                        @error('name')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- 🚀 NOVO: TOGGLE DE COMBO --}}
                    <div class="md:col-span-1 flex items-end pb-1">
                        <div
                            class="w-full bg-gray-950 border border-gray-800 p-2.5 rounded-xl flex items-center justify-between">
                            <div class="flex flex-col">
                                <span
                                    class="text-[10px] font-black text-orange-500 uppercase tracking-widest ml-2">Produto
                                    tipo Combo?</span>
                                <span class="text-[8px] text-gray-500 ml-2 uppercase font-bold italic">Vende um grupo de
                                    itens</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="is_combo" value="1" class="sr-only peer"
                                    x-model="isCombo">
                                <div
                                    class="w-11 h-6 bg-gray-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-600">
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- 📦 ÁREA DA COMPOSIÇÃO DO COMBO --}}
                    <div class="md:col-span-2 bg-orange-600/5 border border-orange-600/20 p-6 rounded-2xl mb-4"
                        x-show="isCombo" x-transition>
                        <div class="flex items-center justify-between mb-4 border-b border-orange-600/10 pb-4">
                            <div>
                                <h3 class="text-orange-500 font-black text-xs uppercase italic tracking-widest">📋 Itens
                                    que compõem este combo</h3>
                                <p class="text-[9px] text-gray-500 uppercase font-bold">O estoque destes itens será
                                    baixado automaticamente na venda</p>
                            </div>
                            <button type="button" @click="comboItems.push({id: Date.now(), child_id: ''})"
                                class="bg-orange-600 hover:bg-orange-500 text-white text-[10px] font-black px-4 py-2 rounded-lg transition-all active:scale-95 shadow-lg shadow-orange-600/20">
                                + ADICIONAR ITEM
                            </button>
                        </div>

                        <div class="space-y-3">
                            <template x-for="(item, index) in comboItems" :key="item.id">
                                <div
                                    class="flex gap-3 items-center animate-in fade-in slide-in-from-top-1 bg-black/20 p-3 rounded-xl border border-gray-800">
                                    <div class="flex-1">
                                        <label
                                            class="block text-[9px] font-black text-gray-500 uppercase mb-1 ml-1">Buscar
                                            Produto</label>
                                        <input type="text"
                                            class="w-full bg-gray-950 border-gray-800 rounded-xl text-white text-xs p-2.5 focus:border-orange-500 outline-none"
                                            placeholder="Nome do produto..." list="products_list"
                                            @input="let selectedOption = Array.from($el.list.options).find(opt => opt.value === $el.value); item.child_id = selectedOption ? selectedOption.dataset.id : '';">
                                        <input type="hidden" :name="`combo_items[${index}][child_id]`"
                                            x-model="item.child_id">
                                    </div>
                                    <div class="w-24">
                                        <label
                                            class="block text-[9px] font-black text-gray-500 uppercase mb-1 text-center">Qtd</label>
                                        <input type="number" :name="`combo_items[${index}][quantity]`" value="1"
                                            min="1" required
                                            class="w-full bg-gray-950 border-gray-800 rounded-xl text-white text-xs p-2.5 text-center focus:border-orange-500">
                                    </div>
                                    <button type="button"
                                        @click="comboItems = comboItems.filter(i => i.id !== item.id)"
                                        class="text-red-500 hover:text-red-400 p-2 font-black mt-4">✕</button>
                                </div>
                            </template>
                            <datalist id="products_list">
                                @foreach ($availableProducts as $avail)
                                    <option data-id="{{ $avail->id }}" value="{{ $avail->name }}">
                                @endforeach
                            </datalist>
                        </div>
                    </div>

                    <div class="md:col-span-1">
                        <div class="flex justify-between items-center mb-2">
                            <label
                                class="block text-xs font-bold text-gray-400 uppercase tracking-widest">Categoria</label>
                            <button type="button" onclick="openCategoryModal()"
                                class="text-orange-500 hover:text-orange-400 text-[10px] font-black uppercase border border-orange-500/30 px-2 py-1 rounded-md transition-all">+
                                CADASTRAR NOVA</button>
                        </div>
                        <select name="bar_category_id" id="category_select" required
                            class="w-full bg-gray-950 border-gray-800 rounded-xl text-white focus:border-orange-500 p-3">
                            <option value="">Selecione uma categoria...</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}"
                                    {{ old('bar_category_id') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-2 tracking-widest">Código de
                            Barras</label>
                        <input type="text" name="barcode" value="{{ old('barcode') }}" maxlength="13"
                            class="w-full bg-gray-950 border-gray-800 rounded-xl text-white p-3 font-mono"
                            placeholder="Bipe agora">
                    </div>

                    {{-- GESTÃO DE INVENTÁRIO --}}
                    <div class="md:col-span-2 bg-gray-950/40 p-5 rounded-2xl border border-gray-800/50 my-2"
                        x-show="!isCombo">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div class="max-w-md">
                                <label
                                    class="block text-xs font-black text-orange-500 uppercase tracking-[0.2em] mb-1">⚙️
                                    Gestão de Inventário</label>
                                <p class="text-[10px] text-gray-500 font-bold uppercase leading-tight">Deseja que o
                                    sistema controle o saldo e emita alertas?</p>
                            </div>
                            <div class="flex bg-gray-900 p-1 rounded-xl border border-gray-800">
                                <label class="flex items-center cursor-pointer">
                                    <input type="radio" name="manage_stock" value="1" class="hidden peer"
                                        checked>
                                    <span
                                        class="px-4 py-2 text-[10px] font-black uppercase rounded-lg text-gray-500 peer-checked:bg-orange-600 peer-checked:text-white transition-all">✅
                                        Sim</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input type="radio" name="manage_stock" value="0" class="hidden peer"
                                        {{ old('manage_stock') === '0' ? 'checked' : '' }}>
                                    <span
                                        class="px-4 py-2 text-[10px] font-black uppercase rounded-lg text-gray-500 peer-checked:bg-red-600 peer-checked:text-white transition-all">🚫
                                        Não</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- UNIDADES --}}
                    <div x-show="!isCombo" class="grid grid-cols-1 md:grid-cols-2 gap-6 md:col-span-2">
                        <div>
                            <label
                                class="block text-xs font-bold text-orange-500 uppercase mb-2 tracking-widest">Unidade
                                de Compra</label>
                            <select name="unit_type" :required="!isCombo"
                                class="w-full bg-gray-950 border-gray-800 rounded-xl text-white p-3">
                                <option value="UN">UNIDADE (UN)</option>
                                <option value="FD">FARDO (FD)</option>
                                <option value="CX">CAIXA (CX)</option>
                                <option value="KG">QUILO (KG)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-orange-500 uppercase mb-2 tracking-widest">Qtd
                                no Fardo/Caixa</label>
                            <input type="number" name="content_quantity" value="{{ old('content_quantity', 1) }}"
                                min="1" :required="!isCombo"
                                class="w-full bg-gray-950 border-gray-800 rounded-xl text-white p-3">
                        </div>
                    </div>

                    {{-- 💰 FINANCEIRO COM INTELIGÊNCIA --}}
                    <div class="md:col-span-1">
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-2 tracking-widest">Preço de
                            Custo (R$)</label>
                        <input type="number" step="0.01" name="purchase_price" id="purchase_price"
                            value="{{ old('purchase_price') }}" required
                            class="w-full bg-gray-950 border-gray-800 rounded-xl text-white p-3 focus:border-orange-500"
                            placeholder="0.00">
                    </div>

                    <div class="md:col-span-1">
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest">Preço de
                                Venda (R$)</label>
                            <div class="flex gap-1">
                                <button type="button" onclick="aplicarMarkup(30)"
                                    class="text-[8px] bg-gray-800 text-gray-500 px-2 py-1 rounded-md hover:bg-orange-600 hover:text-white transition-all font-black">+30%</button>
                                <button type="button" onclick="aplicarMarkup(50)"
                                    class="text-[8px] bg-gray-800 text-gray-500 px-2 py-1 rounded-md hover:bg-orange-600 hover:text-white transition-all font-black">+50%</button>
                                <button type="button" onclick="aplicarMarkup(100)"
                                    class="text-[8px] bg-gray-800 text-gray-500 px-2 py-1 rounded-md hover:bg-orange-600 hover:text-white transition-all font-black">+100%</button>
                            </div>
                        </div>
                        <input type="number" step="0.01" name="sale_price" id="sale_price"
                            value="{{ old('sale_price') }}" required
                            class="w-full bg-gray-950 border-gray-800 rounded-xl text-orange-500 p-3 font-bold"
                            placeholder="0.00">
                    </div>

                    {{-- 📊 WIDGET ANALÍTICO EVOLUÍDO --}}
                    <!-- <div class="md:col-span-2 p-5 bg-black/40 border border-gray-800 rounded-[2rem] mt-2 shadow-inner">
                        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                            <div class="flex items-center gap-4">
                                <div id="margem_status_bg"
                                    class="w-14 h-14 rounded-2xl bg-gray-950 border border-gray-800 flex items-center justify-center transition-all duration-500">
                                    <span id="margem_emoji" class="text-2xl">💰</span>
                                </div>
                                 <div>
                                    <p
                                        class="text-[9px] font-black text-gray-500 uppercase tracking-[0.2em] mb-1 italic">
                                        Análise de Precificação</p>
                                    <div class="flex flex-wrap items-center gap-y-1 gap-x-4">
                                        <div class="flex flex-col">
                                            <span class="text-[8px] text-gray-600 uppercase font-bold">Markup (Sobre
                                                Custo)</span>
                                            <span id="display_markup"
                                                class="text-lg font-black text-orange-500 italic tracking-tighter leading-none">0%</span>
                                        </div>
                                        <span class="text-gray-800 text-xl font-thin">/</span>
                                        <div class="flex flex-col">
                                            <span class="text-[8px] text-gray-600 uppercase font-bold">Margem (Sobre
                                                Venda)</span>
                                            <span id="display_margem"
                                                class="text-lg font-black text-white italic tracking-tighter leading-none">0%</span>
                                        </div>
                                        <span class="text-gray-800 text-xl font-thin">/</span>
                                        <div class="flex flex-col">
                                            <span
                                                class="text-[8px] text-gray-600 uppercase font-bold leading-none">Lucro
                                                Líquido</span>
                                            <span id="display_lucro"
                                                class="text-xs font-bold text-gray-500 uppercase tracking-widest mt-1 italic">R$
                                                0,00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="w-full md:w-auto">
                                <label
                                    class="text-[8px] font-black text-gray-600 uppercase block mb-1 ml-2 tracking-widest">Markup
                                    Alvo (%)</label>
                                <input type="number" id="input_markup" placeholder="Personalizar"
                                    class="w-full md:w-32 bg-gray-950 border-gray-800 rounded-xl p-3 text-xs text-orange-500 font-black text-center outline-none focus:border-orange-500 transition-all">
                            </div>
                        </div>
                    </div> -->

                    {{-- ESTOQUE --}}
                    <div x-show="!isCombo" class="grid grid-cols-1 md:grid-cols-2 gap-6 md:col-span-2">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase mb-2 tracking-widest">Estoque
                                Inicial (UN)</label>
                            <input type="number" name="stock_quantity" value="{{ old('stock_quantity', 0) }}"
                                :required="!isCombo"
                                class="w-full bg-gray-950 border-gray-800 rounded-xl text-white p-3">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase mb-2 tracking-widest">Estoque
                                Mínimo (UN)</label>
                            <input type="number" name="min_stock" value="{{ old('min_stock', 5) }}"
                                :required="!isCombo"
                                class="w-full bg-gray-950 border-gray-800 rounded-xl text-white p-3">
                        </div>
                    </div>
                </div>

                <div class="mt-10 pt-6 border-t border-gray-800 flex justify-end">
                    <button type="submit"
                        class="px-10 py-4 bg-orange-600 hover:bg-orange-500 text-white font-black rounded-2xl transition-all shadow-xl shadow-orange-600/20 uppercase tracking-widest">
                        Gravar Produto
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL CATEGORIA --}}
    <div id="catModal"
        class="hidden fixed inset-0 bg-black/90 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-gray-900 border border-gray-800 p-6 rounded-3xl w-full max-w-sm shadow-2xl">
            <h3 class="text-white font-black uppercase text-sm mb-4">Cadastrar Categoria</h3>
            <input type="text" id="new_cat_name"
                class="w-full bg-gray-950 border-gray-800 rounded-xl text-white p-3 mb-6 focus:border-orange-500 outline-none"
                placeholder="Ex: Cervejas, Porções...">

            <div class="flex gap-3">
                <button type="button" onclick="closeCategoryModal()"
                    class="flex-1 py-2 text-gray-500 font-bold text-xs uppercase">Cancelar</button>
                <button type="button" onclick="saveCategory()"
                    class="flex-1 py-3 bg-orange-600 text-white rounded-xl font-black text-xs uppercase">Salvar</button>
            </div>
        </div>
    </div>

    <script>
        // --- Lógica de Categorias ---
        function openCategoryModal() {
            document.getElementById('catModal').classList.remove('hidden');
            document.getElementById('new_cat_name').focus();
        }

        function closeCategoryModal() {
            document.getElementById('catModal').classList.add('hidden');
            document.getElementById('new_cat_name').value = '';
        }

        async function saveCategory() {
            const nameInput = document.getElementById('new_cat_name');
            const name = nameInput.value;
            if (!name) return alert('Digite o nome da categoria!');

            try {
                const response = await fetch("{{ route('bar.categories.store_ajax') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        name: name
                    })
                });

                if (!response.ok) throw new Error('Erro ao salvar');
                const data = await response.json();
                const select = document.getElementById('category_select');
                const option = new Option(data.name, data.id, true, true);
                select.add(option);
                closeCategoryModal();
            } catch (error) {
                alert('Erro: Categoria já existe ou falha na conexão.');
            }
        }

        // --- Lógica de Precificação Inteligente (Markup vs Margem) ---
        const inputCusto = document.getElementById('purchase_price');
        const inputVenda = document.getElementById('sale_price');
        const inputMarkup = document.getElementById('input_markup');

        function aplicarMarkup(porcentagem) {
            const custo = parseFloat(inputCusto.value) || 0;
            if (custo > 0) {
                // Define o preço de venda baseado no acréscimo (Markup) sobre o custo
                const novoPreco = custo * (1 + (porcentagem / 100));
                inputVenda.value = novoPreco.toFixed(2);
                atualizarResumo();
            } else {
                alert('⚠️ Insira o preço de custo primeiro!');
                inputCusto.focus();
            }
        }

        function atualizarResumo() {
            const custo = parseFloat(inputCusto.value) || 0;
            const venda = parseFloat(inputVenda.value) || 0;

            let lucro = venda - custo;

            // 1. Cálculo de MARKUP (Acréscimo sobre o custo)
            let markup = custo > 0 ? (lucro / custo) * 100 : 0;

            // 2. Cálculo de MARGEM (Porcentagem real que sobra da venda)
            let margem = venda > 0 ? (lucro / venda) * 100 : 0;

            // Atualiza os labels do Widget
            document.getElementById('display_markup').innerText = markup.toFixed(1) + '%';
            document.getElementById('display_margem').innerText = margem.toFixed(1) + '%';
            document.getElementById('display_lucro').innerText = 'R$ ' + lucro.toLocaleString('pt-BR', {
                minimumFractionDigits: 2
            });

            const bgStatus = document.getElementById('margem_status_bg');
            const emoji = document.getElementById('margem_emoji');

            // Lógica visual baseada na MARGEM REAL (Saúde do negócio)
            if (venda === 0) {
                bgStatus.className =
                    'w-14 h-14 rounded-2xl bg-gray-950 border border-gray-800 flex items-center justify-center';
                emoji.innerText = '💰';
            } else if (margem <= 0) {
                bgStatus.className =
                    'w-14 h-14 rounded-2xl bg-red-600/10 border border-red-500/40 flex items-center justify-center shadow-[0_0_20px_rgba(239,68,68,0.1)]';
                emoji.innerText = '⚠️';
            } else if (margem < 25) { // Alerta se a margem real for baixa
                bgStatus.className =
                    'w-14 h-14 rounded-2xl bg-yellow-500/10 border border-yellow-500/40 flex items-center justify-center';
                emoji.innerText = '🧐';
            } else {
                bgStatus.className =
                    'w-14 h-14 rounded-2xl bg-green-500/10 border border-green-500/40 flex items-center justify-center shadow-[0_0_20px_rgba(34,197,94,0.1)]';
                emoji.innerText = '✅';
            }
        }

        // Event Listeners para cálculos automáticos ao digitar
        inputCusto.addEventListener('input', atualizarResumo);
        inputVenda.addEventListener('input', atualizarResumo);

        // Calcula quando o usuário digita uma porcentagem personalizada
        inputMarkup.addEventListener('input', function() {
            if (this.value) aplicarMarkup(parseFloat(this.value));
        });

        // Garante o cálculo inicial ao carregar a página
        window.addEventListener('load', atualizarResumo);
    </script>
</x-bar-layout>
