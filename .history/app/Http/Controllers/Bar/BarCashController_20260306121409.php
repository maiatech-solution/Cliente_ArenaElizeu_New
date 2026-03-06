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
     * Tela Principal do Caixa
     */
    public function index(Request $request)
    {
        $date = $request->get('date', date('Y-m-d'));
        $user = auth()->user();

        $openSession = BarCashSession::where('status', 'open')->first();

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
            : BarCashSession::whereDate('opened_at', $date)->latest()->first();

        $mesasAbertasCount = BarTable::where('status', 'occupied')->count();

        $movements = collect();
        $vendasDinheiro = 0;
        $vendasDigital = 0;
        $reforcos = 0;
        $sangriasDinheiro = 0;
        $totalEstornado = 0; // ⬅️ Nova variável

        if ($currentSession) {
            $allMovements = BarCashMovement::with(['user', 'barOrder.table'])
                ->where('bar_cash_session_id', $currentSession->id)
                ->get();

            $movements = (!in_array($user->role, ['admin', 'gestor']))
                ? $allMovements->where('user_id', $user->id)
                : $allMovements;

            $movements = $movements->sortByDesc('created_at');

            // 1. Faturamento Bruto (Apenas o que está pago/confirmado)
            $faturamentoBrutoMesas = \App\Models\Bar\BarOrder::where('bar_cash_session_id', $currentSession->id)
                ->where('status', 'paid')
                ->sum('total_value');

            $faturamentoBrutoPDV = \App\Models\Bar\BarSale::where('bar_cash_session_id', $currentSession->id)
                ->where('status', 'pago')
                ->sum('total_value');

            // 2. Movimentações por Tipo
            $vendasDinheiro = $allMovements->where('type', 'venda')->where('payment_method', 'dinheiro')->sum('amount');

            $vendasDigital = $allMovements->where('type', 'venda')
                ->whereIn('payment_method', ['pix', 'credito', 'debito', 'cartao', 'misto'])
                ->sum('amount');

            $reforcos = $allMovements->where('type', 'reforco')->sum('amount');
            $sangriasDinheiro = $allMovements->where('type', 'sangria')->sum('amount');

            // 🎯 A CORREÇÃO: Captura os estornos da sessão
            $totalEstornado = $allMovements->where('type', 'estorno')->sum('amount');

            // 3. Cálculos Finais (Líquidos)
            // Subtraímos o estorno do bruto para o dashboard refletir o valor real
            $totalBruto = ($faturamentoBrutoMesas + $faturamentoBrutoPDV) - $totalEstornado;

            $faturamentoDigital = $vendasDigital; // Se o estorno foi digital, ele deve ser tratado aqui se necessário
            $saldoInicialSessao = $currentSession->opening_balance;

            // 💰 Dinheiro esperado na gaveta (Considerando que o estorno retira dinheiro do caixa)
            // Se o estorno for sempre em dinheiro ou se você devolve o valor, ele abate aqui:
            $dinheiroGeral = $saldoInicialSessao + $vendasDinheiro + $reforcos - $sangriasDinheiro - $totalEstornado;

            $sangrias = $allMovements->where('type', 'sangria')->sum('amount');
        } else {
            $totalBruto = 0;
            $faturamentoDigital = 0;
            $dinheiroGeral = 0;
            $sangrias = 0;
            $totalEstornado = 0;
        }

        return view('bar.cash.index', compact(
            'currentSession',
            'openSession',
            'movements',
            'date',
            'dinheiroGeral',
            'reforcos',
            'sangrias',
            'faturamentoDigital',
            'totalBruto',
            'totalEstornado', // ⬅️ Enviando para a view
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
     * Abrir o Caixa (Início de Turno com Autorização de Supervisor)
     */
    public function open(Request $request)
    {
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

        $exists = BarCashSession::where('status', 'open')->exists();
        if ($exists) {
            return back()->with('error', 'Já existe um caixa aberto no sistema!');
        }

        BarCashSession::create([
            'user_id' => auth()->id(),
            'opening_balance' => $request->opening_balance,
            'expected_balance' => $request->opening_balance,
            'status' => 'open',
            'opened_at' => now(),
            'notes' => "Abertura autorizada por: {$supervisor->name}"
        ]);

        return redirect()->route('bar.cash.index')->with('success', 'Turno iniciado com sucesso!');
    }

    /**
     * Fechar o Caixa com Auditoria (Versão Corrigida para considerar Estornos)
     */
    public function close(Request $request)
    {
        // 1. Validação das credenciais do supervisor
        if (!$request->supervisor_email || !$request->supervisor_password) {
            return back()->with('error', '⚠️ Autorização necessária: As credenciais do supervisor não foram enviadas.');
        }

        $supervisor = \App\Models\User::where('email', $request->supervisor_email)->first();

        if (!$supervisor || !Hash::check($request->supervisor_password, $supervisor->password)) {
            return back()->with('error', '⚠️ Falha na autorização: E-mail ou Senha do supervisor incorretos.');
        }

        if (!in_array($supervisor->role, ['admin', 'gestor'])) {
            return back()->with('error', '⚠️ Acesso negado: Somente um Gestor ou Admin pode autorizar o fechamento.');
        }

        // 2. Trava de Mesas Abertas
        $mesasAbertas = BarTable::where('status', 'occupied')->get();
        if ($mesasAbertas->count() > 0) {
            $numeros = $mesasAbertas->pluck('identifier')->implode(', ');
            return back()->with('error', "⚠️ Bloqueio: Existem mesas ocupadas ({$numeros}). Feche todas as comandas antes de encerrar o turno.");
        }

        $request->validate([
            'actual_balance' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500'
        ]);

        // 3. Processamento do Fechamento
        return DB::transaction(function () use ($request, $supervisor) {
            $session = BarCashSession::where('status', 'open')->lockForUpdate()->first();

            if (!$session) {
                return back()->with('error', 'Erro: Não há nenhuma sessão de caixa aberta.');
            }

            // 🎯 RECALCULO EM TEMPO REAL (Auditado)
            $vendasMesas = \App\Models\Bar\BarOrder::where('bar_cash_session_id', $session->id)
                ->where('status', 'paid')
                ->sum('total_value');

            $vendasPDV = \App\Models\Bar\BarSale::where('bar_cash_session_id', $session->id)
                ->where('status', 'pago')
                ->sum('total_value');

            // Movimentações manuais (Reforço, Sangria e Estornos)
            $movimentacoes = BarCashMovement::where('bar_cash_session_id', $session->id)->get();
            $reforcos = $movimentacoes->where('type', 'reforco')->sum('amount');
            $sangrias = $movimentacoes->where('type', 'sangria')->sum('amount');

            // 🛡️ CORREÇÃO CRÍTICA: Captura o valor que saiu do caixa como estorno/devolução
            $estornos = $movimentacoes->where('type', 'estorno')->sum('amount');

            // 🧮 Cálculo do esperado líquido: (Fundo + Vendas + Reforços) - (Sangrias + Estornos)
            $totalEsperadoSistema = ($session->opening_balance + $vendasMesas + $vendasPDV + $reforcos) - ($sangrias + $estornos);

            $actual = $request->actual_balance;
            $difference = $actual - $totalEsperadoSistema;

            $session->update([
                'closing_balance' => $actual,
                'expected_balance' => $totalEsperadoSistema,
                'status' => 'closed',
                'closed_at' => now(),
                'notes' => ($request->notes ? $request->notes . " | " : "") . "Fechamento autorizado por: {$supervisor->name}"
            ]);

            $msg = "Turno encerrado com sucesso!";

            // Verificação de precisão (tolerância de centavos)
            if (abs($difference) < 0.01) {
                $msg .= " Caixa bateu perfeitamente!";
            } elseif ($difference < 0) {
                $msg .= " Quebra detectada: R$ " . number_format(abs($difference), 2, ',', '.');
            } else {
                $msg .= " Sobra detectada: R$ " . number_format($difference, 2, ',', '.');
            }

            return redirect()->route('bar.cash.index')->with('success', $msg);
        });
    }
}
