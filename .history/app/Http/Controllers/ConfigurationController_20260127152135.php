<?php

namespace App\Http\Controllers;

use App\Models\ArenaConfiguration;
use App\Models\Arena;
use App\Models\Reserva;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ConfigurationController extends Controller
{
    /**
     * Portal de Funcionamento: Mostra os cards para seleção da quadra.
     */
    public function funcionamento()
    {
        $arenas = Arena::all();
        return view('admin.quadras.funcionamento', compact('arenas'));
    }

    /**
     * Formulário de Configuração: Edição dos slots de uma quadra específica.
     */
    public function index(Request $request, $arena_id = null)
    {
        $arenas = Arena::all();
        $targetId = $arena_id ?? $request->query('arena_id');
        
        if (!$targetId) {
            return redirect()->route('admin.config.funcionamento');
        }

        $currentArena = Arena::find($targetId);

        if (!$currentArena) {
            return redirect()->route('admin.arenas.index')->with('warning', 'Arena não encontrada.');
        }

        $configs = ArenaConfiguration::where('arena_id', $currentArena->id)->get()->keyBy('day_of_week');

        $dayConfigurations = [];
        $dayNames = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];

        foreach ($dayNames as $dayOfWeek => $dayName) {
            $config = $configs->get($dayOfWeek);
            $dayConfigurations[$dayOfWeek] = ($config && !empty($config->config_data)) ? $config->config_data : [];
        }

        $fixedReservas = Reserva::where('arena_id', $currentArena->id)
            ->where('date', '>=', Carbon::today()->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit(50)
            ->get();

        return view('admin.config.index', [
            'arenas' => $arenas,
            'currentArena' => $currentArena,
            'dayConfigurations' => $dayConfigurations,
            'fixedReservas' => $fixedReservas,
        ]);
    }

    /**
     * Salvar Configuração: Processa o formulário e persiste as regras por Arena.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'arena_id' => 'required|exists:arenas,id',
            'day_status' => 'nullable|array',
            'configs' => 'nullable|array',
            'recurrent_months' => 'nullable|integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $arenaId = $request->input('arena_id');
        $dayStatus = $request->input('day_status', []);
        $configsByDay = $request->input('configs', []);

        DB::beginTransaction();
        try {
            // Percorre os 7 dias da semana (0 a 6)
            for ($i = 0; $i <= 6; $i++) {
                $slotsForDay = $configsByDay[$i] ?? [];
                
                $activeSlots = collect($slotsForDay)->filter(function ($slot) {
                    return isset($slot['is_active']) && (bool)$slot['is_active'] && !empty($slot['start_time']);
                })->map(function ($slot) {
                    $slot['start_time'] = Carbon::parse($slot['start_time'])->format('H:i:s');
                    $slot['end_time'] = Carbon::parse($slot['end_time'])->format('H:i:s');
                    return $slot;
                })->values()->toArray();

                $isDayActive = isset($dayStatus[$i]);
                $finalIsActive = $isDayActive && !empty($activeSlots);

                // Salva a regra na tabela de configurações
                ArenaConfiguration::updateOrCreate(
                    ['day_of_week' => $i, 'arena_id' => $arenaId],
                    ['is_active' => $finalIsActive, 'config_data' => $finalIsActive ? $activeSlots : []]
                );
            }

            DB::commit();
            
            // Após salvar a regra, gera os slots físicos na tabela 'reservas'
            return $this->generateFixedReservas($request);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro no store de config: " . $e->getMessage());
            return redirect()->back()->with('error', 'Erro ao salvar configurações: ' . $e->getMessage());
        }
    }

    /**
     * Gerador de Slots Fisiológicos: Fatias os blocos de horário em registros de 1 hora.
     */
    public function generateFixedReservas(Request $request)
    {
        $arenaId = $request->input('arena_id');
        $today = Carbon::today();
        $recurrentMonths = (int) $request->input('recurrent_months', 6);
        $endDate = $today->copy()->addMonths($recurrentMonths);

        DB::beginTransaction();
        try {
            // 🛑 Limpa apenas slots LIVRES futuros da arena para evitar duplicidade
            Reserva::where('is_fixed', true)
                ->where('arena_id', $arenaId)
                ->where('date', '>=', $today->toDateString())
                ->where('status', Reserva::STATUS_FREE)
                ->delete();

            $activeConfigs = ArenaConfiguration::where('arena_id', $arenaId)
                ->where('is_active', true)
                ->get();

            $reservasToInsert = [];

            // Loop dia a dia pela janela de meses definida
            for ($date = $today->copy(); $date->lessThan($endDate); $date->addDay()) {
                $dayOfWeek = $date->dayOfWeek;
                $config = $activeConfigs->firstWhere('day_of_week', $dayOfWeek);

                if ($config && !empty($config->config_data)) {
                    foreach ($config->config_data as $slot) {
                        
                        $startTime = Carbon::parse($slot['start_time']);
                        $endTime = Carbon::parse($slot['end_time']);

                        // Ajuste para virada de dia (meia-noite)
                        if ($endTime->lte($startTime)) {
                            $endTime->addDay();
                        }

                        $current = $startTime->copy();

                        // 🎯 LÓGICA DE FATIAMENTO EM INTERVALOS DE 1 HORA
                        while ($current->lt($endTime)) {
                            $next = $current->copy()->addHour();

                            // Garante que o slot não ultrapasse o limite final do bloco
                            if ($next->gt($endTime)) break;

                            $reservasToInsert[] = [
                                'arena_id'       => $arenaId,
                                'date'           => $date->toDateString(),
                                'day_of_week'    => $dayOfWeek,
                                'start_time'     => $current->format('H:i:s'),
                                'end_time'       => $next->format('H:i:s'),
                                'price'          => $slot['default_price'],
                                'status'         => Reserva::STATUS_FREE,
                                'is_fixed'       => true,
                                'client_name'    => 'Slot Livre',
                                'client_contact' => 'N/A',
                                'is_recurrent'   => false,
                                'created_at'     => now(),
                                'updated_at'     => now(),
                            ];

                            $current->addHour();
                        }
                    }
                }
            }

            // Inserção em lotes para performance
            if (!empty($reservasToInsert)) {
                foreach (array_chunk($reservasToInsert, 500) as $chunk) {
                    Reserva::insert($chunk);
                }
            }

            DB::commit();
            return redirect()->route('admin.config.index', ['arena_id' => $arenaId])
                             ->with('success', 'Configuração aplicada e grade de horários (1h) gerada com sucesso!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro na geração de slots: " . $e->getMessage());
            return redirect()->back()->with('error', 'Erro ao processar a geração de horários: ' . $e->getMessage());
        }
    }
}