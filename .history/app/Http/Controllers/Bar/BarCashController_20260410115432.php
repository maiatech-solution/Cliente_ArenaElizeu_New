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
        $isAdmin = in_array($user->role, ['admin', 'gestor']);

        // 1. Busca a sessão aberta específica DESTE usuário (Lógica atual)
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

        // Define a sessão atual para exibição dos cards
        $currentSession = ($openSession && Carbon::parse($openSession->opened_at)->format('Y-m-d') == $date)
            ? $openSession
            : BarCashSession::whereDate('opened_at', $date)
            ->when(!$isAdmin, function ($query) use ($user) {
                return $query->where('user_id', $user->id);
            })
            ->latest()
            ->first();

        // 🔍 NOVIDADE: Busca sessões FECHADAS para a lista de Auditoria/Reabertura
        // Se for admin, vê todos os fechados do dia. Se for operador, vê apenas os seus fechados.
        $sessionsClosed = BarCashSession::with('user')
            ->where('status', 'closed')
            ->whereDate('opened_at', $date)
            ->when(!$isAdmin, function ($query) use ($user) {
                return $query->where('user_id', $user->id);
            })
            ->orderBy('closed_at', 'desc')
            ->get();

        $mesasAbertasCount = BarTable::where('status', 'occupied')->count();

        // Inicialização de variáveis para segurança da View
        $movements = collect();
        $totalBruto = 0;
        $faturamentoDigital = 0;
        $dinheiroGeral = 0;
        $sangrias = 0;
        $reforcos = 0;
        $totalEstornado = 0;
        $vendasDinheiro = 0;

        if ($currentSession) {
            // 2. BUSCA TODAS AS MOVIMENTAÇÕES (Fonte única da verdade)
            $allMovements = BarCashMovement::with(['user', 'barOrder.table'])
                ->where('bar_cash_session_id', $currentSession->id)
                ->get();

            // Histórico visual (Colaborador vê o dele, Gestor vê a sessão)
            $movements = (!$isAdmin)
                ? $allMovements->where('user_id', $user->id)
                : $allMovements;

            $movements = $movements->sortByDesc('created_at');

            // 3. MOVIMENTAÇÕES GERAIS
            $reforcos = $allMovements->where('type', 'reforco')->sum('amount');
            $sangrias = $allMovements->where('type', 'sangria')->sum('amount');

            // 🎯 MATEMÁTICA LÍQUIDA
            $vendasDinheiro = $allMovements->where('type', 'venda')->filter(function ($m) {
                return strtolower($m->payment_method) === 'dinheiro';
            })->sum('amount');

            $estornosDinheiro = $allMovements->where('type', 'estorno')->filter(function ($m) {
                return strtolower($m->payment_method) === 'dinheiro';
            })->sum('amount');

            $metodosDigitais = ['pix', 'credito', 'debito', 'cartao', 'misto', 'crédito', 'débito', 'voucher'];

            $vendasDigital = $allMovements->where('type', 'venda')->filter(function ($m) use ($metodosDigitais) {
                return in_array(strtolower($m->payment_method), $metodosDigitais);
            })->sum('amount');

            $estornosDigital = $allMovements->where('type', 'estorno')->filter(function ($m) use ($metodosDigitais) {
                return in_array(strtolower($m->payment_method), $metodosDigitais);
            })->sum('amount');

            // 📊 CÁLCULOS DOS CARDS
            $dinheiroGeral = ($currentSession->opening_balance + $vendasDinheiro + $reforcos) - ($sangrias + $estornosDinheiro);
            $faturamentoDigital = $vendasDigital - $estornosDigital;
            $totalBruto = ($vendasDinheiro - $estornosDinheiro) + $faturamentoDigital;
            $totalEstornado = $estornosDinheiro + $estornosDigital;
        }

        return view('bar.cash.index', compact(
            'currentSession',
            'openSession',
            'sessionsClosed', // 👈 Variável enviada para a lista de reabertura
            'movements',
            'date',
            'dinheiroGeral',
            'reforcos',
            'sangrias',
            'vendasDinheiro',
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
        // 0. 🛡️ VALIDAÇÃO DO SUPERVISOR (Mantido)
        if (!$request->supervisor_email || !$request->supervisor_password) {
            return back()->with('error', '⚠️ Autorização necessária: As credenciais do supervisor não foram detectadas.');
        }

        $supervisor = \App\Models\User::where('email', $request->supervisor_email)->first();

        if (!$supervisor || !\Illuminate\Support\Facades\Hash::check($request->supervisor_password, $supervisor->password)) {
            return back()->with('error', '⚠️ Falha na autorização: E-mail ou Senha do supervisor incorretos.');
        }

        // 1. 🎯 BUSCA SESSÃO ATIVA ESPECÍFICA DO USUÁRIO LOGADO
        // Mudamos o 'first()' genérico por um filtro de 'user_id'
        $session = BarCashSession::where('status', 'open')
            ->where('user_id', auth()->id()) // 👈 O SEGREDO ESTÁ AQUI
            ->first();

        if (!$session) {
            return back()->with('error', 'Erro: Você não possui uma sessão de caixa aberta no seu usuário.');
        }

        // 🛡️ TRAVA DE DATA (Mantido)
        $dataAbertura = \Carbon\Carbon::parse($session->opened_at)->format('Y-m-d');
        $hoje = date('Y-m-d');

        if ($dataAbertura !== $hoje) {
            return back()->with('error', '⚠️ BLOQUEIO DE MOVIMENTAÇÃO: Este caixa pertence ao dia anterior (' . \Carbon\Carbon::parse($session->opened_at)->format('d/m') . '). Encerre este turno antes de realizar sangrias ou reforços hoje.');
        }

        // 2. VALIDAÇÃO TÉCNICA (Mantido)
        $request->validate([
            'type' => 'required|in:sangria,reforco',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
        ]);

        return DB::transaction(function () use ($request, $session, $supervisor) {
            // 3. CRIA A MOVIMENTAÇÃO (Agora vinculada à $session correta)
            BarCashMovement::create([
                'bar_cash_session_id' => $session->id,
                'user_id' => auth()->id(),
                'type' => $request->type,
                'payment_method' => 'dinheiro',
                'amount' => $request->amount,
                'description' => $request->description . " (Autorizado por: {$supervisor->name})",
            ]);

            // 4. ATUALIZA SALDO ESPERADO NA GAVETA (Na sessão correta)
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
     * 🔓 REABRIR TURNO (AUDITORIA DE GESTOR)
     * Melhorado para garantir isolamento e prevenir erros de ID
     */
    public function reopen(Request $request)
    {
        // 1. Validação de Credenciais do Gestor (Segurança de Auditoria)
        if (!$request->supervisor_email || !$request->supervisor_password) {
            return back()->with('error', '⚠️ Autorização necessária: Credenciais não detectadas.');
        }

        $supervisor = \App\Models\User::where('email', $request->supervisor_email)->first();

        if (!$supervisor || !\Illuminate\Support\Facades\Hash::check($request->supervisor_password, $supervisor->password)) {
            return back()->with('error', '⚠️ Falha na autorização: Senha do gestor incorreta.');
        }

        // Garante que quem está autorizando tem permissão de gestão
        if (!in_array($supervisor->role, ['admin', 'gestor'])) {
            return back()->with('error', '⚠️ Acesso negado: Somente gestores podem reabrir turnos.');
        }

        // 2. Busca a Sessão e valida a existência
        $session = BarCashSession::where('id', $request->session_id)->first();

        if (!$session) {
            return back()->with('error', '⚠️ Erro: Sessão de caixa não encontrada.');
        }

        // Trava extra: Só pode reabrir o que já foi fechado
        if ($session->status !== 'closed') {
            return back()->with('error', '⚠️ Atenção: Esta sessão já consta como aberta no sistema.');
        }

        // 3. Trava de Segurança Multi-Caixa:
        // Verifica se o DONO da sessão (ex: Renato) já não abriu um novo caixa hoje.
        $hasOtherOpen = BarCashSession::where('user_id', $session->user_id)
            ->where('status', 'open')
            ->exists();

        if ($hasOtherOpen) {
            $operador = $session->user->name ?? 'operador';
            return back()->with('error', "⚠️ Bloqueio: O colaborador {$operador} já possui um novo turno aberto. Encerre o turno atual dele antes de reabrir este antigo.");
        }

        // 4. Executa a Reabertura (Resetando os dados de fechamento)
        return DB::transaction(function () use ($session, $supervisor) {
            $session->update([
                'status' => 'open',
                'closed_at' => null,
                'closing_balance' => null, // Limpa o valor contado anteriormente
                // 'total_vendas_sistema' não é zerado para manter o rastro do que já foi vendido
            ]);

            // 5. Log de Auditoria (Marcando QUEM reabriu o caixa no histórico)
            // Usamos 'reforco' com valor 0 para que apareça na lista sem alterar o saldo
            BarCashMovement::create([
                'bar_cash_session_id' => $session->id,
                'user_id' => auth()->id(), // Quem clicou no botão (Gestor logado)
                'type' => 'reforco',
                'amount' => 0.00,
                'description' => "🔓 TURNO REABERTO | AUTORIZADO POR: {$supervisor->name}",
                'payment_method' => 'SISTEMA'
            ]);

            return back()->with('success', "O turno de {$session->user->name} foi reaberto e está pronto para novos lançamentos.");
        });
    }

    /**
     * Abrir o Caixa (Ajustado para permitir Abertura Direta ou por Supervisor)
     */
    public function open(Request $request)
    {
        // 1. Definição do Autorizador
        $autorizadorNome = auth()->user()->name;

        // 🛡️ Lógica de bypass: Só valida supervisor se NÃO for "AUTO"
        if ($request->supervisor_password !== 'AUTO') {

            if (!$request->supervisor_email || !$request->supervisor_password) {
                return back()->with('error', '⚠️ Autorização necessária: As credenciais do supervisor não foram detectadas.');
            }

            $supervisor = \App\Models\User::where('email', $request->supervisor_email)->first();

            if (!$supervisor || !Hash::check($request->supervisor_password, $supervisor->password)) {
                return back()->with('error', '⚠️ Falha na autorização: E-mail ou Senha do supervisor incorretos.');
            }

            if (!in_array($supervisor->role, ['admin', 'gestor'])) {
                return back()->with('error', '⚠️ Acesso negado! Somente um Gestor ou Admin pode autorizar.');
            }

            $autorizadorNome = $supervisor->name;
        }

        // 2. Validação técnica
        $request->validate([
            'opening_balance' => 'required|numeric|min:0',
        ]);

        // 🛡️ Verifica se o usuário logado já tem um caixa aberto
        $exists = BarCashSession::where('status', 'open')
            ->where('user_id', auth()->id())
            ->exists();

        if ($exists) {
            return back()->with('error', '⚠️ Você já possui um turno de caixa aberto no seu usuário!');
        }

        // 🚀 Cria a sessão
        BarCashSession::create([
            'user_id' => auth()->id(),
            'opening_balance' => $request->opening_balance,
            'expected_balance' => $request->opening_balance,
            'status' => 'open',
            'opened_at' => now(),
            'notes' => "Abertura realizada por: {$autorizadorNome}"
        ]);

        return redirect()->route('bar.cash.index')->with('success', 'Turno iniciado com sucesso! Boas vendas.');
    }

    /**
     * Fechar o Caixa Individual (Sincronizado com Modal Unificado: Gaveta + PIX)
     */
    public function close(Request $request)
    {
        // 1. Definição do Autorizador (Bypass para 'AUTO')
        $autorizadorNome = auth()->user()->name;

        if ($request->supervisor_password !== 'AUTO') {
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

            $autorizadorNome = $supervisor->name;
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

        // 🚀 AJUSTE AQUI: Busca os dados da arena direto da tabela que vimos nos seus LOGS
        $company = \Illuminate\Support\Facades\DB::table('company_infos')->first();

        // 3. Processamento do Fechamento
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $autorizadorNome, $company) {
            $session = \App\Models\Bar\BarCashSession::where('status', 'open')
                ->where('user_id', auth()->id())
                ->lockForUpdate()
                ->first();

            if (!$session) {
                return back()->with('error', 'Erro: Você não tem um turno aberto.');
            }

            $movs = \App\Models\Bar\BarCashMovement::where('bar_cash_session_id', $session->id)->get();
            $metodosDigitais = ['pix', 'debito', 'credito', 'cartao', 'misto', 'crédito', 'débito'];

            $vCash = $movs->where('type', 'venda')->filter(fn($m) => strtolower($m->payment_method) === 'dinheiro')->sum('amount');
            $ref = $movs->where('type', 'reforco')->sum('amount');
            $san = $movs->where('type', 'sangria')->sum('amount');
            $estCash = $movs->where('type', 'estorno')->filter(fn($m) => strtolower($m->payment_method) === 'dinheiro')->sum('amount');

            $vDigital = $movs->where('type', 'venda')->filter(fn($m) => in_array(strtolower($m->payment_method), $metodosDigitais))->sum('amount');
            $estDigital = $movs->where('type', 'estorno')->filter(fn($m) => in_array(strtolower($m->payment_method), $metodosDigitais))->sum('amount');

            $totalEsperadoUnificado = ($session->opening_balance + $vCash + $vDigital + $ref) - ($san + $estCash + $estDigital);
            $faturamentoTotalLiquido = ($vCash - $estCash) + ($vDigital - $estDigital);

            $actual = $request->actual_balance;
            $difference = $actual - $totalEsperadoUnificado;

            $session->update([
                'closing_balance' => $actual,
                'expected_balance' => $totalEsperadoUnificado,
                'total_vendas_sistema' => $faturamentoTotalLiquido,
                'status' => 'closed',
                'closed_at' => now(),
                'notes' => ($request->notes ? $request->notes . " | " : "") . "Fechamento realizado por: {$autorizadorNome}"
            ]);

            $msg = "Turno encerrado!";
            if (abs($difference) < 0.01) {
                $msg .= " ✅ O Caixa bateu perfeitamente!";
            } else {
                $msg .= ($difference < 0)
                    ? " ⚠️ Diferença detectada! Falta: R$ " . number_format(abs($difference), 2, ',', '.')
                    : " ⚠️ Diferença detectada! Sobrou: R$ " . number_format(abs($difference), 2, ',', '.');
            }

            // 🚀 RETORNO AJUSTADO COM O NOME DA ARENA
            return redirect()->route('bar.cash.index')->with([
                'success' => $msg,
                'arena_nome_print' => $company->nome_fantasia ?? 'ARENA',
                'opening_balance_print' => number_format($session->opening_balance, 2, ',', '.'),
                'vendas_dinheiro_print' => number_format($vCash - $estCash, 2, ',', '.'),
                'vendas_digital_print' => number_format($vDigital - $estDigital, 2, ',', '.'),
                'reforcos_print' => number_format($ref, 2, ',', '.'),
                'sangrias_print' => number_format($san, 2, ',', '.'),
                'total_esperado_print' => number_format($totalEsperadoUnificado, 2, ',', '.'),
                'valor_informado_print' => number_format($actual, 2, ',', '.'),
                'diferenca_print' => number_format($difference, 2, ',', '.')
            ]);
        });
    }
}
