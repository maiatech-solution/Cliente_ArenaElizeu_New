<?php

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Models\Bar\BarCashSession;
use App\Models\Bar\BarCashMovement;
use App\Models\Bar\BarTable;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BarCashController extends Controller
{


    /**
     * Tela Principal do Caixa (Ajustada para Multi-Caixa por Usuário)
     */
    public function index(Request $request)
    {
        $date = $request->get('date', date('Y-m-d'));
        $user = auth()->user();

        // 1. Busca a sessão aberta específica DESTE usuário
        $openSession = BarCashSession::where('status', 'open')
            ->where('user_id', $user->id)
            ->first();

        $caixaVencido = false;
        if ($openSession) {
            $dataAbertura = Carbon::parse($openSession->opened_at)->startOfDay();
            $hoje = Carbon::today();
            if ($dataAbertura->lt($hoje)) {
                $caixaVencido = true;
            }
        }

        $currentSession = ($openSession && Carbon::parse($openSession->opened_at)->format('Y-m-d') == $date)
            ? $openSession
            : BarCashSession::whereDate('opened_at', $date)
            ->when(!in_array($user->role, ['admin', 'gestor']), function ($query) use ($user) {
                return $query->where('user_id', $user->id);
            })
            ->latest()
            ->first();

        $mesasAbertasCount = BarTable::where('status', 'occupied')->count();

        // Inicialização de variáveis para segurança da View
        $movements = collect();
        $totalBruto = 0;
        $faturamentoDigital = 0;
        $dinheiroGeral = 0;
        $sangrias = 0;
        $reforcos = 0;
        $totalEstornado = 0;
        $vendasDinheiro = 0; // Inicializada para evitar erro na view se não houver sessão

        if ($currentSession) {
            // 2. BUSCA TODAS AS MOVIMENTAÇÕES (Fonte única da verdade)
            $allMovements = BarCashMovement::with(['user', 'barOrder.table'])
                ->where('bar_cash_session_id', $currentSession->id)
                ->get();

            // Histórico visual (Colaborador vê o dele, Gestor vê a sessão)
            $movements = (!in_array($user->role, ['admin', 'gestor']))
                ? $allMovements->where('user_id', $user->id)
                : $allMovements;
            $movements = $movements->sortByDesc('created_at');

            // 3. MOVIMENTAÇÕES GERAIS (Limpas)
            $reforcos = $allMovements->where('type', 'reforco')->sum('amount');
            $sangrias = $allMovements->where('type', 'sangria')->sum('amount');

            // 🎯 MATEMÁTICA LÍQUIDA

            // A. DINHEIRO (Vendas brutas em espécie)
            $vendasDinheiro = $allMovements->where('type', 'venda')->filter(function ($m) {
                return strtolower($m->payment_method) === 'dinheiro';
            })->sum('amount');

            $estornosDinheiro = $allMovements->where('type', 'estorno')->filter(function ($m) {
                return strtolower($m->payment_method) === 'dinheiro';
            })->sum('amount');

            // B. DIGITAL (PIX, Cartões, etc)
            $metodosDigitais = ['pix', 'credito', 'debito', 'cartao', 'misto', 'crédito', 'débito'];

            $vendasDigital = $allMovements->where('type', 'venda')->filter(function ($m) use ($metodosDigitais) {
                return in_array(strtolower($m->payment_method), $metodosDigitais);
            })->sum('amount');

            $estornosDigital = $allMovements->where('type', 'estorno')->filter(function ($m) use ($metodosDigitais) {
                return in_array(strtolower($m->payment_method), $metodosDigitais);
            })->sum('amount');

            // 📊 CÁLCULOS DOS CARDS VISUAIS E MODAL

            // 1. Dinheiro na Gaveta (O que o Marlon deve ter em mãos)
            // Fórmula: (Saldo Inicial + Vendas Dinheiro + Reforços) - (Sangrias + Estornos Dinheiro)
            $dinheiroGeral = ($currentSession->opening_balance + $vendasDinheiro + $reforcos) - ($sangrias + $estornosDinheiro);

            // 2. Faturamento Digital Líquido (Vendas Digital - Estornos Digital)
            $faturamentoDigital = $vendasDigital - $estornosDigital;

            // 3. FATURAMENTO TOTAL LÍQUIDO (Total de vendas reais do turno)
            $totalBruto = ($vendasDinheiro - $estornosDinheiro) + $faturamentoDigital;

            // 4. Total de Estornos (Informativo)
            $totalEstornado = $estornosDinheiro + $estornosDigital;
        }

        return view('bar.cash.index', compact(
            'currentSession',
            'openSession',
            'movements',
            'date',
            'dinheiroGeral',
            'reforcos',
            'sangrias',
            'vendasDinheiro', // 👈 Importante: Enviado para o detalhamento do modal
            'faturamentoDigital',
            'totalBruto',
            'totalEstornado',
            'mesasAbertasCount',
            'caixaVencido'
        ));
    }

    /**
     * 💸 PROCESSAR MOVIMENTAÇÕES (Sangria e Reforço) com trava de data
     */
    public function storeMovement(Request $request)
    {
        // 0. 🛡️ VALIDAÇÃO DO SUPERVISOR
        if (!$request->supervisor_email || !$request->supervisor_password) {
            return back()->with('error', '⚠️ Autorização necessária: As credenciais do supervisor não foram detectadas.');
        }

        $supervisor = \App\Models\User::where('email', $request->supervisor_email)->first();

        if (!$supervisor || !\Illuminate\Support\Facades\Hash::check($request->supervisor_password, $supervisor->password)) {
            return back()->with('error', '⚠️ Falha na autorização: E-mail ou Senha do supervisor incorretos.');
        }

        // 1. BUSCA SESSÃO ATIVA
        $session = BarCashSession::where('status', 'open')->first();

        if (!$session) {
            return back()->with('error', 'Erro: Não há nenhuma sessão de caixa aberta.');
        }

        // 🛡️ NOVA TRAVA DE DATA: Impede movimentar valores em caixas de dias anteriores
        $dataAbertura = \Carbon\Carbon::parse($session->opened_at)->format('Y-m-d');
        $hoje = date('Y-m-d');

        if ($dataAbertura !== $hoje) {
            return back()->with('error', '⚠️ BLOQUEIO DE MOVIMENTAÇÃO: Este caixa pertence ao dia anterior (' . \Carbon\Carbon::parse($session->opened_at)->format('d/m') . '). Encerre este turno antes de realizar sangrias ou reforços hoje.');
        }

        // 2. VALIDAÇÃO TÉCNICA
        $request->validate([
            'type' => 'required|in:sangria,reforco',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
        ]);

        return DB::transaction(function () use ($request, $session, $supervisor) {
            // 3. CRIA A MOVIMENTAÇÃO
            BarCashMovement::create([
                'bar_cash_session_id' => $session->id,
                'user_id' => auth()->id(),
                'type' => $request->type,
                'payment_method' => 'dinheiro',
                'amount' => $request->amount,
                'description' => $request->description . " (Autorizado por: {$supervisor->name})",
            ]);

            // 4. ATUALIZA SALDO ESPERADO NA GAVETA
            if ($request->type === 'reforco') {
                $session->increment('expected_balance', $request->amount);
                $msg = "Reforço realizado com sucesso!";
            } else {
                $session->decrement('expected_balance', $request->amount);
                $msg = "Sangria realizada com sucesso!";
            }

            return back()->with('success', $msg);
        });
    }

    /**
     * Reabrir um caixa fechado (Ação de Gerência)
     */
    public function reopen($id)
    {
        $hasOpen = BarCashSession::where('status', 'open')->exists();
        if ($hasOpen) {
            return back()->with('error', 'Já existe um caixa aberto! Feche o atual antes de reabrir este.');
        }

        $session = BarCashSession::findOrFail($id);

        $session->update([
            'status' => 'open',
            'closed_at' => null,
            'closing_balance' => null,
        ]);

        return back()->with('success', 'Caixa reaberto com sucesso!');
    }

    /**
     * Abrir o Caixa (Ajustado para permitir Multi-Caixa por Usuário)
     */
    public function open(Request $request)
    {
        // 1. Validação do Supervisor (Mantido conforme sua regra de segurança)
        if (!$request->supervisor_email || !$request->supervisor_password) {
            return back()->with('error', '⚠️ Autorização necessária: As credenciais do supervisor não foram detectadas.');
        }

        $supervisor = \App\Models\User::where('email', $request->supervisor_email)->first();

        if (!$supervisor || !Hash::check($request->supervisor_password, $supervisor->password)) {
            return back()->with('error', '⚠️ Falha na autorização: E-mail ou Senha do supervisor incorretos.');
        }

        if (!in_array($supervisor->role, ['admin', 'gestor'])) {
            return back()->with('error', '⚠️ Acesso negado! Somente um Gestor ou Admin pode autorizar a abertura de caixa.');
        }

        $request->validate([
            'opening_balance' => 'required|numeric|min:0',
        ]);

        // 🛡️ MUDANÇA CRÍTICA: Verifica apenas se ESTE USUÁRIO já tem um caixa aberto
        $exists = BarCashSession::where('status', 'open')
            ->where('user_id', auth()->id()) // Trava individual
            ->exists();

        if ($exists) {
            return back()->with('error', '⚠️ Você já possui um turno de caixa aberto no seu usuário!');
        }

        // 🚀 Cria a sessão vinculada ao usuário logado
        BarCashSession::create([
            'user_id' => auth()->id(),
            'opening_balance' => $request->opening_balance,
            'expected_balance' => $request->opening_balance,
            'status' => 'open',
            'opened_at' => now(),
            'notes' => "Abertura autorizada por: {$supervisor->name}"
        ]);

        return redirect()->route('bar.cash.index')->with('success', 'Turno iniciado com sucesso! Boas vendas.');
    }

    /**
     * Fechar o Caixa Individual (Sincronizado com Modal Unificado: Gaveta + PIX)
     */
    public function close(Request $request)
    {
        // 1. Validação do Supervisor (Mantido)
        if (!$request->supervisor_email || !$request->supervisor_password) {
            return back()->with('error', '⚠️ Autorização necessária.');
        }

        $supervisor = \App\Models\User::where('email', $request->supervisor_email)->first();

        if (!$supervisor || !\Illuminate\Support\Facades\Hash::check($request->supervisor_password, $supervisor->password)) {
            return back()->with('error', '⚠️ Falha na autorização do supervisor.');
        }

        if (!in_array($supervisor->role, ['admin', 'gestor'])) {
            return back()->with('error', '⚠️ Acesso negado: Somente Gestores podem fechar caixas.');
        }

        // 2. Trava de Mesas Abertas
        $mesasAbertas = \App\Models\Bar\BarTable::where('status', 'occupied')->count();
        if ($mesasAbertas > 0) {
            return back()->with('error', "⚠️ Bloqueio: Existem mesas ocupadas no sistema.");
        }

        $request->validate([
            'actual_balance' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500'
        ]);

        // 3. Processamento do Fechamento em Transação
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $supervisor) {
            $session = \App\Models\Bar\BarCashSession::where('status', 'open')
                ->where('user_id', auth()->id())
                ->lockForUpdate()
                ->first();

            if (!$session) {
                return back()->with('error', 'Erro: Você não tem um turno aberto.');
            }

            // 🎯 BUSCA TODAS AS MOVIMENTAÇÕES DO TURNO
            $movs = \App\Models\Bar\BarCashMovement::where('bar_cash_session_id', $session->id)->get();
            $metodosDigitais = ['pix', 'debito', 'credito', 'cartao', 'misto', 'crédito', 'débito'];

            // --- CÁLCULO DINHEIRO (GAVETA) ---
            $vCash = $movs->where('type', 'venda')->filter(fn($m) => strtolower($m->payment_method) === 'dinheiro')->sum('amount');
            $ref = $movs->where('type', 'reforco')->sum('amount');
            $san = $movs->where('type', 'sangria')->sum('amount');
            $estCash = $movs->where('type', 'estorno')->filter(fn($m) => strtolower($m->payment_method) === 'dinheiro')->sum('amount');

            // --- CÁLCULO DIGITAL (BANCO) ---
            $vDigital = $movs->where('type', 'venda')->filter(fn($m) => in_array(strtolower($m->payment_method), $metodosDigitais))->sum('amount');
            $estDigital = $movs->where('type', 'estorno')->filter(fn($m) => in_array(strtolower($m->payment_method), $metodosDigitais))->sum('amount');

            // 📊 CÁLCULO DO ESPERADO UNIFICADO (O que o sistema diz que tem que ter no TOTAL)
            // Esta é a soma que o seu modal exibe e que o operador informa (Dinheiro + PIX)
            $totalEsperadoUnificado = ($session->opening_balance + $vCash + $vDigital + $ref) - ($san + $estCash + $estDigital);

            // 📊 FATURAMENTO LÍQUIDO DO DIA (Apenas para registro informativo)
            $faturamentoTotalLiquido = ($vCash - $estCash) + ($vDigital - $estDigital);

            // ⚖️ AUDITORIA (Comparação do informado contra o TOTAL esperado)
            $actual = $request->actual_balance;
            $difference = $actual - $totalEsperadoUnificado;

            // 💾 ATUALIZAR SESSÃO NO BANCO
            $session->update([
                'closing_balance' => $actual,
                'expected_balance' => $totalEsperadoUnificado, // Salva o total geral para a auditoria bater 0.00
                'total_vendas_sistema' => $faturamentoTotalLiquido, // Placar real de vendas
                'status' => 'closed',
                'closed_at' => now(),
                'notes' => ($request->notes ? $request->notes . " | " : "") . "Fechamento autorizado por: {$supervisor->name}"
            ]);

            // Mensagem de feedback
            $msg = "Turno encerrado!";
            if (abs($difference) < 0.01) {
                $msg .= " ✅ O Caixa (Dinheiro + Digital) bateu perfeitamente!";
            } else {
                $msg .= ($difference < 0)
                    ? " ⚠️ Diferença detectada! Falta: R$ " . number_format(abs($difference), 2, ',', '.')
                    : " ⚠️ Diferença detectada! Sobrou: R$ " . number_format(abs($difference), 2, ',', '.');
            }

            return redirect()->route('bar.cash.index')->with('success', $msg);
        });
    }
}
