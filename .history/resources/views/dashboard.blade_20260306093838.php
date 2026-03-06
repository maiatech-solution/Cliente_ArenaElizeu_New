<x-app-layout>

    @php
        // Garantindo que as variáveis existam, se não forem passadas
        $pendingReservationsCount = $pendingReservationsCount ?? 0;
        $expiringSeriesCount = $expiringSeriesCount ?? 0;
        $expiringSeries = $expiringSeries ?? [];
    @endphp

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Calendário de Reservas') }}
        </h2>
    </x-slot>

    {{-- IMPORTAÇÕES (Mantidas) --}}
    <link href='https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.11/main.min.css' rel='stylesheet' />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src='https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.11/index.global.min.js'></script>
    <script src='https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.11/locale/pt-br.min.js'></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>


    <style>
        /* 1. CONTAINER E ESTRUTURA GERAL */
        .calendar-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 10px;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .fc {
            font-family: 'Inter', sans-serif;
            color: #333;
            font-size: 0.85rem;
        }

        .fc-toolbar-title {
            font-size: 1.1rem !important;
        }

        /* 2. MODAIS E OVERLAYS */
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
            padding: 10px;
        }

        .modal-overlay.hidden {
            display: none !important;
        }

        /* 3. ESTILIZAÇÃO DE EVENTOS (CORES) */
        .fc-event-available {
            background-color: #10B981 !important;
            border-color: #059669 !important;
            color: white !important;
            cursor: pointer;
            padding: 2px 5px;
            border-radius: 4px;
            opacity: 0.8;
        }

        .fc-event-recurrent {
            background-color: #C026D3 !important;
            border-color: #A21CAF !important;
            font-weight: 700 !important;
        }

        .fc-event-quick {
            background-color: #4f46e5 !important;
            border-color: #4338ca !important;
        }

        .fc-event-pending {
            background-color: #ff9800 !important;
            border-color: #f97316 !important;
            font-style: italic;
        }

        .fc-event-no-show {
            background-color: #E53E3E !important;
            border-color: #C53030 !important;
            font-weight: 700 !important;
        }

        .fc-event-maintenance {
            background-color: #DB2777 !important;
            border-color: #BE185D !important;
            font-weight: bold !important;
            text-transform: uppercase;
        }

        .fc-event-paid {
            background-color: #9CA3AF !important;
            border-color: #6B7280 !important;
            opacity: 0.5 !important;
            filter: grayscale(100%);
            text-decoration: line-through;
        }

        /* 4. REGRAS DE VISIBILIDADE */
        .fc-event-cancelled,
        .fc-event-rejected {
            display: none !important;
        }

        .fc-event-recurrent,
        .fc-event-quick,
        .fc-event-no-show,
        .fc-event-maintenance {
            color: #ffffff !important;
        }

        .cashier-closed-locked {
            opacity: 0.6 !important;
            filter: grayscale(100%) !important;
            pointer-events: none !important;
            background-image: linear-gradient(45deg, rgba(0, 0, 0, 0.05) 25%, transparent 25%, transparent 50%, rgba(0, 0, 0, 0.05) 50%, rgba(0, 0, 0, 0.05) 75%, transparent 75%, transparent) !important;
            background-size: 15px 15px !important;
        }

        /* 5. AUTOCOMPLETE (AJUSTADO PARA DINÂMICO) */

        /* NOVA REGRA: Classe para aplicar na div pai do input de nome para empurrar o WhatsApp */
        .autocomplete-active {
            margin-bottom: 210px !important;
            /* Altura aproximada da lista + respiro */
            transition: margin-bottom 0.2s ease;
        }

        #client-autocomplete-results {
            position: absolute;
            max-height: 200px;
            overflow-y: auto;
            border-radius: 8px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
            z-index: 3000;
            background-color: white;
            width: 100%;
            left: 0;
            top: 100%;
            /* Garante que comece logo abaixo do input */
        }

        #client-autocomplete-results div {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
        }

        #client-autocomplete-results div:last-child {
            border-bottom: none;
        }

        /* 6. RESPONSIVIDADE */
        @media (max-width: 640px) {
            .calendar-container {
                margin: 10px auto;
                padding: 5px;
                border-radius: 0;
            }

            .fc-event-title {
                font-size: 10px !important;
                font-weight: 600;
            }

            .fc-toolbar {
                flex-direction: column;
                gap: 10px;
            }

            .modal-overlay>div {
                width: 100% !important;
                margin: 0 !important;
                max-height: 98vh;
            }

            .autocomplete-active {
                margin-bottom: 180px !important;
            }
        }

        /* 7. SCROLLBAR CUSTOMIZADA */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #4f46e5;
            border-radius: 10px;
        }

        /* 8. ANIMAÇÕES */
        @keyframes pulse-red {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(229, 62, 62, 0.4);
            }

            70% {
                transform: scale(1.02);
                box-shadow: 0 0 0 8px rgba(229, 62, 62, 0);
            }

            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(229, 62, 62, 0);
            }
        }

        .animate-pulse-red {
            animation: pulse-red 2s infinite;
        }
    </style>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">

                {{-- 🚀 ALERTA DE CONFIGURAÇÃO DA ELITE SOCCER (DINÂMICO) --}}
                @php
                    $site_info = \App\Models\CompanyInfo::first();
                    $configPendente =
                        !$site_info || empty($site_info->nome_fantasia) || empty($site_info->whatsapp_suporte);
                @endphp

                @if ($configPendente)
                    <div class="mb-6 animate-bounce-slow">
                        <div
                            class="bg-amber-50 border-l-8 border-amber-500 p-5 rounded-2xl shadow-xl flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="p-3 bg-amber-500 rounded-full mr-4 shadow-lg">
                                    <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-black text-amber-900 uppercase tracking-tighter">
                                        Configuração Incompleta!</h3>
                                    <p class="text-amber-700 font-medium">Os dados da arena (Nome e WhatsApp) não foram
                                        preenchidos. O sistema de reservas não funcionará corretamente.</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.company.edit') }}"
                                class="bg-amber-600 hover:bg-amber-700 text-white font-bold py-2 px-6 rounded-xl transition duration-300 shadow-md uppercase text-sm tracking-widest">
                                Configurar Agora
                            </a>
                        </div>
                    </div>

                    <style>
                        @keyframes bounce-slow {

                            0%,
                            100% {
                                transform: translateY(0);
                            }

                            50% {
                                transform: translateY(-5px);
                            }
                        }

                        .animate-bounce-slow {
                            animation: bounce-slow 3s infinite ease-in-out;
                        }
                    </style>
                @endif

                {{-- Contêiner para Mensagens Dinâmicas (Já existente no seu código) --}}
                <div id="dashboard-message-container">

                    {{-- Contêiner para Mensagens Dinâmicas (Substituindo Session Flash messages via JS) --}}
                    <div id="dashboard-message-container">
                        {{-- Mensagens de sessão (mantidas para a primeira carga do Blade) --}}
                        @if (session('success'))
                            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded"
                                role="alert">
                                <p>{{ session('success') }}</p>
                            </div>
                        @endif

                        @if (session('warning'))
                            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4 rounded"
                                role="alert">
                                <p>{{ session('warning') }}</p>
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded"
                                role="alert">
                                <p>{{ session('error') }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- ALERTA DE PENDÊNCIA RENDERIZADO PELO SERVIDOR (COM VERIFICAÇÃO DE SEGURANÇA) --}}
                    <div id="pending-alert-container">
                        @if ($pendingReservationsCount > 0)
                            <div class="bg-orange-100 border-l-4 border-orange-500 text-orange-700 p-4 mb-6 rounded-lg shadow-md flex flex-col sm:flex-row items-start sm:items-center justify-between transition-all duration-300 transform hover:scale-[1.005]"
                                role="alert">
                                <div class="flex items-start">
                                    <svg class="h-6 w-6 flex-shrink-0 mt-0.5 sm:mt-0 mr-3 text-orange-500"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <div>
                                        <p class="font-bold text-lg">Atenção: Pendências!</p>
                                        <p class="mt-1 text-sm">Você tem <span
                                                class="font-extrabold text-orange-900">{{ $pendingReservationsCount }}</span>
                                            pré-reserva(s) aguardando sua ação.</p>
                                    </div>
                                </div>
                                <div class="mt-4 sm:mt-0 sm:ml-6">
                                    <a href="{{ route('admin.reservas.pendentes') }}"
                                        class="inline-block bg-orange-600 hover:bg-orange-700 active:bg-orange-800 text-white font-bold py-2 px-6 rounded-lg text-sm transition duration-150 ease-in-out shadow-lg">
                                        Revisar Pendências
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>


                    {{-- ALERTA E BOTÃO PARA RENOVAÇÃO RECORRENTE (COM VERIFICAÇÃO DE SEGURANÇA) --}}
                    @if ($expiringSeriesCount > 0)
                        <div id="renewal-alert-container" data-series='@json($expiringSeries)'
                            data-count="{{ $expiringSeriesCount }}"
                            class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded-lg shadow-md flex flex-col items-start transition-all duration-300 transform hover:scale-[1.005]"
                            role="alert">

                            <div class="flex items-start w-full">
                                <svg class="h-6 w-6 flex-shrink-0 mt-0.5 mr-3 text-yellow-500" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <div class="w-full">
                                    <p class="font-bold text-lg">ALERTA DE RENOVAÇÃO ({{ $expiringSeriesCount }}
                                        Série{{ $expiringSeriesCount > 1 ? 's' : '' }}
                                        Expira{{ $expiringSeriesCount > 1 ? 'm' : '' }} em Breve)</p>
                                    <p id="renewal-message" class="mt-1 text-sm mb-3">
                                        <span class="font-extrabold text-yellow-900">{{ $expiringSeriesCount }}</span>
                                        série(s) de agendamento recorrente de clientes está(ão) prestes a expirar nos
                                        próximos 30 dias.
                                    </p>

                                    {{-- NOVO: DETALHES DE EXPIRAÇÃO NO ALERTA (6 MESES) --}}
                                    <div class="space-y-2 p-3 bg-yellow-50 rounded border border-yellow-200">
                                        <p class="font-semibold text-sm text-yellow-800">Detalhes para Renovação
                                            (Sugestão:
                                            +6 meses):</p>
                                        @foreach ($expiringSeries as $seriesItem)
                                            @php
                                                $lastDate = \Carbon\Carbon::parse($seriesItem['last_date']);
                                                $suggestedNewDate = $lastDate->copy()->addMonths(6); // ✅ MUDANÇA AQUI: +6 meses
                                            @endphp
                                            <div class="text-xs text-gray-700">
                                                <strong>{{ $seriesItem['client_name'] }}</strong>
                                                ({{ $seriesItem['slot_time'] }})
                                                expira em
                                                {{ $lastDate->format('d/m/Y') }}.
                                                <span class="font-bold text-green-600">Renovação sugerida até
                                                    {{ $suggestedNewDate->format('d/m/Y') }}.</span>
                                            </div>
                                        @endforeach
                                    </div>
                                    {{-- FIM NOVO DETALHE --}}
                                </div>
                            </div>

                            <button onclick="openRenewalModal()"
                                class="mt-4 bg-yellow-600 hover:bg-yellow-700 active:bg-yellow-800 text-white font-bold py-2 px-6 rounded-lg text-sm transition duration-150 ease-in-out shadow-lg">
                                Revisar Renovações
                            </button>
                        </div>
                    @endif

                    {{-- 🏟️ NOVO: SELETOR DE ARENAS NO DASHBOARD --}}
                    <div
                        class="mb-6 p-4 bg-indigo-50 border border-indigo-200 rounded-xl shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="flex items-center">
                            <div class="p-2 bg-indigo-600 rounded-lg mr-3 shadow-md">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div>
                                <label for="filter_arena"
                                    class="block text-sm font-bold text-indigo-900 uppercase tracking-wider">Visualizar
                                    Agenda da Quadra:</label>
                                <p class="text-xs text-indigo-600">Selecione para filtrar os horários no calendário</p>
                            </div>
                        </div>
                        <select id="filter_arena"
                            class="block w-full sm:w-72 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-bold text-gray-700 h-12">

                            @foreach (\App\Models\Arena::all() as $arena)
                                <option value="{{ $arena->id }}">{{ $arena->name }}</option>
                            @endforeach
                        </select>
                    </div>


                    {{-- Legenda Otimizada e Responsiva --}}
                    <div
                        class="flex sm:flex-wrap items-center gap-4 mb-6 overflow-x-auto pb-2 scrollbar-hide whitespace-wrap justify-center">

                        <div
                            class="flex items-center px-2 py-1 bg-fuchsia-50 rounded-md border border-fuchsia-100 flex-shrink-0">
                            <span class="w-3 h-3 rounded-full bg-fuchsia-700 mr-1.5"></span>
                            <span class="text-[11px] text-fuchsia-700 font-bold uppercase">Fixo</span>
                        </div>

                        <div
                            class="flex items-center px-2 py-1 bg-indigo-50 rounded-md border border-indigo-100 flex-shrink-0">
                            <span class="w-3 h-3 rounded-full bg-indigo-600 mr-1.5"></span>
                            <span class="text-[11px] text-indigo-700 font-bold uppercase">Avulso</span>
                        </div>

                        <div
                            class="flex items-center px-2 py-1 bg-pink-50 rounded-md border border-pink-200 flex-shrink-0">
                            <span class="w-3 h-3 rounded-full bg-pink-600 mr-1.5"></span>
                            <span class="text-[11px] text-pink-700 font-bold uppercase">Manutenção</span>
                        </div>

                        <div
                            class="flex items-center px-2 py-1 bg-red-50 rounded-md border border-red-100 flex-shrink-0">
                            <span class="w-3 h-3 rounded-full bg-red-600 mr-1.5"></span>
                            <span class="text-[11px] text-red-700 font-bold uppercase">Falta</span>
                        </div>

                        <div
                            class="flex items-center px-2 py-1 bg-gray-100 rounded-md border border-gray-200 flex-shrink-0 opacity-70">
                            <span class="w-3 h-3 rounded-full bg-gray-500 mr-1.5"></span>
                            <span class="text-[11px] text-gray-700 font-bold uppercase">Pago</span>
                        </div>

                        <div
                            class="flex items-center px-2 py-1 bg-green-50 rounded-md border border-green-200 flex-shrink-0">
                            <span class="w-3 h-3 rounded-full bg-green-500 mr-1.5"></span>
                            <span class="text-[11px] text-green-700 font-bold uppercase">Livre</span>
                        </div>
                    </div>

                    <div class="calendar-container">
                        <div id='calendar'></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal de Detalhes de Reserva (RESERVAS EXISTENTES CONFIRMADAS/RECORRENTES) --}}
        <div id="event-modal" class="modal-overlay hidden" onclick="closeEventModal()">
            <div class="bg-white p-6 rounded-xl shadow-2xl max-w-sm w-full transition-all duration-300 transform scale-100"
                onclick="event.stopPropagation()">
                <h3 class="text-xl font-bold text-indigo-700 mb-4 border-b pb-2">Detalhes da Reserva Confirmada</h3>
                <div class="space-y-3 text-gray-700" id="modal-content">
                </div>
                <div class="mt-6 w-full space-y-2" id="modal-actions">
                    {{-- Botões injetados pelo JS --}}
                    <button onclick="closeEventModal()"
                        class="w-full px-4 py-2 bg-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-400 transition duration-150">
                        Fechar
                    </button>
                </div>
            </div>
        </div>

        {{-- NOVO: Modal de Ação Pendente (Abre ao clicar no slot Laranja) --}}
        <div id="pending-action-modal" class="modal-overlay hidden" onclick="closePendingActionModal()">
            <div class="bg-white p-6 rounded-xl shadow-2xl max-w-lg w-full transition-all duration-300 transform scale-100"
                onclick="event.stopPropagation()">
                <h3 class="text-xl font-bold text-orange-600 mb-4 border-b pb-2 flex items-center">
                    <svg class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Ação Requerida: Pré-Reserva Pendente
                </h3>

                <div class="mb-6 p-4 bg-orange-50 border border-orange-200 rounded-lg">
                    <div class="space-y-2 text-gray-700" id="pending-modal-content">
                        {{-- Conteúdo Injetado via JS --}}
                    </div>
                </div>

                <form id="pending-action-form" onsubmit="return false;">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="reserva_id" id="pending-reserva-id">

                    <div id="rejection-reason-area" class="mb-4 hidden">
                        <label for="rejection-reason" class="block text-sm font-medium text-gray-700 mb-1">Motivo da
                            Rejeição (Opcional):</label>
                        <textarea name="rejection_reason" id="rejection-reason" rows="2"
                            placeholder="Descreva o motivo para liberar o horário." class="w-full p-2 border border-gray-300 rounded-lg"></textarea>
                    </div>

                    <div id="confirmation-value-area" class="mb-4">
                        <label for="confirmation-value" class="block text-sm font-medium text-gray-700 mb-1">Valor do
                            Sinal/Confirmação (R$):</label>
                        {{-- ✅ CORRIGIDO: Alterado para type="text" e adicionada a classe de formatação --}}
                        <input type="text" name="confirmation_value" id="confirmation-value" value="0,00"
                            required
                            class="w-full p-2 border border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 input-money-quick">
                        <p class="text-xs text-gray-500 mt-1">Este valor é opcional, mas define a confirmação da
                            reserva.
                        </p>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closePendingActionModal()"
                            class="px-4 py-2 bg-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-400 transition duration-150">
                            Voltar
                        </button>
                        <button type="button" id="reject-pending-btn"
                            class="px-4 py-2 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700 transition duration-150">
                            Rejeitar
                        </button>
                        <button type="submit" id="confirm-pending-btn"
                            class="px-4 py-2 bg-green-600 text-white font-bold rounded-lg hover:bg-green-700 transition duration-150">
                            Confirmar Reserva
                        </button>
                    </div>
                </form>
            </div>
        </div>


        {{-- MODAL DE CANCELAMENTO (para o Motivo do Cancelamento e Decisão de Estorno) --}}
        <div id="cancellation-modal" class="modal-overlay hidden">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 m-4 transform transition-transform duration-300 scale-95 opacity-0"
                id="cancellation-modal-content" onclick="event.stopPropagation()">
                <h3 id="modal-title-cancel" class="text-xl font-bold text-red-700 mb-4 border-b pb-2">Confirmação de
                    Cancelamento</h3>

                <p id="modal-message-cancel" class="text-gray-700 mb-4 font-medium"></p>

                {{-- NOVO: Área de Decisão de Estorno --}}
                <div id="refund-decision-area" class="mb-6 p-4 border border-red-300 bg-red-50 rounded-lg"
                    style="display: none;">
                    <p class="font-bold text-red-700 mb-3 flex items-center">
                        <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 10h18M7 5h10M5 15h14M7 20h10" />
                        </svg>
                        <span id="refund-title-text">HOUVE SINAL PAGO:</span> R$ <span id="refund-signal-value"
                            class="font-extrabold ml-1">0,00</span>
                    </p>
                    <p class="text-sm text-gray-700 mb-2 font-medium">O que fazer com o valor?</p>
                    <div class="flex flex-wrap gap-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="refund_choice" value="refund" id="refund-choice-yes"
                                class="form-radio h-5 w-5 text-red-600 border-red-500 focus:ring-red-500">
                            <span class="ml-2 text-red-700 font-semibold text-sm">Devolver TODO o valor (Estornar do
                                Caixa)</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="refund_choice" value="keep" id="refund-choice-no"
                                class="form-radio h-5 w-5 text-green-600 border-green-500 focus:ring-green-500"
                                checked>
                            <span class="ml-2 text-green-700 font-semibold text-sm">Manter TODO o valor (Fica no
                                Caixa)</span>
                        </label>
                    </div>
                    {{-- ✅ NOVO: Nota sobre estorno parcial --}}
                    <p class="text-xs text-gray-500 mt-2 font-medium">⚠️ Para estornar um valor parcial, mantenha o
                        valor
                        no caixa e utilize a página de **Caixa/Pagamentos** para registrar a saída parcial
                        posteriormente.
                    </p>
                </div>
                {{-- FIM NOVO --}}

                <div class="mb-6">
                    <label for="cancellation-reason-input" class="block text-sm font-medium text-gray-700 mb-2">
                        Motivo do Cancelamento:
                    </label>
                    <textarea id="cancellation-reason-input" rows="3"
                        class="w-full p-2 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                        placeholder="Obrigatório, descreva o motivo do cancelamento (mínimo 5 caracteres)..."></textarea>
                </div>

                <div class="flex justify-end space-x-3">
                    <button onclick="closeCancellationModal()" type="button"
                        class="px-4 py-2 bg-gray-200 text-gray-800 font-semibold rounded-lg hover:bg-gray-300 transition duration-150">
                        Fechar
                    </button>
                    <button id="confirm-cancellation-btn" type="button"
                        class="px-4 py-2 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700 transition duration-150">
                        Confirmar Ação
                    </button>
                </div>
            </div>
        </div>


        {{-- MODAL DE REGISTRO DE FALTA (NO-SHOW) ATUALIZADO COM ESTORNO PARCIAL E VALIDAÇÕES INTERNAS --}}
        <div id="no-show-modal" class="modal-overlay hidden" onclick="closeNoShowModal()">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 m-4 transform transition-transform duration-300 scale-95 opacity-0"
                id="no-show-modal-content" onclick="event.stopPropagation()">
                <h3 class="text-xl font-bold text-red-700 mb-4 border-b pb-2">Marcar como Falta (No-Show)</h3>

                <p id="no-show-modal-message" class="text-gray-700 mb-4 font-medium"></p>

                <form id="no-show-form" onsubmit="return false;">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="reserva_id" id="no-show-reserva-id">
                    <input type="hidden" name="paid_amount_ref" id="paid-amount-ref">

                    <div id="no-show-refund-area" class="mb-6 p-4 border border-red-300 bg-red-50 rounded-lg hidden">
                        <p class="font-bold text-red-700 mb-3 flex items-center">
                            <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 10h18M7 5h10M5 15h14M7 20h10" />
                            </svg>
                            VALOR JÁ PAGO: R$ <span id="no-show-paid-amount" class="font-extrabold ml-1">0,00</span>
                        </p>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Decisão Financeira:</label>
                            <select id="should_refund_no_show" name="should_refund"
                                onchange="toggleDashboardNoShowRefundInput()"
                                class="w-full p-2 border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500 font-bold">
                                <option value="false">🔒 Reter Tudo (Fica para a Arena como Multa)</option>
                                <option value="true">💸 Estornar / Devolver (Saída do Caixa)</option>
                            </select>
                        </div>

                        {{-- CAMPO PARA VALOR PERSONALIZADO COM VALIDAÇÃO INTERNA --}}
                        <div id="customNoShowRefundDiv"
                            class="hidden mt-4 p-3 bg-white rounded-lg border border-red-200 shadow-inner">
                            <label class="block text-xs font-bold text-red-600 uppercase mb-1">Valor a Devolver
                                (R$):</label>
                            <input type="number" step="0.01" id="custom_no_show_refund_amount"
                                name="refund_amount"
                                class="w-full p-2 border-red-300 rounded-md focus:ring-red-500 focus:border-red-500 font-bold text-lg"
                                placeholder="0.00">

                            {{-- MENSAGEM DE ERRO DE VALOR --}}
                            <span id="no-show-error-span"
                                class="text-[10px] text-red-600 font-bold mt-1 hidden flex items-center">
                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                O estorno não pode ser maior que o valor pago.
                            </span>

                            <p class="text-[10px] text-gray-500 mt-1 italic">O saldo não devolvido será mantido no
                                caixa como lucro/multa.</p>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="no-show-reason-input" class="block text-sm font-medium text-gray-700 mb-2">Motivo
                            da Falta:</label>
                        <textarea id="no-show-reason-input" name="no_show_reason" rows="3"
                            class="w-full p-2 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500 transition duration-150"
                            placeholder="Obrigatório (mínimo 5 caracteres)..."></textarea>

                        {{-- NOVO: MENSAGEM DE ERRO DE MOTIVO --}}
                        <span id="no-show-reason-error-span"
                            class="text-[10px] text-red-600 font-bold mt-1 hidden flex items-center">
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                    clip-rule="evenodd" />
                            </svg>
                            Por favor, forneça o motivo da falta com pelo menos 5 caracteres.
                        </span>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button onclick="closeNoShowModal()" type="button"
                            class="px-4 py-2 bg-gray-200 text-gray-800 font-semibold rounded-lg hover:bg-gray-300 transition duration-150">Fechar</button>
                        <button id="confirm-no-show-btn" type="submit"
                            class="px-4 py-2 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700 transition duration-150 shadow-md">Confirmar
                            Falta</button>
                    </div>
                </form>
            </div>
        </div>


        {{-- MODAL DE RENOVAÇÃO DE SÉRIE --}}
        <div id="renewal-modal" class="modal-overlay hidden" onclick="closeRenewalModal()">
            <div class="bg-white p-6 rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto"
                onclick="event.stopPropagation()">
                <h3 class="text-xl font-bold text-yellow-700 mb-4 border-b pb-2">Gerenciar Renovações Recorrentes</h3>

                <p class="text-sm text-gray-700 mb-4">
                    Abaixo estão as séries de reservas que atingirão o limite (expirarão) nas próximas semanas.
                    **Ao clicar em Renovar, o sistema tentará estender a série por mais seis meses.**
                </p>

                <div id="renewal-message-box" class="hidden p-3 mb-4 rounded-lg text-sm font-medium"></div>

                <div id="renewal-list" class="space-y-4">
                    {{-- Lista injetada pelo JS --}}
                    <p class="text-gray-500 italic">Nenhuma série a ser renovada no momento.</p>
                </div>

                <div class="mt-6 flex justify-end">
                    <button onclick="closeRenewalModal()"
                        class="px-4 py-2 bg-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-400 transition duration-150">
                        Fechar
                    </button>
                </div>
            </div>
        </div>


        {{-- Modal de Agendamento Rápido (Responsivo & Autocomplete Nativo) --}}
        <div id="quick-booking-modal" class="modal-overlay hidden" onclick="closeQuickBookingModal()">
            <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full max-h-[95vh] flex flex-col transition-all duration-300 transform scale-100 mx-4 sm:mx-0"
                onclick="event.stopPropagation()">

                <div class="p-4 border-b flex justify-between items-center bg-gray-50 rounded-t-xl">
                    <h3 class="text-lg font-bold text-green-700">Agendamento Rápido</h3>
                    <button onclick="closeQuickBookingModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="p-4 overflow-y-auto custom-scrollbar">
                    <form id="quick-booking-form">
                        @csrf

                        {{-- Informações do Horário --}}
                        <div id="slot-info-display"
                            class="mb-4 p-3 bg-indigo-50 border border-indigo-100 rounded-lg text-xs sm:text-sm text-gray-700">
                        </div>

                        {{-- Hidden Inputs (Mantidos intactos) --}}
                        <input type="hidden" name="schedule_id" id="quick-schedule-id">
                        <input type="hidden" name="date" id="quick-date">
                        <input type="hidden" name="start_time" id="quick-start-time">
                        <input type="hidden" name="end_time" id="quick-end-time">
                        <input type="hidden" name="price" id="quick-price">
                        <input type="hidden" name="reserva_id_to_update" id="reserva-id-to-update">
                        <input type="hidden" name="arena_id" id="quick-arena-id">

                        <div class="space-y-4">
                            {{-- Campo Nome com Dropdown Acoplado e Lógica de Empurrar --}}
                            <div class="relative transition-all duration-300" id="name-field-wrapper">
                                <label for="client_name" class="block text-xs font-bold text-gray-500 uppercase">
                                    Nome do Cliente *
                                </label>
                                <input type="text" name="client_name" id="client_name" required
                                    autocomplete="off"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm h-10">

                                {{-- LISTA DE RESULTADOS --}}
                                <div id="client-autocomplete-results"
                                    class="absolute z-[3000] w-full bg-white border border-gray-200 rounded-b-md shadow-xl hidden max-h-48 overflow-y-auto top-full left-0">
                                </div>
                            </div>

                            {{-- Campo WhatsApp: Este será empurrado para baixo quando o autocomplete estiver ativo --}}
                            <div>
                                <label for="client_contact"
                                    class="block text-xs font-bold text-gray-500 uppercase">WhatsApp (11 dígitos)
                                    *</label>
                                <input type="tel" name="client_contact" id="client_contact" required
                                    maxlength="11" placeholder="Ex: 91999999999"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm h-10">
                                <p id="whatsapp-error-message"
                                    class="text-[10px] text-red-600 mt-1 hidden font-semibold">⚠️ Insira 11 dígitos.
                                </p>
                                <div id="client-reputation-display" class="mt-1"></div>
                            </div>

                            {{-- Financeiro --}}
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="signal_value_quick"
                                        class="block text-xs font-bold text-gray-500 uppercase">Sinal (R$)</label>
                                    <input type="text" name="signal_value" id="signal_value_quick" value="0,00"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm h-10 input-money-quick text-right">
                                </div>
                                <div>
                                    <label for="payment_method_quick"
                                        class="block text-xs font-bold text-gray-500 uppercase">Método</label>
                                    <select name="payment_method" id="payment_method_quick" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm h-10">
                                        <option value="">Selecione</option>
                                        <option value="pix">PIX</option>
                                        <option value="cartao">Cartão</option>
                                        <option value="dinheiro">Dinheiro</option>
                                        <option value="outro">Sem Sinal</option>
                                    </select>
                                </div>
                            </div>

                            {{-- Recorrência --}}
                            <div class="p-3 border border-indigo-100 rounded-lg bg-indigo-50/50 flex items-center">
                                <input type="checkbox" name="is_recurrent" id="is-recurrent" value="1"
                                    class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                <label for="is-recurrent"
                                    class="ml-3 text-sm font-bold text-indigo-700 uppercase tracking-tighter">Reserva
                                    Recorrente (6 meses)</label>
                            </div>

                            <div>
                                <label for="notes"
                                    class="block text-xs font-bold text-gray-500 uppercase">Observações</label>
                                <textarea name="notes" id="notes" rows="2"
                                    class="mt-1 block w-full border-gray-300 rounded-md text-sm shadow-sm"></textarea>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="p-4 border-t bg-gray-50 rounded-b-xl grid grid-cols-2 gap-3">
                    <button type="button" onclick="closeQuickBookingModal()"
                        class="px-4 py-2 bg-gray-200 text-gray-700 font-bold rounded-lg text-sm">Cancelar</button>
                    <button type="submit" form="quick-booking-form" id="submit-quick-booking"
                        class="px-4 py-2 bg-green-600 text-white font-bold rounded-lg shadow-md text-sm">Confirmar</button>
                </div>
            </div>
        </div>


        <script>
            window.closedDatesCache = {};
            // === CONFIGURAÇÕES E ROTAS (AJUSTADAS) ===
            const PENDING_API_URL = `{{ route('api.reservas.pendentes.count') }}`;
            const CONFIRMED_API_URL = `{{ route('api.reservas.confirmadas') }}`;
            const AVAILABLE_API_URL = `{{ route('api.horarios.disponiveis') }}`;
            const SHOW_RESERVA_URL = `{{ route('admin.reservas.show', ':id') }}`;

            const USER_REPUTATION_URL = `{{ route('api.users.reputation', ':contact') }}`;

            const PAYMENT_INDEX_URL = `{{ route('admin.payment.index') }}`;

            // ROTAS DE SUBMISSÃO
            const RECURRENT_STORE_URL = `{{ route('api.reservas.store_recurrent') }}`;
            const QUICK_STORE_URL = `{{ route('api.reservas.store_quick') }}`;
            const RENEW_SERIE_URL = `{{ url('admin/reservas') }}/:masterReserva/renew-serie`;

            // ROTAS DE AÇÕES PENDENTES
            const CONFIRM_PENDING_URL = `{{ route('admin.reservas.confirmar', ':id') }}`;
            const REJECT_PENDING_URL = `{{ route('admin.reservas.rejeitar', ':id') }}`;

            // ROTAS DE CANCELAMENTO
            const CANCEL_PONTUAL_URL = `{{ route('admin.reservas.cancelar_pontual', ':id') }}`;
            const CANCEL_SERIE_URL = `{{ route('admin.reservas.cancelar_serie', ':id') }}`;
            const CANCEL_PADRAO_URL = `{{ route('admin.reservas.cancelar', ':id') }}`;

            // 🎯 ROTA PARA MARCAR COMO FALTA
            const NO_SHOW_URL = `{{ route('admin.reservas.no_show', ':id') }}`;
            // ======================================

            // TOKEN CSRF
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                document.querySelector('input[name="_token"]')?.value;

            // VARIÁVEIS GLOBAIS DE ESTADO
            let calendar;
            let currentReservaId = null;
            let currentMethod = null;
            let currentUrlBase = null;
            let globalExpiringSeries = [];
            let currentClientStatus = {
                is_vip: false,
                reputation_tag: ''
            };

            // Elementos do Formulário
            const clientNameInput = () => document.getElementById('client_name');
            const clientContactInput = () => document.getElementById('client_contact');
            const whatsappError = () => document.getElementById('whatsapp-error-message');
            const reputationDisplay = () => document.getElementById('client-reputation-display');
            const signalValueInputQuick = () => document.getElementById('signal_value_quick');
            const confirmationValueInput = () => document.getElementById('confirmation-value');


            // === FUNÇÃO PARA FORMATAR MOEDA NO QUICK MODAL E PENDENTE MODAL ===
            const formatMoneyQuick = (input) => {
                let value = input.value.replace(/\D/g, ''); // Remove tudo que não for dígito
                if (value.length === 0) return '0,00';

                while (value.length < 3) {
                    value = '0' + value;
                }

                let integerPart = value.substring(0, value.length - 2);
                let decimalPart = value.substring(value.length - 2);

                integerPart = integerPart.replace(/^0+/, '');
                if (integerPart.length === 0) integerPart = '0';

                integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ".");

                return `${integerPart},${decimalPart}`;
            };

            // Função para limpar e converter string monetária (ex: "1.000,50" -> 1000.50)
            const cleanAndConvertForApi = (value) => {
                if (!value) return 0.00;
                // Garante que o valor é uma string antes de tentar substituir
                value = String(value).replace(/\./g, '');
                value = value.replace(',', '.');
                return parseFloat(value) || 0.00;
            };
            // ========================================================


            document.addEventListener('DOMContentLoaded', () => {
                // 1. Carregamento das Séries Expirando
                const renewalAlertContainer = document.getElementById('renewal-alert-container');
                if (renewalAlertContainer) {
                    try {
                        const dataSeriesAttr = renewalAlertContainer.getAttribute('data-series');
                        globalExpiringSeries = dataSeriesAttr ? JSON.parse(dataSeriesAttr) : [];
                    } catch (e) {
                        console.error("Erro ao carregar dados de séries expirando:", e);
                        globalExpiringSeries = [];
                    }
                }

                // 2. Aplicar formatação nos inputs de moeda (Quick Modal e Pendência)
                document.querySelectorAll('.input-money-quick').forEach(input => {
                    input.value = formatMoneyQuick(input);

                    input.addEventListener('input', (e) => {
                        e.target.value = formatMoneyQuick(e.target);
                    });

                    input.addEventListener('blur', (e) => {
                        e.target.value = formatMoneyQuick(e.target);
                    });
                });

                // 3. 🛡️ INTERCEPTADOR GLOBAL DE CLIQUES (Trava de Segurança do Caixa)
                // Monitora cliques em links de pendências e botões de renovação
                document.addEventListener('click', async function(e) {
                    const linkPendentes = e.target.closest('a[href*="pendentes"]');
                    const btnRenovacao = e.target.closest('button[onclick="openRenewalModal()"]');

                    if (linkPendentes || btnRenovacao) {
                        // Verifica o status do caixa usando a função que já ajustamos
                        // Ela mostrará a mensagem de erro e fará o refetch do calendário se necessário
                        const aberto = await isCashierOpen(moment().format('YYYY-MM-DD'));

                        if (!aberto) {
                            e.preventDefault(); // Impede a navegação do link
                            e.stopImmediatePropagation(); // Impede a abertura do modal de renovação
                            console.log("Ação bloqueada: Caixa Fechado.");
                        }
                    }
                }, true); // O parâmetro 'true' (capture) garante que nossa trava rode antes de outros scripts
            });


            /**
             * FUNÇÃO PARA EXIBIR MENSAGENS NO DASHBOARD (Substitui alerts e session flashes via JS)
             */
            // Localize a função antiga e substitua por esta:
            function showDashboardMessage(message, type = 'success') {
                const container = document.getElementById('dashboard-message-container');
                if (!container) return;

                // Definição de cores baseadas no tipo
                const colors = {
                    error: 'bg-red-100 border-red-500 text-red-700',
                    warning: 'bg-yellow-100 border-yellow-500 text-yellow-700',
                    success: 'bg-green-100 border-green-500 text-green-700'
                };

                const colorClass = colors[type] || colors.success;

                // HTML do alerta - Começa invisível (opacity-0) e deslocado (translate-y)
                const alertHtml = `
        <div class="${colorClass} border-l-4 p-4 mb-4 rounded shadow-md transform transition-all duration-500 opacity-0 translate-y-[-10px]" role="alert">
            <p class="font-bold">${message}</p>
        </div>
    `;

                // Insere no topo da lista
                container.insertAdjacentHTML('afterbegin', alertHtml);
                const newAlert = container.firstElementChild;

                // TRUQUE PARA CORRIGIR O BUG "INVISÍVEL":
                // Usamos requestAnimationFrame para garantir que o navegador renderize o estado inicial (invisível)
                // antes de removermos a classe opacity-0. Isso força a transição visual.
                requestAnimationFrame(() => {
                    if (newAlert) {
                        newAlert.classList.remove('opacity-0', 'translate-y-[-10px]');
                    }
                });

                // Remove automaticamente após 5 segundos
                setTimeout(() => {
                    if (newAlert) {
                        // Adiciona opacidade para sumir suavemente
                        newAlert.classList.add('opacity-0');
                        // Remove do DOM após a animação de sumir (500ms)
                        setTimeout(() => newAlert.remove(), 500);
                    }
                }, 5000);
            }

            /**
             * FUNÇÃO PARA CHECAR AS RESERVAS PENDENTES EM TEMPO REAL (PERIÓDICO)
             */
            const checkPendingReservations = async () => {
                const notificationContainer = document.getElementById('pending-alert-container');
                const apiUrl = PENDING_API_URL;

                try {
                    const response = await fetch(apiUrl);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();
                    const count = data.count || 0;

                    let htmlContent = '';

                    if (count > 0) {
                        htmlContent = `
                        <div class="bg-orange-100 border-l-4 border-orange-500 text-orange-700 p-4 mb-6 rounded-lg shadow-md flex flex-col sm:flex-row items-start sm:items-center justify-between transition-all duration-300 transform hover:scale-[1.005]" role="alert">
                            <div class="flex items-start">
                                <svg class="h-6 w-6 flex-shrink-0 mt-0.5 sm:mt-0 mr-3 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <div>
                                    <p class="font-bold text-lg">Atenção: Pendências!</p>
                                    <p class="mt-1 text-sm">Você tem <span class="font-extrabold text-orange-900">${count}</span> pré-reserva(s) aguardando sua ação.</p>
                                </div>
                            </div>
                            <div class="mt-4 sm:mt-0 sm:ml-6">
                                <a href="{{ route('admin.reservas.pendentes') }}" class="inline-block bg-orange-600 hover:bg-orange-700 active:bg-orange-800 text-white font-bold py-2 px-6 rounded-lg text-sm transition duration-150 ease-in-out shadow-lg">
                                    Revisar Pendências
                                </a>
                            </div>
                        </div>
                    `;
                    } else {
                        htmlContent = '';
                    }

                    if (notificationContainer.innerHTML.trim() !== htmlContent.trim()) {
                        notificationContainer.innerHTML = htmlContent;
                    }

                } catch (error) {
                    console.error('[PENDÊNCIA DEBUG] Erro ao buscar o status de pendências:', error);
                    notificationContainer.innerHTML = '';
                }
            };

            // =========================================================
            // FUNÇÃO PARA BUSCAR REPUTAÇÃO DO CLIENTE
            // =========================================================

            /**
             * Busca a reputação do cliente via API e atualiza o modal.
             */
            async function fetchClientReputation(contact) {
                const displayEl = reputationDisplay();
                const signalInput = signalValueInputQuick();

                // Limpa estados anteriores
                displayEl.innerHTML = '<span class="text-xs text-gray-500">Buscando reputação...</span>';
                // Deixamos o valor do sinal no formulário, mas limpamos o estilo
                signalInput.removeAttribute('title');
                signalInput.classList.remove('bg-indigo-50', 'border-indigo-400', 'text-indigo-800');

                if (contact.length !== 11) {
                    displayEl.innerHTML = '';
                    currentClientStatus = {
                        is_vip: false,
                        reputation_tag: ''
                    };
                    return;
                }

                const url = USER_REPUTATION_URL.replace(':contact', contact);

                try {
                    const response = await fetch(url);

                    if (!response.ok) {
                        throw new Error(`Erro HTTP! status: ${response.status}`);
                    }

                    // A API deve retornar um objeto como: { status_tag: '<span...>', is_vip: true/false }
                    const data = await response.json();

                    currentClientStatus.is_vip = data.is_vip || false;
                    currentClientStatus.reputation_tag = data.status_tag || '';

                    // 1. Exibe a tag de reputação
                    if (currentClientStatus.reputation_tag) {
                        displayEl.innerHTML =
                            `<p class="font-semibold text-gray-700 mb-1">Reputação:</p>${currentClientStatus.reputation_tag}`;
                    } else {
                        displayEl.innerHTML =
                            '<span class="text-sm text-gray-500 font-medium p-1 bg-green-50 rounded-lg">👍 Novo Cliente ou Reputação OK.</span>';
                    }

                    // 2. Atualiza o valor do sinal se for VIP (seta para 0,00)
                    if (currentClientStatus.is_vip) {
                        signalInput.value = '0,00';
                        signalInput.setAttribute('title', 'Sinal zerado automaticamente para cliente VIP.');
                        signalInput.classList.add('bg-indigo-50', 'border-indigo-400', 'text-indigo-800');
                        displayEl.insertAdjacentHTML('beforeend',
                            '<span class="text-xs ml-2 text-indigo-600 font-bold p-1 bg-indigo-100 rounded">✅ VIP DETECTADO</span>'
                        );
                    } else {
                        // Se não for VIP, restaura para 0,00 ou o valor inicial
                        signalInput.value = '0,00';
                        signalInput.classList.remove('bg-indigo-50', 'border-indigo-400', 'text-indigo-800');
                    }

                } catch (error) {
                    console.error('[Reputation Debug] Erro ao buscar reputação:', error);
                    displayEl.innerHTML = '<span class="text-xs text-red-500">Falha ao buscar reputação.</span>';
                    currentClientStatus = {
                        is_vip: false,
                        reputation_tag: ''
                    };
                }
            }


            // =========================================================
            // FUNÇÃO DE VALIDAÇÃO WHATSAPP (11 DÍGITOS)
            // =========================================================

            /**
             * Valida se o contato do cliente é um número de WhatsApp com 11 dígitos
             * e dispara a busca de reputação se for válido.
             */
            function validateClientContact(contact) {
                const numbersOnly = contact.replace(/\D/g, '');
                const isValid = numbersOnly.length === 11;

                const errorElement = whatsappError();
                const contactInputEl = clientContactInput();
                const displayEl = reputationDisplay();

                contactInputEl.classList.remove('border-red-500', 'border-green-500');

                if (isValid) {
                    errorElement.classList.add('hidden');
                    contactInputEl.classList.add('border-green-500');
                    // ✅ NOVO: Dispara a busca de reputação apenas com 11 dígitos
                    fetchClientReputation(numbersOnly);
                } else {
                    errorElement.classList.remove('hidden');
                    contactInputEl.classList.add('border-red-500');
                    // Limpa o display se não for válido
                    displayEl.innerHTML = '';
                    signalValueInputQuick().value = '0,00';
                    currentClientStatus = {
                        is_vip: false,
                        reputation_tag: ''
                    };
                }

                return isValid;
            }


            // =========================================================
            // FUNÇÃO CORRIGIDA: Lidar com a submissão do Agendamento Rápido
            // =========================================================
            async function handleQuickBookingSubmit(event) {
                event.preventDefault();
                const form = document.getElementById('quick-booking-form');
                const submitBtn = document.getElementById('submit-quick-booking');

                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());

                // --- 🕒 GARANTIA DE FORMATO DE HORA (H:i) ---
                data.start_time = document.getElementById('quick-start-time').value;
                data.end_time = document.getElementById('quick-end-time').value;

                // --- 💰 PREPARAÇÃO DE VALORES MONETÁRIOS ---
                const rawPrice = document.getElementById('quick-price').value;
                const rawSignal = document.getElementById('signal_value_quick').value;
                const cleanValue = (val) => {
                    if (!val) return 0;
                    return parseFloat(val.toString().replace(/\./g, '').replace(',', '.')) || 0;
                };

                data.fixed_price = cleanValue(rawPrice);
                data.signal_value = cleanValue(rawSignal);
                data.is_recurrent = document.getElementById('is-recurrent').checked ? 1 : 0;

                const targetUrl = data.is_recurrent ? RECURRENT_STORE_URL : QUICK_STORE_URL;

                // 🔍 DEBUG: O QUE ESTÁ SAINDO DO NAVEGADOR?
                console.log("--- DEBUG AGENDAMENTO RÁPIDO ---");
                console.log("URL de destino:", targetUrl);
                console.log("Payload sendo enviado:", data);
                console.log("Formato start_time:", data.start_time, "| Tamanho:", data.start_time?.length);
                console.log("Formato end_time:", data.end_time, "| Tamanho:", data.end_time?.length);

                submitBtn.disabled = true;
                submitBtn.textContent = 'Processando...';

                try {
                    const response = await fetch(targetUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        // 🔍 DEBUG: POR QUE O SERVIDOR REJEITOU?
                        console.error("--- ERRO NA RESPOSTA DO SERVIDOR ---");
                        console.error("Status HTTP:", response.status);
                        console.error("Erros de Validação:", result.errors);
                        console.error("Mensagem:", result.message);
                    }

                    window.closeQuickBookingModal();

                    if (response.ok && result.success) {
                        showDashboardMessage(result.message, 'success');
                        if (window.calendar) window.calendar.refetchEvents();
                    } else {
                        if (window.calendar) window.calendar.refetchEvents();

                        const errorMsg = result.errors ?
                            Object.values(result.errors).flat().join(' ') :
                            result.message;

                        showDashboardMessage(errorMsg || "Erro ao salvar reserva.", 'error');
                    }
                } catch (error) {
                    console.error("Erro crítico na requisição:", error);
                    window.closeQuickBookingModal();
                    if (window.calendar) window.calendar.refetchEvents();
                    showDashboardMessage("Erro de conexão com o servidor.", 'error');
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Confirmar Agendamento';
                }
            }

            // =========================================================
            // FLUXO DE AÇÕES PENDENTES, CANCELAMENTO, FALTA E RENOVAÇÃO
            // =========================================================

            function closeEventModal() {
                document.getElementById('event-modal').classList.add('hidden');
            }

            function openPendingActionModal(event) {
                const extendedProps = event.extendedProps || {};
                const reservaId = event.id;
                const dateDisplay = moment(event.start).format('DD/MM/YYYY');
                const timeDisplay = moment(event.start).format('HH:mm') + ' - ' + moment(event.end).format('HH:mm');
                const priceDisplay = parseFloat(extendedProps.price || 0).toFixed(2).replace('.', ',');

                // Regex abrangente para limpar o nome do cliente que pode ter prefixos
                const prefixRegex =
                    /^\s*(?:\(?(?:PAGO|FALTA|ATRASADO|CANCELADO|REJEITADA|PENDENTE|A\sVENCER\/FALTA|RECORR(?:E)?|SINAL|RESOLVIDO)\)?[\.:\s]*\s*)+/i;
                const clientName = event.title.replace(prefixRegex, '').split(' - R$ ')[0].trim();


                document.getElementById('pending-reserva-id').value = reservaId;

                const initialPriceFormatted = parseFloat(extendedProps.price || 0).toFixed(2).replace('.', ',');
                confirmationValueInput().value = initialPriceFormatted;

                document.getElementById('pending-modal-content').innerHTML = `
                <p>O cliente **${clientName}** realizou uma pré-reserva.</p>
                <p><strong>Data:</strong> ${dateDisplay}</p>
                <p><strong>Horário:</strong> ${timeDisplay}</p>
                <p><strong>Valor Proposto:</strong> R$ ${priceDisplay}</p>
                <p class="text-xs italic mt-2 text-orange-700">A confirmação remove o slot fixo e a rejeição recria o slot fixo.</p>
            `;

                document.getElementById('rejection-reason-area').classList.add('hidden');
                document.getElementById('rejection-reason').value = '';
                document.getElementById('reject-pending-btn').textContent = 'Rejeitar';
                document.getElementById('reject-pending-btn').classList.replace('bg-red-800', 'bg-red-600');

                document.getElementById('pending-action-modal').classList.remove('hidden');
            }

            function closePendingActionModal() {
                document.getElementById('pending-action-modal').classList.add('hidden');
            }

            document.getElementById('confirm-pending-btn').addEventListener('click', function() {
                const form = document.getElementById('pending-action-form');
                const reservaId = document.getElementById('pending-reserva-id').value;
                let confirmationValue = confirmationValueInput().value;

                const signalValueFinal = cleanAndConvertForApi(confirmationValue);

                if (form.reportValidity()) {
                    const url = CONFIRM_PENDING_URL.replace(':id', reservaId);
                    const data = {
                        signal_value: signalValueFinal,
                        is_recurrent: false,
                        _token: csrfToken,
                        _method: 'PATCH',
                    };
                    sendPendingAction(url, data, 'Confirmando...');
                }
            });

            document.getElementById('reject-pending-btn').addEventListener('click', function() {
                const reasonArea = document.getElementById('rejection-reason-area');
                const reasonInput = document.getElementById('rejection-reason');

                if (reasonArea.classList.contains('hidden')) {
                    reasonArea.classList.remove('hidden');
                    this.textContent = 'Confirmar Rejeição';
                    this.classList.replace('bg-red-600', 'bg-red-800');
                } else {
                    const reservaId = document.getElementById('pending-reserva-id').value;
                    const reason = reasonInput.value.trim();

                    if (reason.length < 5) {
                        showDashboardMessage("Por favor, forneça um motivo de rejeição com pelo menos 5 caracteres.",
                            'warning');
                        return;
                    }

                    const url = REJECT_PENDING_URL.replace(':id', reservaId);
                    const data = {
                        rejection_reason: reason,
                        _token: csrfToken,
                        _method: 'PATCH',
                    };
                    sendPendingAction(url, data, 'Rejeitando...');
                }
            });

            async function sendPendingAction(url, data, buttonText) {
                const submitBtn = document.getElementById('confirm-pending-btn');
                const rejectBtn = document.getElementById('reject-pending-btn');

                // Estado de carregamento
                submitBtn.disabled = true;
                rejectBtn.disabled = true;
                if (buttonText.includes('Confirmando')) submitBtn.textContent = 'Processando...';
                if (buttonText.includes('Rejeitando')) rejectBtn.textContent = 'Processando...';

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();

                    // 🎯 PASSO 1: Fecha o modal de pendência imediatamente
                    closePendingActionModal();

                    if (response.ok && result.success) {
                        // SUCESSO
                        showDashboardMessage(result.message, 'success');
                        if (calendar) calendar.refetchEvents();
                    } else {
                        // 🎯 ERRO (Ex: Caixa Fechado)
                        // Forçamos o calendário a recarregar para garantir que o slot laranja não suma
                        if (calendar) calendar.refetchEvents();

                        showDashboardMessage(result.message || "Erro ao processar ação pendente.", 'error');
                    }
                } catch (error) {
                    console.error('Erro na ação pendente:', error);
                    closePendingActionModal();
                    if (calendar) calendar.refetchEvents();
                    showDashboardMessage("Erro de conexão.", 'error');
                } finally {
                    submitBtn.disabled = false;
                    rejectBtn.disabled = false;
                    submitBtn.textContent = 'Confirmar Reserva';
                    rejectBtn.textContent = 'Rejeitar';
                }
            }

            // --- CANCELAMENTO LÓGICA (COM ESTORNO) ---

            /**
             * Abre o modal de cancelamento com lógica de estorno/retenção.
             * @param {int} reservaId
             * @param {string} method - PATCH ou DELETE
             * @param {string} urlBase - Rota da API
             * @param {string} message - Mensagem de confirmação
             * @param {string} buttonText - Texto do botão principal
             * @param {mixed} paidOrSignalValue - Valor pago para decisão financeira
             * @param {boolean} isEventPaid - Indica se a reserva já estava concluída
             */
            function openCancellationModal(reservaId, method, urlBase, message, buttonText, paidOrSignalValue = 0, isEventPaid =
                false) {

                // 1. Limpeza inicial
                closeEventModal();
                currentReservaId = reservaId;
                currentMethod = method;
                currentUrlBase = urlBase;

                const reasonInput = document.getElementById('cancellation-reason-input');
                if (reasonInput) reasonInput.value = '';

                const refundArea = document.getElementById('refund-decision-area');
                const signalDisplay = document.getElementById('refund-signal-value');
                const titleDisplay = document.getElementById('refund-title-text');

                // 2. Garante a existência do input hidden para o valor limpo (para o backend)
                let paidAmountRefInput = document.getElementById('cancellation-paid-amount-ref');
                if (!paidAmountRefInput) {
                    paidAmountRefInput = document.createElement('input');
                    paidAmountRefInput.type = 'hidden';
                    paidAmountRefInput.id = 'cancellation-paid-amount-ref';
                    paidAmountRefInput.name = 'paid_amount_ref';
                    document.getElementById('cancellation-modal-content').appendChild(paidAmountRefInput);
                }

                // 3. Normalização do Valor (Trata string "50,00" ou número 50.00)
                const signalValueCleaned = cleanAndConvertForApi(paidOrSignalValue);
                const isRefundable = signalValueCleaned > 0;
                const signalFormatted = signalValueCleaned.toFixed(2).replace('.', ',');

                // Define a mensagem no modal
                const messageEl = document.getElementById('modal-message-cancel');
                if (messageEl) messageEl.textContent = message;

                // Seta o valor numérico limpo para o envio via formulário
                paidAmountRefInput.value = signalValueCleaned;

                // 4. Lógica de exibição da Área Financeira (Blindada contra CSS)
                if (isRefundable) {
                    // Remove 'hidden' e força o display via JS para garantir que apareça
                    refundArea.classList.remove('hidden');
                    refundArea.style.setProperty('display', 'block', 'important');

                    // Define título baseado no status de pagamento
                    titleDisplay.textContent = isEventPaid ? 'VALOR PAGO TOTAL/PARCIAL:' : 'HOUVE SINAL PAGO:';
                    signalDisplay.textContent = signalFormatted;

                    // Por padrão, sugere MANTER o valor (retenção)
                    const keepRadio = document.getElementById('refund-choice-no');
                    if (keepRadio) keepRadio.checked = true;
                } else {
                    // Se o valor for 0, esconde a área financeira totalmente
                    refundArea.classList.add('hidden');
                    refundArea.style.setProperty('display', 'none', 'important');
                    signalDisplay.textContent = '0,00';
                }

                // 5. Exibição do Modal com animação
                const modalContainer = document.getElementById('cancellation-modal');
                const modalContent = document.getElementById('cancellation-modal-content');

                modalContainer.classList.remove('hidden');
                modalContainer.style.setProperty('display', 'flex', 'important');

                setTimeout(() => {
                    if (modalContent) {
                        modalContent.classList.remove('opacity-0', 'scale-95');
                        modalContent.classList.add('opacity-100', 'scale-100');
                    }
                }, 10);

                const btnConfirm = document.getElementById('confirm-cancellation-btn');
                if (btnConfirm) btnConfirm.textContent = buttonText;
            }

            function closeCancellationModal() {
                // 1. Inicia a animação de saída do modal de confirmação
                const content = document.getElementById('cancellation-modal-content');
                if (content) content.classList.add('opacity-0', 'scale-95');

                // 🎯 O SEGREDO AQUI:
                // Fecha também o modal de detalhes (o que fica por baixo)
                closeEventModal();

                // 2. Esconde o container de cancelamento após a animação
                setTimeout(() => {
                    const modal = document.getElementById('cancellation-modal');
                    if (modal) {
                        modal.classList.add('hidden');
                        modal.style.setProperty('display', 'none', 'important');
                    }
                }, 300);
            }

            // ✅ ADICIONADO: Parâmetro supervisorToken para auditoria e segurança
            async function sendCancellationRequest(reservaId, method, urlBase, reason, supervisorToken = null) {
                const url = urlBase.replace(':id', reservaId);
                const refundChoice = document.querySelector('input[name="refund_choice"]:checked');
                const paidAmountRef = document.getElementById('cancellation-paid-amount-ref')?.value || 0;

                const bodyData = {
                    cancellation_reason: reason,
                    should_refund: refundChoice ? refundChoice.value === 'refund' : false,
                    paid_amount_ref: paidAmountRef,
                    supervisor_token: supervisorToken, // ⬅️ IMPORTANTE: Envia a autorização do Gestor
                    _token: csrfToken,
                    _method: method,
                };

                const submitBtn = document.getElementById('confirm-cancellation-btn');
                submitBtn.disabled = true;
                submitBtn.textContent = 'Processando...';

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(bodyData)
                    });

                    const result = await response.json();

                    // 1. Fecha o modal imediatamente para liberar a visão do alerta
                    closeCancellationModal();

                    if (response.ok && result.success) {
                        // SUCESSO
                        showDashboardMessage(result.message, 'success');
                        if (calendar) calendar.refetchEvents();
                    } else {
                        // 2. ERRO (Ex: Caixa Fechado ou Autorização Inválida)
                        if (calendar) calendar.refetchEvents();
                        showDashboardMessage(result.message || "Erro ao cancelar reserva.", 'error');
                    }
                } catch (error) {
                    console.error('Erro no cancelamento:', error);
                    closeCancellationModal();
                    if (calendar) calendar.refetchEvents();
                    showDashboardMessage("Erro de conexão.", 'error');
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Confirmar Ação';
                }
            }

            // ✅ NOVO: Adicionado isEventPaid
            const cancelarPontual = (id, isRecurrent, paidOrSignalValue, isEventPaid) => {
                const urlBase = isRecurrent ? CANCEL_PONTUAL_URL : CANCEL_PADRAO_URL;
                const method = 'PATCH';
                const confirmation = isRecurrent ?
                    "Cancelar SOMENTE ESTA reserva (exceção)? O horário será liberado pontualmente." :
                    "Cancelar esta reserva pontual (O horário será liberado e a reserva deletada).";
                const buttonText = isRecurrent ? 'Cancelar ESTE DIA' : 'Confirmar Cancelamento';

                // Passamos o signalValue (ou paidAmount) e o status de pago para o modal de cancelamento
                openCancellationModal(id, method, urlBase, confirmation, buttonText, paidOrSignalValue, isEventPaid);
            };

            // ✅ NOVO: Adicionado isEventPaid
            const cancelarSerie = (id, paidOrSignalValue, isEventPaid) => {
                const urlBase = CANCEL_SERIE_URL;
                const method = 'DELETE';
                const confirmation =
                    "⚠️ ATENÇÃO: Cancelar TODA A SÉRIE desta reserva? Todos os horários futuros serão liberados.";
                const buttonText = 'Confirmar Cancelamento de SÉRIE';

                // Passamos o signalValue (ou paidAmount) e o status de pago para o modal de cancelamento
                openCancellationModal(id, method, urlBase, confirmation, buttonText, paidOrSignalValue, isEventPaid);
            };

            // --- NO-SHOW LÓGICA (COM ESTORNO) ---

            // Função atualizada para abrir o modal de falta (No-Show)
            function openNoShowModal(reservaId, clientName, paidAmount, isPaid, price) {
                // 1. Fecha o modal de detalhes (que está por baixo)
                closeEventModal();

                const modalEl = document.getElementById('no-show-modal');
                const modalContent = document.getElementById('no-show-modal-content');
                const paidAmountEl = document.getElementById('no-show-paid-amount');
                const paidAmountRefInput = document.getElementById('paid-amount-ref');
                const refundArea = document.getElementById('no-show-refund-area');
                const refundSelect = document.getElementById('should_refund_no_show');
                const customRefundDiv = document.getElementById('customNoShowRefundDiv');
                const customRefundInput = document.getElementById('custom_no_show_refund_amount');

                // 2. Limpa o formulário e prepara os IDs
                document.getElementById('no-show-reserva-id').value = reservaId;
                document.getElementById('no-show-reason-input').value = '';
                document.getElementById('confirm-no-show-btn').textContent = 'Confirmar Falta';
                document.getElementById('confirm-no-show-btn').disabled = false;

                // 3. Tratamento do valor pago
                // Converte de "50,00" para 50.00
                const amountPaid = cleanAndConvertForApi(paidAmount);
                const paidFormatted = amountPaid.toFixed(2).replace('.', ',');

                // Seta o valor bruto no input oculto para o backend
                paidAmountRefInput.value = amountPaid;

                // 4. Lógica de exibição da área financeira
                if (amountPaid > 0) {
                    refundArea.classList.remove('hidden');
                    paidAmountEl.textContent = paidFormatted;

                    // Reseta o Select para "Reter Tudo" e esconde o campo de valor customizado
                    if (refundSelect) refundSelect.value = 'false';
                    if (customRefundDiv) customRefundDiv.classList.add('hidden');
                    if (customRefundInput) customRefundInput.value = 0;
                } else {
                    // Se o cliente não pagou nada, não faz sentido mostrar opções de estorno
                    refundArea.classList.add('hidden');
                }

                // 5. Atualiza a mensagem do modal
                document.getElementById('no-show-modal-message').innerHTML = `
        Marque a falta do cliente <strong>${clientName}</strong>.
        O sistema processará o horário e o financeiro conforme sua escolha abaixo.
    `;

                // 6. Exibe o modal com as animações de entrada
                modalEl.classList.remove('hidden');
                modalEl.style.display = 'flex'; // Garante o alinhamento central

                // Pequeno delay para a animação do Tailwind funcionar
                setTimeout(() => {
                    if (modalContent) {
                        modalContent.classList.remove('opacity-0', 'scale-95');
                        modalContent.classList.add('opacity-100', 'scale-100');
                    }
                }, 10);
            }

            function closeNoShowModal() {
                document.getElementById('no-show-modal').classList.add('hidden');
                document.getElementById('no-show-modal-content').classList.add('opacity-0', 'scale-95');
            }

            document.getElementById('no-show-form').addEventListener('submit', async function(e) {
                e.preventDefault();

                // 1. Captura de elementos e valores (Igual ao seu)
                const reasonInput = document.getElementById('no-show-reason-input');
                const reason = reasonInput.value.trim();
                const reasonErrorSpan = document.getElementById('no-show-reason-error-span');
                const paidAmountRef = document.getElementById('paid-amount-ref');
                const paidAmount = parseFloat(paidAmountRef ? paidAmountRef.value : 0) || 0;
                const shouldRefund = document.getElementById('should_refund_no_show').value === 'true';
                const refundAmountInput = document.getElementById('custom_no_show_refund_amount');
                const refundAmount = parseFloat(refundAmountInput.value) || 0;
                const valueErrorSpan = document.getElementById('no-show-error-span');

                // 2. Reset de estados de erro (Igual ao seu)
                reasonErrorSpan.classList.add('hidden');
                reasonInput.classList.remove('border-red-600', 'bg-red-50');
                valueErrorSpan.classList.add('hidden');
                refundAmountInput.classList.remove('border-red-600', 'bg-red-50');

                // 🛡️ TRAVAS DE VALIDAÇÃO (Motivo e Valor)
                if (reason.length < 5) {
                    reasonInput.focus();
                    reasonInput.classList.add('border-red-600', 'bg-red-50');
                    reasonErrorSpan.classList.remove('hidden');
                    return;
                }
                if (shouldRefund && refundAmount > paidAmount) {
                    refundAmountInput.focus();
                    refundAmountInput.classList.add('border-red-600', 'bg-red-50', 'animate-pulse');
                    valueErrorSpan.classList.remove('hidden');
                    return;
                }

                // 🔐 3. INTERCEPTAÇÃO: Pede autorização do Gestor
                requisitarAutorizacao(async (supervisorEmail = null) => {
                    const reservaId = document.getElementById('no-show-reserva-id').value;
                    const url = NO_SHOW_URL.replace(':id', reservaId);
                    const submitBtn = document.getElementById('confirm-no-show-btn');

                    // Preparação dos dados incluindo o token de quem autorizou
                    const bodyData = {
                        _token: csrfToken,
                        no_show_reason: reason,
                        notes: reason,
                        should_refund: shouldRefund,
                        refund_amount: refundAmount,
                        paid_amount: paidAmount,
                        supervisor_token: supervisorEmail // ⬅️ Envia o e-mail do gestor
                    };

                    // 4. Estado de carregamento
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Processando...';

                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify(bodyData)
                        });

                        const result = await response.json();

                        if (response.ok && result.success) {
                            closeNoShowModal();
                            showDashboardMessage(result.message || "Falta registrada com sucesso.",
                                'success');
                            if (window.calendar) window.calendar.refetchEvents();
                        } else {
                            showDashboardMessage(result.message || "Erro ao processar falta.", 'error');
                            if (window.calendar) window.calendar.refetchEvents();
                        }
                    } catch (error) {
                        console.error('Erro de Rede:', error);
                        showDashboardMessage("Erro de conexão com o servidor.", 'error');
                    } finally {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Confirmar Falta';
                    }
                });
            });

            // ✅ AJUSTADO: Event Listener com Trava de Autorização para Cancelamento
            document.getElementById('confirm-cancellation-btn').addEventListener('click', async function() {
                const reasonInput = document.getElementById('cancellation-reason-input');
                const reason = reasonInput.value.trim();

                // 1. Validação básica de preenchimento (continua igual)
                if (reason.length < 5) {
                    showDashboardMessage("Por favor, forneça o motivo do cancelamento com pelo menos 5 caracteres.",
                        'warning');
                    return;
                }

                if (currentReservaId && currentMethod && currentUrlBase) {

                    // 🔐 2. INTERCEPTAÇÃO: Pede autorização se for colaborador
                    requisitarAutorizacao(async (supervisorEmail = null) => {
                        // Esta parte só executa se o supervisor digitar a senha correta
                        // ou se o usuário logado já for Gestor/Admin.

                        console.log("Ação autorizada por:", supervisorEmail || "Usuário Master");

                        // Passamos o email do supervisor para a função de envio
                        await sendCancellationRequest(currentReservaId, currentMethod, currentUrlBase,
                            reason, supervisorEmail);
                    });

                } else {
                    console.error("Dados de cancelamento (ID, Método ou URL) não encontrados.");
                    showDashboardMessage("Erro interno: Dados da reserva para cancelamento perdidos.", 'error');
                }
            });

            // --- RENOVAÇÃO LÓGICA ---

            function closeRenewalModal() {
                document.getElementById('renewal-modal').classList.add('hidden');
            }

            function renderRenewalList() {
                const listContainer = document.getElementById('renewal-list');
                const messageBox = document.getElementById('renewal-message-box');
                listContainer.innerHTML = '';
                messageBox.classList.add('hidden');

                if (globalExpiringSeries.length === 0) {
                    listContainer.innerHTML = '<p class="text-gray-500 italic">Nenhuma série a ser renovada no momento.</p>';
                    return;
                }

                globalExpiringSeries.forEach(series => {
                    const lastDate = moment(series.last_date);
                    const suggestedNewDate = lastDate.clone().add(6, 'months');
                    const lastDateDisplay = lastDate.format('DD/MM/YYYY');
                    const suggestedNewDateDisplay = suggestedNewDate.format('DD/MM/YYYY');

                    const itemHtml = `
                    <div id="renewal-item-${series.master_id}" class="p-4 bg-yellow-50 border border-yellow-300 rounded-lg shadow-sm flex justify-between items-center transition-opacity duration-300">
                        <div>
                            <p class="font-bold text-gray-800">${series.client_name}</p>
                            <p class="text-sm text-gray-600">Horário: ${series.slot_time} | Expira em: ${lastDateDisplay}</p>
                            <p class="text-xs text-green-700 font-semibold">Sugestão: Renovar até ${suggestedNewDateDisplay} (+6 meses)</p>
                        </div>
                        <button onclick="handleRenewal(${series.master_id})"
                                class="renew-btn-${series.master_id} px-4 py-2 bg-yellow-600 text-white text-sm font-semibold rounded-lg hover:bg-yellow-700 transition duration-150">
                            Renovar
                        </button>
                    </div>
                `;
                    listContainer.insertAdjacentHTML('beforeend', itemHtml);
                });
            }

            function openRenewalModal() {
                renderRenewalList();
                document.getElementById('renewal-modal').classList.remove('hidden');
            }

            async function handleRenewal(masterReservaId) {
                // 🎯 1. BLOQUEIO PREVENTIVO OTIMIZADO
                // Reutiliza a função global: verifica status, avisa o usuário e trava o calendário se necessário
                const aberto = await isCashierOpen(moment().format('YYYY-MM-DD'));
                if (!aberto) return; // 🛑 Cancela a operação se o caixa estiver fechado

                const url = RENEW_SERIE_URL.replace(':masterReserva', masterReservaId);
                const button = document.querySelector(`.renew-btn-${masterReservaId}`);

                if (!button) return;

                // Estado de carregamento (UI Feedback)
                const originalText = button.textContent;
                button.disabled = true;
                button.textContent = 'Processando...';

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            _method: 'PATCH' // Laravel reconhece como PATCH através do spoofing
                        })
                    });

                    const result = await response.json();

                    if (response.ok && result.success) {
                        // SUCESSO: Notifica e limpa a lista
                        showDashboardMessage(result.message || `Série renovada com sucesso!`, 'success');

                        // Remove a série da memória local e atualiza o modal visualmente
                        globalExpiringSeries = globalExpiringSeries.filter(s => s.master_id !== masterReservaId);
                        renderRenewalList();

                        // Atualiza o calendário para mostrar os novos meses gerados
                        if (window.calendar) window.calendar.refetchEvents();

                        // Se limpou todas as renovações pendentes, fecha o modal após um breve delay
                        if (globalExpiringSeries.length === 0) {
                            setTimeout(() => closeRenewalModal(), 1200);
                        }

                    } else {
                        // ERRO DE REGRA DE NEGÓCIO (Ex: Conflito de horário ou Caixa fechou durante o clique)
                        console.error(result.message || `Erro ao renovar série ${masterReservaId}.`);
                        showDashboardMessage(result.message || `Falha na renovação da série.`, 'error');

                        // Sincroniza o calendário para garantir que os dados batem com o servidor
                        if (window.calendar) window.calendar.refetchEvents();
                    }
                } catch (error) {
                    console.error('Erro de Rede na Renovação:', error);
                    showDashboardMessage("Erro de conexão ao tentar renovar a série. Verifique sua internet.", 'error');
                } finally {
                    // Restaura o estado do botão para o usuário poder tentar novamente se falhou
                    if (button && button.parentNode) {
                        button.disabled = false;
                        button.textContent = originalText;
                    }
                }
            }

            // =========================================================
            // FUNÇÃO GLOBAL: Gerenciar cliques no Calendário
            // =========================================================
            window.eventClick = async function(info) {
                const event = info.event;
                const props = event.extendedProps;

                // 📅 0. Identifica a data e hora do clique
                const eventDate = moment(event.start).format('YYYY-MM-DD');
                const isToday = eventDate === moment().format('YYYY-MM-DD');
                const isPast = moment(event.start).isBefore(moment());

                // 🏟️ Captura a arena atual para validação precisa
                const currentArenaId = document.getElementById('filter_arena')?.value || '';
                const cacheKey = `${eventDate}_${currentArenaId}`;

                // 🛑 1. TRAVA DE SEGURANÇA LOCAL (Cache por Data + Arena)
                if (window.closedDatesCache && window.closedDatesCache[cacheKey] === true) {
                    const msg = isToday ?
                        "Ação bloqueada: O caixa desta arena para HOJE está fechado." :
                        `Ação bloqueada: O caixa desta arena para o dia ${moment(eventDate).format('DD/MM')} está fechado.`;
                    showDashboardMessage(msg, 'error');
                    return;
                }

                // 🎯 2. VERIFICAÇÃO EM TEMPO REAL (Sincronização com Servidor incluindo Arena)
                try {
                    const response = await fetch(
                        `{{ route('admin.payment.caixa.status') }}?date=${eventDate}&arena_id=${currentArenaId}`);
                    const statusCaixa = await response.json();

                    if (!statusCaixa.isOpen) {
                        if (!window.closedDatesCache) window.closedDatesCache = {};
                        window.closedDatesCache[cacheKey] = true;

                        showDashboardMessage(
                            `Ação Bloqueada: O caixa desta arena para o dia ${moment(eventDate).format('DD/MM')} está fechado.`,
                            'error');

                        if (window.calendar) window.calendar.render();
                        return;
                    }
                } catch (error) {
                    console.error("Erro ao verificar status do caixa:", error);
                }

                // 3. Identificação do Tipo de Slot
                const status = (props.status || '').toLowerCase();
                const isAvailable = status === 'free' ||
                    event.classNames.includes('fc-event-available') ||
                    info.el.classList.contains('fc-event-available');

                // 🛠️ VERIFICAÇÃO DE MANUTENÇÃO (Status Pink)
                if (status === 'maintenance') {
                    if (typeof window.openMaintenanceModal === "function") {
                        window.openMaintenanceModal(event.id, props.notes);
                    }
                    return;
                }

                // A. SLOT LIVRE (VERDE) -> ABRE AGENDAMENTO RÁPIDO
                if (isAvailable) {
                    const modal = document.getElementById('quick-booking-modal');
                    if (!modal) return;

                    const arenaFilter = document.getElementById('filter_arena');
                    const selectedArenaId = props.arena_id || (arenaFilter ? arenaFilter.value : '');
                    const selectedArenaName = props.arena_name || (arenaFilter ? arenaFilter.options[arenaFilter
                        .selectedIndex]?.text : 'N/A');

                    const setVal = (id, val) => {
                        const el = document.getElementById(id);
                        if (el) el.value = val;
                    };

                    // --- 🕒 TRATAMENTO RIGOROSO DE HORÁRIOS PARA O BACKEND ---
                    // 1. Início sempre existe
                    let startTimeFormatted = moment(event.start).format('HH:mm');
                    let endTimeFormatted = '';

                    // 2. Lógica de segurança para o fim (evita o "Invalid date")
                    if (event.end && moment(event.end).isValid()) {
                        endTimeFormatted = moment(event.end).format('HH:mm');
                    } else if (props.end_time) {
                        // Se o objeto event.end falhar, tentamos o end_time que veio do banco (extendedProps)
                        endTimeFormatted = props.end_time.substring(0, 5);
                    } else {
                        // Fallback final: se não houver dados, assume 1 hora de duração
                        endTimeFormatted = moment(event.start).add(1, 'hours').format('HH:mm');
                    }

                    // 3. Normalização de Meia-Noite e Datas Inválidas
                    if (endTimeFormatted === '24:00' || endTimeFormatted === 'Invalid date') {
                        endTimeFormatted = '00:00';
                    }

                    // Preenchimento dos campos ocultos e visíveis
                    setVal('quick-schedule-id', props.schedule_id || '');
                    setVal('quick-arena-id', selectedArenaId);
                    setVal('quick-date', eventDate);
                    setVal('quick-start-time', startTimeFormatted);
                    setVal('quick-end-time', endTimeFormatted);
                    setVal('reserva-id-to-update', event.id || '');

                    // Formatação de Preço
                    const priceRaw = parseFloat(props.price || 0);
                    const priceFormatted = priceRaw.toFixed(2).replace('.', ',');
                    setVal('quick-price', priceFormatted);

                    // Atualização da área de exibição do modal
                    const displayArea = document.getElementById('slot-info-display');
                    if (displayArea) {
                        displayArea.innerHTML = `
            <div class="space-y-1 border-l-4 border-green-500 pl-3">
                <p class="text-xs uppercase text-gray-500 font-bold tracking-wider">Informações da Reserva</p>
                <p><strong>Quadra:</strong> <span class="text-indigo-600">${selectedArenaName}</span></p>
                <p><strong>Data:</strong> ${moment(event.start).format('DD/MM/YYYY')}</p>
                <p><strong>Hora:</strong> ${startTimeFormatted} às ${endTimeFormatted}</p>
                <p><strong>Preço Sugerido:</strong> <span class="text-green-600 font-bold">R$ ${priceFormatted}</span></p>
            </div>`;
                    }

                    // Limpeza de campos de cliente para novo uso
                    setVal('client_name', '');
                    setVal('client_contact', '');
                    setVal('signal_value_quick', '0,00');

                    const reputationDisplay = document.getElementById('client-reputation-display');
                    if (reputationDisplay) reputationDisplay.innerHTML = '';

                    // Exibição do Modal
                    modal.classList.remove('hidden');
                    modal.style.setProperty('display', 'flex', 'important');
                    return;
                }

                // B. PENDENTE E FUTURA (LARANJA)
                if (status === 'pending' && !isPast) {
                    if (typeof openPendingActionModal === "function") openPendingActionModal(event);
                    return;
                }

                // C. RESERVA EXISTENTE (CONFIRMADA, RECORRENTE OU PAGO)
                const reservaId = event.id;
                const prefixRegex =
                    /^\s*(?:\(?(?:PAGO|FALTA|ATRASADO|CANCELADO|REJEITADA|PENDENTE|A\sVENCER\/FALTA|RECORR(?:E)?|SINAL|RESOLVIDO)\)?[\.:\s]*\s*)+/i;
                const clientNameRaw = event.title.replace(prefixRegex, '').split(' - ')[0].trim();

                const isRecurrent = props.is_recurrent ? true : false;
                const paidAmountValue = parseFloat(props.total_paid || props.retained_amount || 0);
                const totalPriceValue = parseFloat(props.final_price || props.price || 0);
                const paidAmountString = paidAmountValue.toFixed(2).replace('.', ',');
                const totalPriceString = totalPriceValue.toFixed(2).replace('.', ',');

                const isFinalized = ['completed', 'launched', 'concluida', 'pago'].includes(status);

                const contentArea = document.getElementById('modal-content');
                const actionsArea = document.getElementById('modal-actions');
                const eventModal = document.getElementById('event-modal');

                if (contentArea && actionsArea && eventModal) {
                    const modalTitle = (status === 'pending' && isPast) ? 'Detalhes de Reserva ATRASADA' :
                        'Detalhes da Reserva';
                    eventModal.querySelector('h3').textContent = modalTitle;

                    contentArea.innerHTML = `
            <div class="space-y-2">
                <p><strong>Cliente:</strong> ${clientNameRaw}</p>
                <p><strong>Contato:</strong> ${props.client_contact || 'N/A'}</p>
                <p><strong>Horário:</strong> ${moment(event.start).format('HH:mm')} - ${moment(event.end).format('HH:mm')}</p>
                <p><strong>Status:</strong> <span class="uppercase font-extrabold ${status === 'no_show' || (status === 'pending' && isPast) ? 'text-red-600' : 'text-indigo-600'}">${status === 'pending' && isPast ? 'PENDENTE (ATRASADA)' : status}</span></p>
                <p><strong>Total Pago:</strong> <span class="text-green-700 font-bold">R$ ${paidAmountString}</span> / R$ ${totalPriceString}</p>
            </div>`;

                    actionsArea.innerHTML = `
            <div class="grid grid-cols-1 gap-2">
                ${!isFinalized && status !== 'cancelled' ?
                    `<button onclick="openPaymentModal('${reservaId}')" class="w-full px-4 py-3 bg-green-600 text-white font-black rounded-lg hover:bg-green-700 transition flex items-center justify-center gap-2">
                                                                                                                                                                                                        <span>💰 IR PARA O CAIXA</span>
                                                                                                                                                                                                    </button>` : `<div class="p-2 bg-green-50 border border-green-200 text-green-700 text-center rounded-lg font-bold text-sm">✅ PAGO / FINALIZADA</div>`}

                <div class="grid grid-cols-2 gap-2 mt-1">
                    <button onclick="cancelarPontual('${reservaId}', ${isRecurrent}, '${paidAmountString}', ${isFinalized})"
                        class="px-2 py-2 bg-gray-100 text-gray-700 text-[10px] font-bold rounded-lg border border-gray-300 shadow-sm hover:bg-gray-200 transition uppercase">
                        Cancelar Dia
                    </button>
                    <button onclick="acionarManutencao('${reservaId}')"
                        class="px-2 py-2 bg-pink-100 text-pink-700 text-[10px] font-bold rounded-lg border border-pink-200 hover:bg-pink-200 transition uppercase flex items-center justify-center gap-1">
                        🛠️ Manutenção
                    </button>
                </div>

                ${!isFinalized && status !== 'no_show' ?
                    `<button onclick="openNoShowModal('${reservaId}', '${clientNameRaw.replace(/'/g, "\\'")}', '${paidAmountString}', ${isFinalized}, '${totalPriceString}')"
                                                                                                                                                                                                        class="w-full py-2 bg-red-50 text-red-700 text-xs font-bold rounded-lg border border-red-200 shadow-sm hover:bg-red-100 transition uppercase">
                                                                                                                                                                                                        FALTA (NO-SHOW)
                                                                                                                                                                                                    </button>` : ''}

                ${isRecurrent ?
                    `<button onclick="cancelarSerie('${reservaId}', '${paidAmountString}', ${isFinalized})" class="w-full mt-1 px-4 py-2 bg-red-700 text-white text-xs font-bold rounded-lg shadow-sm hover:bg-red-800 transition uppercase">
                                                                                                                                                                                                        CANCELAR SÉRIE
                                                                                                                                                                                                    </button>` : ''}

                <button onclick="closeEventModal()" class="w-full mt-2 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-semibold">
                    Fechar
                </button>
            </div>`;

                    eventModal.classList.remove('hidden');
                    eventModal.style.display = 'flex';
                }
            };
            // --- FUNÇÕES DE SUPORTE (FORA DA eventClick) ---
            window.closeQuickBookingModal = function() {
                const modal = document.getElementById('quick-booking-modal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.style.display = 'none';
                }
            };

            window.openPaymentModal = function(reservaId) {
                if (!reservaId) return alert("Erro: ID da reserva não encontrado.");
                window.location.href = `/admin/pagamentos?reserva_id=${reservaId}`;
            };

            // --- INICIALIZAÇÃO DO CALENDÁRIO ---
            window.onload = function() {
                var calendarEl = document.getElementById('calendar');
                if (!calendarEl) return;

                // --- 1. Verificações Iniciais e Periódicas ---

                // Checagem de Reservas Pendentes (Mantido 30s)
                checkPendingReservations();
                setInterval(checkPendingReservations, 30000);

                /**
                 * ✅ MELHORIA: Consolidação da Varredura do Caixa e Auto-Refresh da Agenda
                 * Verifica o caixa de hoje e força a atualização dos eventos a cada 5 minutos
                 */
                async function syncDashboardState() {
                    const hoje = moment().format('YYYY-MM-DD');
                    // 🏟️ Captura a arena atual para sincronizar o status do caixa correto
                    const arenaId = document.getElementById('filter_arena')?.value || '';
                    const cacheKey = `${hoje}_${arenaId}`;

                    try {
                        // 🚀 Adicionado arena_id na URL para não misturar Futebol com Vôlei
                        const response = await fetch(
                            `{{ route('admin.payment.caixa.status') }}?date=${hoje}&arena_id=${arenaId}`);
                        const status = await response.json();

                        if (!status.isOpen) {
                            // Só mostra a mensagem se ainda não estiver marcado como fechado no cache local para ESTA arena
                            if (!window.closedDatesCache || !window.closedDatesCache[cacheKey]) {
                                showDashboardMessage(
                                    `Atenção: O caixa desta arena para HOJE (${moment(hoje).format('DD/MM')}) está fechado.`,
                                    'warning'
                                );
                            }

                            if (!window.closedDatesCache) window.closedDatesCache = {};
                            window.closedDatesCache[cacheKey] = true;
                        } else {
                            // Caso o caixa tenha sido aberto em outra aba/dispositivo
                            if (window.closedDatesCache) window.closedDatesCache[cacheKey] = false;
                        }

                        // 🛡️ VERIFICAÇÃO DE SEGURANÇA: Só executa se o objeto calendar e as funções existirem
                        if (window.calendar && typeof window.calendar.refetchEvents === 'function') {
                            window.calendar.refetchEvents();

                            // O render() garante que a classe 'cashier-closed-locked' seja aplicada/removida no eventDidMount
                            if (typeof window.calendar.render === 'function') {
                                window.calendar.render();
                            }
                        } else {
                            console.warn(
                                "[DEBUG] Sincronização ignorada: Calendário FullCalendar ainda não está pronto.");
                        }

                    } catch (e) {
                        console.error("Erro na sincronização do Dashboard:", e);
                    }
                }

                // Executa agora e agenda para cada 5 minutos (300.000 ms)
                syncDashboardState();
                setInterval(syncDashboardState, 300000);

                // --- 2. Listeners de Formulário e Filtros ---
                const quickBookingForm = document.getElementById('quick-booking-form');
                if (quickBookingForm) {
                    quickBookingForm.addEventListener('submit', handleQuickBookingSubmit);
                }

                const arenaFilter = document.getElementById('filter_arena');
                if (arenaFilter) {
                    arenaFilter.addEventListener('change', () => {
                        if (window.calendar) window.calendar.refetchEvents();
                    });
                }

                const contactInput = document.getElementById('client_contact');
                if (contactInput) {
                    contactInput.addEventListener('input', function() {
                        this.value = this.value.replace(/\D/g, '').substring(0, 11);
                        validateClientContact(this.value);
                    });
                }

                // --- 3. Inicialização do Calendário ---
                // Chamamos isCashierOpen uma vez para garantir o estado inicial antes do FullCalendar
                isCashierOpen().then(() => {
                    console.log("Status do caixa verificado. Iniciando calendário...");

                    var calendarInstance = new FullCalendar.Calendar(calendarEl, {
                        locale: 'pt-br',
                        initialView: 'dayGridMonth',
                        height: 'auto',
                        timeZone: 'local',
                        slotMinTime: '06:00:00',
                        slotMaxTime: '24:00:00',
                        headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek,timeGridDay'
                        },
                        eventSources: [{
                                // 1. Reservas Confirmadas
                                url: CONFIRMED_API_URL,
                                method: 'GET',
                                extraParams: () => ({
                                    // Garante que o ID da arena atual seja enviado em cada busca
                                    arena_id: document.getElementById('filter_arena')?.value ||
                                        '',
                                    // Timestamp para evitar cache de requisições GET
                                    _: new Date().getTime()
                                })
                            },
                            {
                                // 2. Slots Disponíveis (Lógica Manual com Debug e Cache-Busting)
                                events: function(fetchInfo, successCallback, failureCallback) {
                                    const arenaId = document.getElementById('filter_arena')
                                        ?.value || '';

                                    // Adicionamos um parâmetro de tempo (_) para garantir que o navegador
                                    // busque dados frescos do servidor, ignorando o cache de rede.
                                    const url =
                                        `${AVAILABLE_API_URL}?start=${fetchInfo.startStr}&end=${fetchInfo.endStr}&arena_id=${arenaId}&_=${new Date().getTime()}`;

                                    console.log(
                                        `[DEBUG CALENDÁRIO] Buscando slots para Arena: ${arenaId}`
                                    );

                                    fetch(url, {
                                            cache: "no-store"
                                        }) // Instrução para o browser não cachear o JSON
                                        .then(r => {
                                            if (!r.ok) throw new Error(
                                                `HTTP error! status: ${r.status}`);
                                            return r.json();
                                        })
                                        .then(events => {
                                            console.log(
                                                `[DEBUG CALENDÁRIO] Servidor retornou ${events.length} slots brutos para Arena ${arenaId}.`
                                            );

                                            const now = moment();
                                            const filtered = events.filter(e => {
                                                const eventStart = moment(e.start);

                                                // Se não for hoje, mantém o slot visível
                                                if (!eventStart.isSame(now, 'day'))
                                                    return true;

                                                // Para HOJE: Mantém visível por meia hora após o início planejado
                                                // (Evita que slots "fujam" da tela por pequenos atrasos no relógio)
                                                const isVisible = eventStart.isAfter(now
                                                    .clone().subtract(30, 'minutes')
                                                );
                                                return isVisible;
                                            });

                                            console.log(
                                                `[DEBUG CALENDÁRIO] Após filtro de 30min: ${filtered.length} slots visíveis.`
                                            );
                                            successCallback(filtered);
                                        })
                                        .catch(err => {
                                            console.error(
                                                "[DEBUG CALENDÁRIO] Erro na requisição de slots:",
                                                err);
                                            failureCallback(err);
                                        });
                                }
                            }
                        ],
                        eventDidMount: function(info) {
                            const props = info.event.extendedProps;
                            const status = (props.status || '').toLowerCase();
                            const paymentStatus = (props.payment_status || '').toLowerCase();
                            const titleEl = info.el.querySelector('.fc-event-title');
                            const eventDate = moment(info.event.start).format('YYYY-MM-DD');

                            // 1. Esconde eventos cancelados ou rejeitados imediatamente
                            if (status === 'cancelled' || status === 'rejected') {
                                info.el.style.display = 'none';
                                return;
                            }

                            // 2. 🛡️ VERIFICAÇÃO DE BLOQUEIO POR ARENA (Independência de Quadras)
                            const currentArena = document.getElementById('filter_arena')?.value || '';
                            const cacheKey = `${eventDate}_${currentArena}`;

                            const isLocked = window.closedDatesCache && window.closedDatesCache[
                                cacheKey] === true;

                            if (isLocked) {
                                info.el.classList.add('cashier-closed-locked');
                                info.el.style.pointerEvents = 'none';
                                info.el.style.cursor = 'not-allowed';
                            } else {
                                info.el.classList.remove('cashier-closed-locked');
                                info.el.style.pointerEvents = 'auto';
                                info.el.style.cursor = 'pointer';
                            }

                            // 3. Limpa todas as classes de status para evitar sobreposição de cores
                            info.el.classList.remove(
                                'fc-event-available', 'fc-event-recurrent', 'fc-event-quick',
                                'fc-event-pending', 'fc-event-paid', 'fc-event-no-show',
                                'fc-event-maintenance'
                            );

                            // 4. 🎨 APLICAÇÃO DA LÓGICA DE CORES
                            if (['pago', 'completed', 'resolvido', 'concluida'].includes(status) ||
                                paymentStatus === 'paid') {
                                info.el.classList.add('fc-event-paid');
                            } else if (status === 'no_show') {
                                info.el.classList.add('fc-event-no-show');
                            } else if (status === 'pending') {
                                const isPast = moment(info.event.end).isBefore(moment());
                                info.el.classList.add('fc-event-pending');
                                if (isPast && titleEl) {
                                    titleEl.innerHTML =
                                        '⚠️ <span style="font-weight: 800;">EXPIRADA:</span> ' + titleEl
                                        .textContent;
                                }
                            } else if (status === 'maintenance') {
                                info.el.classList.add('fc-event-maintenance');
                                if (titleEl) titleEl.innerHTML = '🛠️ MANUTENÇÃO';
                            } else if (status === 'free' || info.event.classNames.includes(
                                    'fc-event-available')) {
                                info.el.classList.add('fc-event-available');
                                if (titleEl) {
                                    const price = parseFloat(props.price || 0).toFixed(2).replace('.',
                                        ',');
                                    titleEl.textContent = 'LIVRE - R$ ' + price;
                                }
                            } else {
                                const now = moment();
                                const eventEnd = moment(info.event.end);
                                const isPast = eventEnd.isBefore(now);

                                if (isPast && (status === 'confirmed' || status === 'confirmada')) {
                                    info.el.classList.add('fc-event-no-show');
                                    info.el.classList.add('animate-pulse-red');
                                    if (titleEl) {
                                        titleEl.innerHTML =
                                            '<span style="font-weight: 900;">⚠️ ATRASADA:</span> ' +
                                            titleEl.textContent;
                                    }
                                } else {
                                    info.el.classList.add(props.is_recurrent ? 'fc-event-recurrent' :
                                        'fc-event-quick');
                                }
                            }
                        },
                        eventClick: (info) => window.eventClick(info)
                    });

                    calendarInstance.render();
                    window.calendar = calendarInstance;

                    // --- 🚀 ESCUTADOR DE TROCA DE ARENA (Sincronização Corrigida) ---
                    const filterArenaEl = document.getElementById('filter_arena');
                    if (filterArenaEl) {
                        filterArenaEl.addEventListener('change', function() {
                            const hoje = moment().format('YYYY-MM-DD');
                            const novaArenaId = this.value;

                            console.log(
                                `[DASHBOARD] Mudando para Arena ${novaArenaId}. Resetando estado...`);

                            // 1. Limpamos o cache local para esquecer a trava da quadra anterior
                            window.closedDatesCache = {};

                            // 2. Verificamos o status do caixa especificamente para a NOVA arena
                            isCashierOpen(hoje).then(() => {
                                if (window.calendar) {
                                    // 3. Forçamos o calendário a buscar os eventos e rodar o eventDidMount novamente
                                    window.calendar.refetchEvents();
                                    console.log(
                                        "[DASHBOARD] Calendário atualizado para a arena selecionada."
                                    );
                                }
                            });
                        });
                    }
                });
            };

            /**
             * Verifica se o caixa está aberto para uma data específica.
             * @param {string} date - Data no formato YYYY-MM-DD
             * @returns {Promise<boolean>}
             */
            async function isCashierOpen(date) {
                // 1. Define a data (hoje como padrão)
                const targetDate = date || moment().format('YYYY-MM-DD');

                // 2. 🏟️ Captura qual arena está selecionada no momento do clique ou carga
                const arenaId = document.getElementById('filter_arena')?.value || '';

                if (!window.closedDatesCache) window.closedDatesCache = {};

                try {
                    // 3. Enviamos data E arena_id na URL
                    const response = await fetch(
                        `{{ route('admin.payment.caixa.status') }}?date=${targetDate}&arena_id=${arenaId}`);

                    if (!response.ok) return true;

                    const data = await response.json();
                    const isClosedNow = !data.isOpen;

                    // 4. 🧠 Cache Inteligente: Guardamos o status por Data + Arena
                    const cacheKey = `${targetDate}_${arenaId}`;
                    window.closedDatesCache[cacheKey] = isClosedNow;

                    // 5. 🛡️ AJUSTE DE SEGURANÇA: Só tenta renderizar se o FullCalendar já existir e for uma função
                    // Isso evita o erro "is not a function" durante o carregamento inicial da página
                    if (isClosedNow && window.calendar && typeof window.calendar.render === 'function') {
                        window.calendar.render();
                    }

                    return !isClosedNow;
                } catch (e) {
                    console.error("Erro ao verificar caixa:", e);
                    return true; // Em caso de erro de rede, liberamos por segurança
                }
            }
            async function checkCurrentDayCaixa() {
                const hoje = moment().format('YYYY-MM-DD');

                try {
                    const response = await fetch(`{{ route('admin.payment.caixa.status') }}?date=${hoje}`);
                    const status = await response.json();

                    if (!status.isOpen) {
                        // Mensagem exclusiva para o dia de hoje
                        showDashboardMessage(
                            `Atenção: O caixa do dia atual (${moment(hoje).format('DD/MM')}) está fechado.`, 'warning');

                        // Registra no cache para o calendar pintar de cinza imediatamente
                        if (!window.closedDatesCache) window.closedDatesCache = {};
                        window.closedDatesCache[hoje] = true;

                        if (window.calendar) window.calendar.render();
                    }
                } catch (e) {
                    console.error("Erro ao checar caixa de hoje:", e);
                }
            }

            // --- FUNÇÃO PARA MOSTRAR/ESCONDER E VALIDAR O VALOR NO MODAL DE FALTA ---
            function toggleDashboardNoShowRefundInput() {
                const shouldRefund = document.getElementById('should_refund_no_show').value === 'true';
                const customDiv = document.getElementById('customNoShowRefundDiv');
                const paidAmount = parseFloat(document.getElementById('paid-amount-ref').value) || 0;
                const inputRefund = document.getElementById('custom_no_show_refund_amount');
                const errorSpan = document.getElementById('no-show-error-span');

                if (shouldRefund) {
                    customDiv.classList.remove('hidden');
                    inputRefund.value = paidAmount.toFixed(2);

                    // Listener para validar em tempo real enquanto o usuário digita
                    inputRefund.oninput = function() {
                        const valorDigitado = parseFloat(this.value) || 0;

                        if (valorDigitado > paidAmount) {
                            // Aplica estilos de erro no campo
                            this.classList.add('border-red-600', 'text-red-600', 'bg-red-50');
                            // Mostra o aviso interno (span)
                            errorSpan.classList.remove('hidden');
                        } else {
                            // Remove estilos de erro
                            this.classList.remove('border-red-600', 'text-red-600', 'bg-red-50');
                            // Esconde o aviso interno (span)
                            errorSpan.classList.add('hidden');
                        }
                    };
                } else {
                    // Se a opção for reter tudo, limpa tudo
                    customDiv.classList.add('hidden');
                    inputRefund.value = 0;
                    errorSpan.classList.add('hidden');
                    inputRefund.classList.remove('border-red-600', 'text-red-600', 'bg-red-50');
                }
            }


            // 🛠️ FUNÇÃO SIMPLIFICADA: Redireciona para os detalhes da reserva
            window.acionarManutencao = function(reservaId) {
                // Usamos a constante SHOW_RESERVA_URL que você já tem definida no seu script
                // Ela vai transformar ':id' no número real da reserva (ex: /admin/reservas/150/show)
                const urlDetalhes = SHOW_RESERVA_URL.replace(':id', reservaId);

                // Faz o navegador navegar para essa URL
                window.location.href = urlDetalhes;
            };

            // EXPOSIÇÃO GLOBAL DE FUNÇÕES
            window.closeEventModal = closeEventModal;
            window.cancelarPontual = cancelarPontual;
            window.cancelarSerie = cancelarSerie;
            window.openRenewalModal = openRenewalModal;
            window.closeRenewalModal = closeRenewalModal;
            window.handleRenewal = handleRenewal;
            window.openPendingActionModal = openPendingActionModal;
            window.closePendingActionModal = closePendingActionModal;
            window.openNoShowModal = openNoShowModal;
            window.closeNoShowModal = closeNoShowModal;

            // ✅ ADICIONE ESTA LINHA AQUI:
            window.acionarManutencao = acionarManutencao;


            // =========================================================
            // LÓGICA DE AUTOCOMPLETE COM MOVIMENTAÇÃO DE LAYOUT (FULL)
            // =========================================================
            const autocompleteResults = document.getElementById('client-autocomplete-results');
            const nameFieldWrapper = document.getElementById('name-field-wrapper');
            const contactInputEl = document.getElementById('client_contact');
            const nameInputEl = document.getElementById('client_name');
            let debounceTimer;

            /**
             * Função auxiliar para esconder a lista e resetar a posição do layout
             */
            const resetAutocompleteLayout = () => {
                if (autocompleteResults) {
                    autocompleteResults.classList.add('hidden');
                    autocompleteResults.innerHTML = '';
                }
                if (nameFieldWrapper) {
                    nameFieldWrapper.classList.remove('autocomplete-active');
                }
            };

            /**
             * Função unificada para busca de clientes
             * @param {HTMLElement} inputElement - O campo que disparou a busca
             */
            const performClientSearch = (inputElement) => {
                const query = inputElement.value;
                const arenaId = document.getElementById('quick-arena-id')?.value || '';

                clearTimeout(debounceTimer);

                // REGRA 1: Se o campo tiver menos de 2 letras, limpa e esconde na hora
                if (query.length < 2) {
                    resetAutocompleteLayout();
                    return;
                }

                debounceTimer = setTimeout(() => {
                    fetch(`/api/clientes/search?query=${encodeURIComponent(query)}&arena_id=${arenaId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (!autocompleteResults) return;

                            // Limpa o conteúdo anterior
                            autocompleteResults.innerHTML = '';

                            // REGRA 2: Se não houver resultados, esconde a lista e o layout volta ao normal
                            if (!data || data.length === 0) {
                                resetAutocompleteLayout();
                                return;
                            }

                            // Popula a lista se houver dados
                            data.forEach(client => {
                                const div = document.createElement('div');
                                div.className =
                                    'p-3 hover:bg-indigo-50 cursor-pointer border-b border-gray-100 transition-colors';
                                const phone = client.whatsapp_contact || '';

                                div.innerHTML = `
                        <div class="font-bold text-gray-800 text-sm">${client.name}</div>
                        <div class="text-xs text-gray-500">${phone}</div>
                    `;

                                // Lógica de seleção ao clicar no nome
                                div.onclick = () => {
                                    if (nameInputEl) nameInputEl.value = client.name;

                                    if (phone && contactInputEl) {
                                        const cleanPhone = phone.replace(/\D/g, '');
                                        contactInputEl.value = cleanPhone;

                                        // Dispara a busca de reputação/VIP vinculada ao número
                                        if (typeof validateClientContact === 'function') {
                                            validateClientContact(cleanPhone);
                                        }
                                    }
                                    // Selecionou? Esconde tudo.
                                    resetAutocompleteLayout();
                                };
                                autocompleteResults.appendChild(div);
                            });

                            // REGRA 3: Mostra a lista e EMPURRA o WhatsApp para baixo
                            autocompleteResults.classList.remove('hidden');
                            if (nameFieldWrapper) {
                                nameFieldWrapper.classList.add('autocomplete-active');
                            }
                        })
                        .catch(err => {
                            console.error("Erro no autocomplete:", err);
                            resetAutocompleteLayout();
                        });
                }, 300); // Delay para fluidez
            };

            // --- Registro dos Eventos ---

            // 1. Escuta a digitação no campo de Nome
            if (nameInputEl) {
                nameInputEl.addEventListener('input', function() {
                    performClientSearch(this);
                });
            }

            // 2. REGRA DE OURO: Fecha a lista ao focar no campo de WhatsApp
            if (contactInputEl) {
                contactInputEl.addEventListener('focus', function() {
                    resetAutocompleteLayout();
                });
            }

            // 3. Fecha a lista ao clicar em qualquer lugar fora do componente
            document.addEventListener('click', function(e) {
                if (autocompleteResults &&
                    !autocompleteResults.contains(e.target) &&
                    e.target !== nameInputEl) {

                    resetAutocompleteLayout();
                }
            });
        </script>
</x-app-layout>
