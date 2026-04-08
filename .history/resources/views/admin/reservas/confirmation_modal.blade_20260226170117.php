<div id="confirmReservationModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden overflow-y-auto z-50">
    <div class="flex items-center justify-center min-h-screen">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 m-4">
            <h3 class="text-xl font-semibold text-gray-900 mb-4 border-b pb-2">
                Confirmar Agendamento Pendente
            </h3>

            <form id="confirmReservationForm" method="POST" action="">
                @csrf
                @method('PATCH')

                <input type="hidden" name="reserva_id" id="modal_reserva_id">

                <div class="space-y-4">
                    {{-- Informações da Reserva --}}
                    <div class="bg-gray-50 p-3 rounded-md space-y-1 border-l-4 border-indigo-500">
                        <p class="text-sm text-gray-700">
                            Cliente: <strong id="modal_client_name" class="text-indigo-600"></strong>
                        </p>
                        <p class="text-sm text-gray-700">
                            Horário: <strong id="modal_reservation_time"></strong>
                        </p>
                        {{-- 🏟️ IDENTIFICAÇÃO DA QUADRA --}}
                        <p class="text-sm text-gray-700">
                            Quadra: <strong id="modal_arena_name" class="text-indigo-700"></strong>
                        </p>
                        <p class="text-sm text-gray-700 font-bold">
                            Preço Base: <span id="modal_reservation_price" class="text-green-600"></span>
                        </p>
                    </div>

                    {{-- Campo de Sinal --}}
                    <div>
                        <label for="signal_value" class="block text-sm font-medium text-gray-700">
                            Valor do Sinal Recebido (R$)
                        </label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <input type="number" step="0.01" min="0" name="signal_value" id="signal_value"
                                required
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2 border"
                                placeholder="0.00">
                        </div>
                    </div>

                    {{-- Método de Pagamento --}}
                    <div>
                        <label for="payment_method" class="block text-sm font-medium text-gray-700">
                            Forma de Pagamento do Sinal
                        </label>
                        <select name="payment_method" id="payment_method" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2 border">
                            <option value="pix" selected>Pix</option>
                            <option value="dinheiro">Dinheiro</option>
                            <option value="cartao">Cardão de Crédito/Débito</option>
                            <option value="transferencia">Transferência Bancária</option>
                        </select>
                    </div>

                    {{-- Checkbox Recorrência --}}
                    <div class="space-y-2">
                        <div id="recurrent-warning-msg"></div>

                        <div class="flex items-start p-2 bg-indigo-50 rounded-md">
                            <input type="hidden" name="is_recurrent" value="0">
                            <div class="flex items-center h-5">
                                <input id="is_recurrent" name="is_recurrent" type="checkbox" value="1"
                                    class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="is_recurrent" class="font-medium text-gray-700 cursor-pointer">
                                    Agendar como Série Recorrente
                                </label>
                                <p class="text-gray-500 text-xs">Marque para criar cópias automáticas (Mensalista) nesta
                                    quadra por 6 meses.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeConfirmModal()"
                        class="py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none">
                        Cancelar
                    </button>
                    <button type="submit"
                        class="py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        Confirmar Reserva
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    async function openConfirmModal(reservaId, clientName, reservationTime, price, arenaId, horaPura) {
        // 1. Referências dos Elementos
        const modal = document.getElementById('confirmReservationModal');
        const checkRecurrent = document.getElementById('is_recurrent');
        const warningArea = document.getElementById('recurrent-warning-msg');

        // 2. Exibe o Modal e Reseta o estado do Checkbox e Alertas
        modal.classList.remove('hidden');
        if (warningArea) warningArea.innerHTML = '';
        if (checkRecurrent) {
            checkRecurrent.disabled = false;
            checkRecurrent.checked = false;
        }

        // 3. Preenchimento de IDs e Dados de Texto
        document.getElementById('modal_reserva_id').value = reservaId;
        document.getElementById('modal_client_name').textContent = clientName;
        document.getElementById('modal_reservation_time').textContent = reservationTime;

        // 4. Formatação de Preço e Configuração do Formulário
        document.getElementById('modal_reservation_price').textContent = 'R$ ' + parseFloat(price).toFixed(2)
            .replace('.', ',');
        document.getElementById('signal_value').value = parseFloat(price).toFixed(2);
        document.getElementById('payment_method').value = 'pix';

        const form = document.getElementById('confirmReservationForm');
        form.action = `/admin/reservas/confirmar/${reservaId}`;

        // --- 🛡️ TRAVA DE SEGURANÇA: VALIDAÇÃO DE CONFLITO FUTURO ---
        try {
            // Buscamos os eventos confirmados da arena para checar o futuro
            const response = await fetch(`/api/reservas/confirmadas?arena_id=${arenaId}`);
            const eventos = await response.json();

            // Extraímos o dia da semana da data (0-6)
            const dataParte = reservationTime.split(' às ')[0];
            const [dia, mes, ano] = dataParte.split('/');
            const diaSemanaPendente = new Date(ano, mes - 1, dia).getDay();

            // Comparamos o horário (HH:mm)
            const horaInicioPendente = horaPura.substring(0, 5);

            // Verifica se existe algum mensalista no futuro neste mesmo dia e hora
            const temConflitoFuturo = eventos.some(event => {
                const dataEvento = new Date(event.start);
                return event.extendedProps.is_recurrent === true &&
                    event.extendedProps.is_fixed === false &&
                    dataEvento.getDay() === diaSemanaPendente &&
                    moment(event.start).format('HH:mm') === horaInicioPendente &&
                    dataEvento > new Date(); // Apenas datas futuras
            });

            if (temConflitoFuturo && checkRecurrent) {
                checkRecurrent.disabled = true;
                if (warningArea) {
                    warningArea.innerHTML = `
                    <div class="p-3 mb-3 bg-amber-100 border-l-4 border-amber-500 text-amber-700 text-xs shadow-sm">
                        <strong>⚠️ BLOQUEIO DE RECORRÊNCIA:</strong><br>
                        Já existe um mensalista neste horário nas próximas semanas.
                        Confirmação permitida apenas como <strong>PONTUAL</strong>.
                    </div>`;
                }
            }
        } catch (error) {
            console.error("Erro ao validar disponibilidade futura:", error);
        }
    }

    function closeConfirmModal() {
        document.getElementById('confirmReservationModal').classList.add('hidden');
    }
</script>
