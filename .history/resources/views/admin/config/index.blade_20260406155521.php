<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Configuração de Horários:') }} <span class="text-indigo-600">{{ $currentArena->name }}</span>
            </h2>
            {{-- Botão Voltar --}}
            <a href="{{ route('admin.arenas.index') }}"
                class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                {{ __('Voltar para Quadras') }}
            </a>
        </div>
    </x-slot>

    <style>
        /* Estilos CSS mantidos para o formulário de recorrência */
        .price-input-config {
            width: 100%;
            padding: 4px;
            border-radius: 4px;
            border: 1px solid #d1d5db;
        }

        .time-input {
            width: 100%;
        }

        .slot-container {
            border: 1px solid #e5e7eb;
            /* Gray 200 */
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 0.75rem;
            background-color: #fafafa;
            /* Gray 50 */
        }

        /* Estilo para o modal de confirmação */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-overlay.hidden {
            display: none !important;
        }
    </style>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Notificações --}}
            @if (session('success'))
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded" role="alert">
                    <p>{{ session('success') }}</p>
                </div>
            @endif
            @if (session('warning'))
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4 rounded" role="alert">
                    <p>{{ session('warning') }}</p>
                </div>
            @endif
            @if (session('error'))
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded" role="alert">
                    <p>{{ session('error') }}</p>
                </div>
            @endif
            @if ($errors->any())
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded" role="alert">
                    <p>Houve um erro na validação dos dados. Por favor, verifique o formulário de Configuração Semanal
                        abaixo.</p>
                </div>
            @endif

            {{-- 🏟️ SELETOR DE ARENAS: Recarrega a página filtrando pela quadra selecionada --}}
            <div class="mb-8 p-4 bg-indigo-50 border border-indigo-200 rounded-lg shadow-sm">
                <label for="arena_select" class="block text-sm font-bold text-indigo-900 mb-2">
                    📍 Arena sendo configurada agora:
                </label>
                <div class="flex items-center gap-4">
                    <select id="arena_select" onchange="window.location.href='/admin/config/' + this.value"
                        class="block w-full md:w-1/3 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-gray-700 font-medium">
                        @foreach ($arenas as $arena)
                            <option value="{{ $arena->id }}"
                                {{ isset($currentArena) && $currentArena->id == $arena->id ? 'selected' : '' }}>
                                {{ $arena->name }}
                </div>
                @endforeach
                </select>

                <span class="text-xs text-indigo-600 font-medium hidden md:block">
                    ← Troque aqui para mudar de quadra
                </span>
            </div>
        </div>

        {{-- Formulário de Configuração Semanal (MÚLTIPLOS SLOTS) --}}
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg mb-8">
            <div class="p-6 bg-white border-b border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    1. Definição de Horários de Funcionamento Recorrente
                </h3>

                {{-- ✅ NOVO: MENSAGEM DE PROCESSO AUTOMÁTICO (Mantida a descrição de 1 ano para evitar mexer no front) --}}
                <div
                    class="mt-4 p-4 bg-blue-100 border border-blue-400 rounded-lg dark:bg-blue-900 dark:border-blue-700 mb-6">
                    <p class="text-sm font-semibold text-blue-800 dark:text-blue-200">
                        ✅ Processo Automático: As reservas fixas (slots disponíveis) são agora **geradas
                        automaticamente** para os próximos 6 meses, logo após você clicar em "Salvar Configuração
                        Semanal".
                    </p>
                    <p class="text-xs text-blue-700 dark:text-blue-300 mt-2">
                        *Para marcar um dia específico como Manutenção, use a tela "Todas as Reservas".
                    </p>
                </div>


                <form id="config-form" action="{{ route('admin.config.store') }}" method="POST">
                    @csrf
                    {{-- 🛑 CAMPO HIDDEN INSERIDO PARA FORÇAR 6 MESES --}}
                    <input type="hidden" name="arena_id" value="{{ $currentArena->id }}">
                    <div class="space-y-6">
                        @php
                            // Esta variável precisa ser passada pelo seu ConfigurationController@index
                            $dayConfigurations = $dayConfigurations ?? [];
                            $dayNames = [
                                0 => 'Domingo',
                                1 => 'Segunda-feira',
                                2 => 'Terça-feira',
                                3 => 'Quarta-feira',
                                4 => 'Quinta-feira',
                                5 => 'Sexta-feira',
                                6 => 'Sábado',
                            ];
                        @endphp

                        @foreach ($dayNames as $dayOfWeek => $dayName)
                            @php
                                // Acessa a configuração de slots (que contém config_data)
                                $configModel = \App\Models\ArenaConfiguration::where('day_of_week', $dayOfWeek)
                                    ->where('arena_id', $currentArena->id)
                                    ->first();
                                $isDayActive = $configModel ? $configModel->is_active : false;

                                // Pega os slots, se existirem. Verifica se já é array (casting) ou se é string (JSON).
                                $slots = [];
                                if ($configModel && $configModel->config_data) {
                                    $slotsData = $configModel->config_data;
                                    if (is_string($slotsData)) {
                                        $slots = json_decode($slotsData, true);
                                    } elseif (is_array($slotsData)) {
                                        $slots = $slotsData;
                                    }
                                }

                                // Se não houver slots válidos ou carregados, insere um placeholder para o formulário
                                if (empty($slots) || !is_array($slots)) {
                                    // Placeholder para o formulário
                                    $slots = [
                                        [
                                            'start_time' => '06:00',
                                            'end_time' => '23:00',
                                            'default_price' => 100.0,
                                            'is_active' => $isDayActive,
                                        ],
                                    ];
                                }
                            @endphp

                            <div class="p-4 bg-gray-100 dark:bg-gray-700 rounded-lg shadow-inner">
                                <div class="flex items-center space-x-4 mb-4 border-b pb-2 justify-between">

                                    {{-- Título e Checkbox Mestre --}}
                                    <div class="flex items-center space-x-4">
                                        <input type="checkbox" name="day_status[{{ $dayOfWeek }}]"
                                            id="day-active-{{ $dayOfWeek }}" value="1"
                                            {{ $isDayActive ? 'checked' : '' }}
                                            class="h-5 w-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 day-toggle-master">
                                        <label for="day-active-{{ $dayOfWeek }}"
                                            class="text-lg font-bold text-gray-900 dark:text-white">
                                            {{ $dayName }}
                                        </label>
                                    </div>

                                    {{-- Botão de Exclusão de Dia Inteiro (Mantido para exclusão em massa de recorrência) --}}
                                    @if ($isDayActive)
                                        <button type="button"
                                            onclick="deleteDayConfig({{ $dayOfWeek }}, '{{ $dayName }}')"
                                            class="px-3 py-1 bg-red-600 text-white font-semibold rounded-lg shadow-md hover:bg-red-700 transition duration-150 text-xs flex items-center space-x-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            <span>Excluir Dia Recorrente</span>
                                        </button>
                                    @endif

                                </div>

                                {{-- Container para as faixas de preço --}}
                                <div id="slots-container-{{ $dayOfWeek }}" class="slots-container mt-2"
                                    style="{{ !$isDayActive ? 'display: none;' : '' }}">

                                    @foreach ($slots as $index => $slot)
                                        {{-- Renderiza o Slot Salvo ou o Slot de Placeholder --}}
                                        <div class="slot-item slot-container flex items-center space-x-4 p-3 bg-white dark:bg-gray-600"
                                            data-day="{{ $dayOfWeek }}" data-index="{{ $index }}"
                                            data-start-time="{{ \Carbon\Carbon::parse($slot['start_time'])->format('H:i:s') }}"
                                            data-end-time="{{ \Carbon\Carbon::parse($slot['end_time'])->format('H:i:s') }}">

                                            <input type="hidden"
                                                name="configs[{{ $dayOfWeek }}][{{ $index }}][day_of_week]"
                                                value="{{ $dayOfWeek }}">

                                            {{-- Checkbox de Slot Ativo --}}
                                            <div class="flex items-center">
                                                <input type="checkbox"
                                                    name="configs[{{ $dayOfWeek }}][{{ $index }}][is_active]"
                                                    id="slot-active-{{ $dayOfWeek }}-{{ $index }}"
                                                    value="1"
                                                    {{ isset($slot['is_active']) && $slot['is_active'] ? 'checked' : '' }}
                                                    class="h-4 w-4 text-green-600 border-gray-300 rounded focus:ring-green-500 slot-active-checkbox"
                                                    {{ !$isDayActive ? 'disabled' : '' }}>
                                                <label for="slot-active-{{ $dayOfWeek }}-{{ $index }}"
                                                    class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    Ativo
                                                </label>
                                            </div>

                                            {{-- Horário de Início --}}
                                            <div class="w-1/4">
                                                <label
                                                    class="block text-xs font-medium text-gray-500 dark:text-gray-400">Início</label>
                                                <input type="time"
                                                    name="configs[{{ $dayOfWeek }}][{{ $index }}][start_time]"
                                                    {{-- Garantimos o formato H:i pegando apenas os 5 primeiros caracteres (HH:mm) --}}
                                                    value="{{ old("configs.$dayOfWeek.$index.start_time", isset($slot['start_time']) ? substr($slot['start_time'], 0, 5) : '08:00') }}"
                                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-500 dark:text-white time-input"
                                                    {{ !$isDayActive ? 'disabled' : '' }}>

                                                @error("configs.$dayOfWeek.$index.start_time")
                                                    <p class="text-xs text-red-500">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            {{-- Horário de Fim --}}
                                            <div class="w-1/4">
                                                <label
                                                    class="block text-xs font-medium text-gray-500 dark:text-gray-400">Fim</label>
                                                <input type="time"
                                                    name="configs[{{ $dayOfWeek }}][{{ $index }}][end_time]"
                                                    {{-- Aplicamos a mesma trava de 5 caracteres para o fim --}}
                                                    value="{{ old("configs.$dayOfWeek.$index.end_time", isset($slot['end_time']) ? substr($slot['end_time'], 0, 5) : '18:00') }}"
                                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-500 dark:text-white time-input"
                                                    {{ !$isDayActive ? 'disabled' : '' }}>

                                                @error("configs.$dayOfWeek.$index.end_time")
                                                    <p class="text-xs text-red-500">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            {{-- Preço Padrão --}}
                                            <div class="w-1/4">
                                                <label
                                                    class="block text-xs font-medium text-gray-500 dark:text-gray-400">Preço
                                                    (R$)
                                                </label>
                                                <input type="number" step="0.01"
                                                    name="configs[{{ $dayOfWeek }}][{{ $index }}][default_price]"
                                                    value="{{ old("configs.$dayOfWeek.$index.default_price", $slot['default_price']) }}"
                                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-500 dark:text-white price-input-config"
                                                    {{ !$isDayActive ? 'disabled' : '' }}>
                                                @error("configs.$dayOfWeek.$index.default_price")
                                                    <p class="text-xs text-red-500">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            {{-- Botão de Remover Slot (Ação local no form, não AJAX) --}}
                                            <div class="w-1/12 flex items-center justify-end space-x-2">
                                                <button type="button" onclick="removeSlotFormRow(this)"
                                                    class="text-red-600 hover:text-red-900"
                                                    title="Remover Faixa de Horário do Formulário"
                                                    {{ !$isDayActive ? 'disabled' : '' }}>
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6"
                                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach

                                </div>

                                {{-- Botão Adicionar Faixa --}}
                                <div class="mt-3">
                                    <button type="button"
                                        class="inline-flex items-center px-3 py-1 bg-gray-200 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300 add-slot-btn"
                                        data-day="{{ $dayOfWeek }}" {{ !$isDayActive ? 'disabled' : '' }}>
                                        + Adicionar Faixa de Horário
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- ✅ ÚNICO BOTÃO DE SUBMISSÃO --}}
                    <div class="flex justify-start mt-8">
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150">
                            Salvar Configuração Semanal e Gerar Slots
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- NOVO: BOTÃO DE REDIRECIONAMENTO PARA GERENCIAMENTO DE SLOTS (Topo) - Estilo DISCRETO --}}
        <div class="mb-8 flex justify-end">
            <a href="{{ route('admin.reservas.todas') }}"
                class="inline-flex items-center px-4 py-2 bg-gray-200 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300 active:bg-gray-400 focus:outline-none focus:border-gray-400 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                    </path>
                </svg>
                {{ __('Ir para Todas as Reservas') }}
            </a>
        </div>
    </div>
    </div>


    {{-- 🆕 MODAL DE CONFIRMAÇÃO DE EXCLUSÃO (MANTIDO APENAS PARA EXCLUSÃO DE DIAS RECORRENTES) --}}
    <div id="delete-config-modal" class="modal-overlay hidden" onclick="closeDeleteConfigModal()">
        <div class="bg-white p-6 rounded-xl shadow-2xl max-w-lg w-full transition-all duration-300 transform scale-100"
            onclick="event.stopPropagation()">
            <h3 class="text-xl font-bold text-red-700 mb-4 border-b pb-2">Confirmação de Exclusão de Recorrência</h3>

            <p id="delete-config-message" class="text-gray-700 mb-4 font-medium"></p>

            {{-- ✅ CAMPO: Justificativa (Obrigatório) --}}
            <div id="justification-section" class="mb-6">
                <label for="config-justification-input" class="block text-sm font-medium text-gray-700 mb-2">
                    Justificativa da Ação (Obrigatória):
                </label>
                <textarea id="config-justification-input" rows="3"
                    class="w-full p-2 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                    placeholder="Motivo pelo qual a faixa/dia será excluído (mínimo 5 caracteres)..."></textarea>
                <p id="justification-error" class="text-xs text-red-500 mt-1 hidden">Por favor, insira uma
                    justificativa válida (mínimo 5 caracteres).</p>
            </div>

            {{-- Alerta de Conflito de Clientes --}}
            <p id="delete-config-conflict-warning"
                class="text-base text-red-600 font-semibold mb-6 p-3 bg-red-100 border border-red-300 rounded hidden">
                ⚠️ <span id="conflict-count">0</span> reserva(s) de cliente futuras serão CANCELADAS e DELETADAS.
            </p>

            <div class="flex justify-end space-x-3">
                <button onclick="closeDeleteConfigModal()" type="button"
                    class="px-4 py-2 bg-gray-200 text-gray-800 font-semibold rounded-lg hover:bg-gray-300 transition duration-150">
                    Cancelar
                </button>
                <button id="confirm-delete-config-btn" type="button"
                    class="px-4 py-2 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700 transition duration-150">
                    Continuar
                </button>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]') ? document.querySelector(
            'meta[name="csrf-token"]').getAttribute('content') : document.querySelector('input[name="_token"]').value;

        // ✅ ROTAS MANTIDAS
        //const DELETE_DAY_CONFIG_URL = '{{ route('admin.config.delete_day_config') }}';
        // ===================================

        // Variáveis de estado para o modal de exclusão (Apenas para exclusão de dia inteiro)
        let pendingDeleteAction = {
            type: null,
            dayOfWeek: null,
            isConfirmed: false,
            justification: null,
        };

        // Contadores para garantir índices únicos ao adicionar novos slots
        const nextIndex = {};

        // Inicializa contadores de índice de 0 a 6 (Domingo a Sábado) para robustez no JS
        for (let i = 0; i <= 6; i++) {
            nextIndex[i] = document.querySelectorAll(`#slots-container-${i} .slot-item`).length;
        }


        function updateRemoveButtonState(dayOfWeek) {
            // Lógica removida, pois a desabilitação não é mais necessária (botão é apenas para exclusão do formulário)
        }

        /**
         * Habilita/Desabilita inputs e botões de um determinado dia.
         * @param {number} dayOfWeek
         * @param {boolean} isDisabled
         */
        function updateSlotInputsState(dayOfWeek, isDisabled) {
            const container = document.getElementById(`slots-container-${dayOfWeek}`);

            // Verifica se o container existe antes de tentar buscar os inputs
            if (!container) return;

            // Inputs de tempo, preço e checkboxes de slot ativo
            const inputs = container.querySelectorAll('input[type="time"], input[type="number"], .slot-active-checkbox');

            // Botões de adicionar (localizado fora do container de slots) e remover (localizado dentro dos slots)
            const addBtn = document.querySelector(`.add-slot-btn[data-day="${dayOfWeek}"]`);
            const deleteBtns = container.querySelectorAll('.slot-item button');

            inputs.forEach(input => {
                input.disabled = isDisabled;
            });

            // Desabilita/habilita botões de remover/adicionar
            deleteBtns.forEach(btn => {
                btn.disabled = isDisabled;
            });

            if (addBtn) addBtn.disabled = isDisabled;
        }

        // --- LÓGICA DE GERENCIAMENTO DE SLOTS (JS) ---

        // 1. Alternância do Dia Mestre
        function attachMasterToggleListener(checkbox) {
            checkbox.addEventListener('change', function() {
                const day = this.id.replace('day-active-', '');
                const isDisabled = !this.checked;
                const container = document.getElementById(`slots-container-${day}`);

                if (!isDisabled) {
                    container.style.display = 'block';
                    // Garante que o checkbox do primeiro slot fica ativo quando o mestre é ativado
                    const firstSlotCheckbox = container.querySelector('.slot-active-checkbox');
                    if (firstSlotCheckbox) {
                        firstSlotCheckbox.checked = true;
                    }
                } else {
                    container.style.display = 'none';
                    // Desativa todos os slots
                    container.querySelectorAll('.slot-active-checkbox').forEach(cb => cb.checked = false);
                }

                // Habilita/desabilita os inputs e o botão de adicionar faixa
                updateSlotInputsState(day, isDisabled);
                updateRemoveButtonState(day);
            });
        }

        // 2. Adicionar Slot
        function attachAddSlotListener(button) {
            button.addEventListener('click', function() {
                const dayOfWeek = this.dataset.day;
                const container = document.getElementById(`slots-container-${dayOfWeek}`);
                const index = nextIndex[dayOfWeek]++; // Incrementa após usar

                // Cópia do HTML de um slot de placeholder - Removido segundos dos atributos data e valores
                const newSlotHtml = `
            <div class="slot-item slot-container flex items-center space-x-4 p-3 bg-white dark:bg-gray-600"
                    data-day="${dayOfWeek}"
                    data-index="${index}"
                    data-start-time="08:00"
                    data-end-time="12:00">
                <input type="hidden" name="configs[${dayOfWeek}][${index}][day_of_week]" value="${dayOfWeek}">

                <div class="flex items-center">
                    <input type="checkbox" name="configs[${dayOfWeek}][${index}][is_active]"
                                id="slot-active-${dayOfWeek}-${index}" value="1" checked
                                class="h-4 w-4 text-green-600 border-gray-300 rounded focus:ring-green-500 slot-active-checkbox">
                    <label for="slot-active-${dayOfWeek}-${index}" class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Ativo
                    </label>
                </div>

                <div class="w-1/4">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Início</label>
                    <input type="time" name="configs[${dayOfWeek}][${index}][start_time]" value="08:00"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-500 dark:text-white time-input">
                </div>

                <div class="w-1/4">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Fim</label>
                    <input type="time" name="configs[${dayOfWeek}][${index}][end_time]" value="12:00"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-500 dark:text-white time-input">
                </div>

                <div class="w-1/4">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Preço (R$)</label>
                    <input type="number" step="0.01" name="configs[${dayOfWeek}][${index}][default_price]" value="120.00"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-500 dark:text-white price-input-config">
                </div>

                <div class="w-1/12 flex items-center justify-end space-x-2">
                    <button type="button"
                                onclick="removeSlotFormRow(this)"
                                class="text-red-600 hover:text-red-900"
                                title="Remover Faixa de Horário do Formulário">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </button>
                </div>
            </div>
        `;

                container.insertAdjacentHTML('beforeend', newSlotHtml);
                updateRemoveButtonState(dayOfWeek);
            });
        }

        // 3. Remover Slot do Formulário (Ação local, sem AJAX)
        function removeSlotFormRow(buttonElement) {
            const slotItem = buttonElement.closest('.slot-item');
            if (slotItem) {
                // 🛑 NOVO: Antes de remover, pede confirmação simples (para evitar cliques acidentais)
                // Usando alert() aqui temporariamente pois confirm() é desabilitado no ambiente.
                if (window.confirm(
                        'Tem certeza que deseja remover esta faixa de horário do formulário? (Isto não cancela reservas futuras já criadas)'
                    )) {
                    const dayOfWeek = slotItem.dataset.day;
                    slotItem.remove();
                    updateRemoveButtonState(dayOfWeek);
                }
            }
        }


        // --- LÓGICA DO MODAL DE CONFIRMAÇÃO DE EXCLUSÃO (MANTIDA PARA EXCLUSÃO EM MASSA) ---

        /**
         * Abre o modal de exclusão e configura a mensagem e alerta de conflito.
         */
        function openDeleteConfigModal(message, conflictCount) {
            document.getElementById('delete-config-message').innerHTML = message;

            // Reinicializa o campo de justificativa
            document.getElementById('config-justification-input').value = '';
            document.getElementById('justification-error').classList.add('hidden');

            const conflictWarning = document.getElementById('delete-config-conflict-warning');
            const conflictCountSpan = document.getElementById('conflict-count');

            if (conflictCount > 0) {
                conflictCountSpan.textContent = conflictCount;
                conflictWarning.classList.remove('hidden');
            } else {
                conflictWarning.classList.add('hidden');
            }

            document.getElementById('delete-config-modal').classList.remove('hidden');
            document.getElementById('delete-config-modal').classList.add('flex');
            document.getElementById('confirm-delete-config-btn').textContent = 'Continuar'; // Botão padrão
        }

        /**
         * Fecha o modal de exclusão e reseta o estado de confirmação.
         */
        function closeDeleteConfigModal() {
            document.getElementById('delete-config-modal').classList.remove('flex');
            document.getElementById('delete-config-modal').classList.add('hidden');
            pendingDeleteAction.isConfirmed = false; // Reseta a confirmação
            pendingDeleteAction.justification = null; // ✅ Reseta a justificativa
        }

        /**
         * Função para realizar a chamada AJAX de exclusão (Apenas dia).
         */
        async function executeDeleteAction(isConfirmed) {
            const {
                type,
                configId, // 🎯 Mudamos de dayOfWeek para configId como definimos na função anterior
                justification
            } = pendingDeleteAction;

            let url = '';
            let payload = {
                confirm_cancel: isConfirmed ? 1 : 0,
                justificativa_gestor: justification,
                _token: csrfToken
            };

            if (type === 'day') {
                // 🎯 Aqui montamos a URL final com o ID. Ex: /admin/config/delete-day-config/15
                url = '{{ route('admin.config.delete_day_config', '') }}/' + configId;
            } else {
                window.alert('Erro: Ação de exclusão desconhecida.');
                return;
            }

            const confirmBtn = document.getElementById('confirm-delete-config-btn');
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Processando...';

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify(payload)
                });

                let result = {};
                try {
                    result = await response.json();
                } catch (e) {
                    // ... (seu código de tratamento de erro de JSON está ótimo, pode manter)
                }

                if (response.ok && result.success) {
                    window.alert(result.message);
                    closeDeleteConfigModal();
                    window.location.reload();

                } else if (response.status === 400 || response.status === 409) {
                    // 🎯 O Controller agora envia 400 se houver reservas ativas
                    window.alert(result.message);
                    closeDeleteConfigModal();
                } else {
                    // ... (resto do seu tratamento de erro)
                    const finalErrorMsg = result.error || result.message ||
                        `Erro de servidor (Status: ${response.status}).`;
                    window.alert('Erro ao excluir: ' + finalErrorMsg);
                    closeDeleteConfigModal();
                }
            } catch (error) {
                // ... (seu catch de erro de rede)
            } finally {
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Continuar';
            }
        }

        // Listener do botão de Confirmação Final do Modal
        document.getElementById('confirm-delete-config-btn').addEventListener('click', function() {
            // Se já for a 2ª rodada (confirmação de cliente), prossegue imediatamente.
            if (pendingDeleteAction.isConfirmed) {
                executeDeleteAction(true);
                return;
            }

            // Caso contrário, coleta a justificativa e chama a função de checagem.
            const justificationInput = document.getElementById('config-justification-input');
            const justificationError = document.getElementById('justification-error');
            const justification = justificationInput.value.trim();

            if (justification.length < 5) {
                justificationError.textContent =
                    'Por favor, insira uma justificativa válida (mínimo 5 caracteres).';
                justificationError.classList.remove('hidden');
                justificationInput.focus();
                return;
            }
            justificationError.classList.add('hidden');

            // Armazena a justificativa no estado temporário
            pendingDeleteAction.justification = justification;

            // Dispara a checagem de conflito (com a justificativa pronta)
            executeDeleteAction(false);
        });


        // Função para Excluir Dia Inteiro (Chamada pelo Botão 'Excluir Dia Recorrente')
        function deleteDayConfig(configId, dayName) {

            // 1. Configura a ação pendente usando o ID do registro no banco
            pendingDeleteAction = {
                type: 'day',
                configId: configId, // 👈 Mudamos de dayOfWeek para configId
                isConfirmed: false,
                justification: null,
            };

            // 2. Mensagem Inicial (Adicionei um alerta sobre as reservas)
            const initialMessage =
                `Tem certeza que deseja **desativar e remover** TODAS as faixas de horário de <b>${dayName}</b>?<br><br>` +
                `<small class="text-red-500">Nota: Isso só será permitido se não houver reservas de clientes pagas ou pendentes.</small>`;

            // 3. Abre o modal e espera a justificativa.
            openDeleteConfigModal(initialMessage, 0);
        }

        // Exporta a função para o HTML
        window.deleteDayConfig = deleteDayConfig;
        window.removeSlotFormRow = removeSlotFormRow;


        // === INICIALIZAÇÃO NO DOMContentLoaded (CORREÇÃO CRÍTICA) ===
        document.addEventListener('DOMContentLoaded', function() {
            // Inicialização dos Listeners
            document.querySelectorAll('.day-toggle-master').forEach(attachMasterToggleListener);
            document.querySelectorAll('.add-slot-btn').forEach(attachAddSlotListener);

            // Inicializa o estado dos inputs e botões (no carregamento da página) usando loop numérico (0 a 6)
            for (let i = 0; i <= 6; i++) {
                const checkbox = document.getElementById(`day-active-${i}`);
                if (checkbox) {
                    const isChecked = checkbox.checked;
                    // Chamamos para garantir o estado inicial dos inputs e botões
                    updateSlotInputsState(i, !isChecked);
                }
            }
        });
    </script>
</x-app-layout>
