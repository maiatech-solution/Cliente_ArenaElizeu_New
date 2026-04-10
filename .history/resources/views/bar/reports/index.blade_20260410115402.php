<x-bar-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

        {{-- 🍺 HEADER DO RELATÓRIO --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-4">
            <div>
                <h1 class="text-4xl font-black text-white uppercase tracking-tighter italic">
                    Relatórios <span class="text-orange-600">Financeiros</span>
                </h1>
                <p class="text-gray-500 font-medium italic">Análise de faturamento e desempenho do bar.</p>
            </div>

            {{-- 📅 Filtro de Período (Mês/Ano) --}}
            <div
                class="bg-gray-900/40 border-2 border-gray-800 p-2 rounded-3xl flex items-center gap-4 px-6 shadow-inner">
                <form action="{{ route('bar.reports.index') }}" method="GET" class="flex items-center gap-4">
                    <div class="text-right">
                        <label
                            class="text-[9px] font-black text-gray-500 uppercase tracking-widest block leading-none">Período</label>
                        <input type="month" name="mes_referencia"
                            value="{{ request('mes_referencia', now()->format('Y-m')) }}" onchange="this.form.submit()"
                            class="bg-transparent border-none p-0 font-black text-orange-500 uppercase text-sm focus:ring-0 cursor-pointer">
                    </div>
                    <span class="text-gray-700 text-xl">|</span>
                    <span class="text-2xl">📅</span>
                </form>
            </div>
        </div>

        {{-- 📊 INDICADORES DE PERFORMANCE (KPIs) --}}
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-12">

            {{-- Faturamento Consolidado --}}
            <div
                class="p-8 rounded-[2.5rem] bg-orange-600/10 border-2 border-orange-600/20 shadow-lg shadow-orange-600/5 relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 opacity-5 group-hover:scale-110 transition-transform duration-500">
                    <span class="text-9xl text-orange-500 font-black">💰</span>
                </div>
                <h4 class="text-[10px] font-black text-orange-500 uppercase tracking-widest mb-2">Faturamento Total</h4>
                <p class="text-4xl font-black text-white italic tracking-tighter">
                    R$ {{ number_format($faturamentoMensal, 2, ',', '.') }}
                </p>
                <p
                    class="text-[9px] text-gray-500 font-bold uppercase italic mt-4 px-3 py-1 bg-black/40 rounded-full inline-block">
                    Mesas + Balcão
                </p>
            </div>

            {{-- Total em Cortesias (Vouchers) --}}
            <div
                class="p-8 rounded-[2.5rem] bg-indigo-600/10 border-2 border-indigo-600/20 shadow-lg relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 opacity-5 group-hover:scale-110 transition-transform duration-500">
                    <span class="text-9xl text-indigo-500 font-black">🎟️</span>
                </div>
                <h4 class="text-[10px] font-black text-indigo-500 uppercase tracking-widest mb-2">Total Cortesias</h4>
                <p class="text-4xl font-black text-white italic tracking-tighter">
                    R$ {{ number_format($totalVouchersMes, 2, ',', '.') }}
                </p>
                <p
                    class="text-[9px] text-gray-500 font-bold uppercase italic mt-4 px-3 py-1 bg-black/40 rounded-full inline-block">
                    Vouchers Emitidos
                </p>
            </div>

            {{-- Volume de Itens --}}
            <div
                class="p-8 rounded-[2.5rem] bg-gray-900/40 border-2 border-gray-800 hover:border-gray-700 transition-all">
                <h4 class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2">Volume de Saída</h4>
                <p class="text-4xl font-black text-white italic tracking-tighter">
                    {{ number_format($totalItensMes, 0, ',', '.') }}
                </p>
                <p class="text-[9px] text-gray-600 font-bold uppercase italic mt-4">Unidades Vendidas</p>
            </div>

            {{-- Ticket Médio --}}
            <div
                class="p-8 rounded-[2.5rem] bg-gray-900/40 border-2 border-gray-800 hover:border-blue-900/30 transition-all">
                <h4 class="text-[10px] font-black text-blue-500 uppercase tracking-widest mb-2">Ticket Médio</h4>
                <p class="text-4xl font-black text-blue-500 italic tracking-tighter">
                    R$ {{ number_format($ticketMedio, 2, ',', '.') }}
                </p>
                <p class="text-[9px] text-gray-600 font-bold uppercase italic mt-4">Média p/ Transação</p>
            </div>

            {{-- Sangrias --}}
            <div
                class="p-8 rounded-[2.5rem] bg-gray-900/40 border-2 border-gray-800 hover:border-red-900/30 transition-all">
                <h4 class="text-[10px] font-black text-red-500 uppercase tracking-widest mb-2">Total Sangrias</h4>
                <p class="text-4xl font-black text-red-500 italic tracking-tighter">
                    R$ {{ number_format($totalSangriasMes, 2, ',', '.') }}
                </p>
                <p class="text-[9px] text-gray-600 font-bold uppercase italic mt-4">Saídas de Caixa</p>
            </div>
        </div>

        {{-- 🏷️ DIVISOR DE SEÇÃO --}}
        <div class="flex items-center gap-4 mb-8">
            <h3 class="text-[10px] font-black text-gray-600 uppercase tracking-[0.4em] italic">Módulos Analíticos</h3>
            <div class="flex-1 h-px bg-gray-800"></div>
        </div>

        {{-- 🚀 MENU DE RELATÓRIOS ESPECÍFICOS --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">

            {{-- Ranking de Produtos (Contém Margem de Lucro) --}}
            <a href="{{ route('bar.reports.products') }}"
                class="group p-6 bg-gray-900/40 border-2 border-gray-800 rounded-[2rem] hover:border-orange-600 transition-all flex flex-col gap-4 shadow-xl">
                <div
                    class="w-12 h-12 bg-orange-600/10 rounded-xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
                    🔥
                </div>
                <div>
                    <h4 class="text-white font-black uppercase italic text-md leading-tight">Produtos & Margem</h4>
                    <p class="text-[9px] text-gray-500 font-bold uppercase tracking-widest">Mais vendidos e
                        Lucratividade</p>
                </div>
            </a>

            {{-- Auditoria de Caixas (Fechamento) --}}
            <a href="{{ route('bar.reports.cashier') }}"
                class="group p-6 bg-gray-900/40 border-2 border-gray-800 rounded-[2rem] hover:border-emerald-500 transition-all flex flex-col gap-4 shadow-xl">
                <div
                    class="w-12 h-12 bg-emerald-600/10 rounded-xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
                    📟
                </div>
                <div>
                    <h4 class="text-white font-black uppercase italic text-md leading-tight">Fechamento de Caixa</h4>
                    <p class="text-[9px] text-gray-500 font-bold uppercase tracking-widest">Conferência de Turnos</p>
                </div>
            </a>

            {{-- Controle de Estoque --}}
            <a href="{{ route('bar.reports.movements') }}"
                class="group p-6 bg-gray-900/40 border-2 border-gray-800 rounded-[2rem] hover:border-blue-500 transition-all flex flex-col gap-4 shadow-xl">
                <div
                    class="w-12 h-12 bg-blue-600/10 rounded-xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
                    📦
                </div>
                <div>
                    <h4 class="text-white font-black uppercase italic text-md leading-tight">Gestão de Estoque</h4>
                    <p class="text-[9px] text-gray-500 font-bold uppercase tracking-widest">Entradas e Saídas</p>
                </div>
            </a>

            {{-- Meios de Pagamento --}}
            <a href="{{ route('bar.reports.payments') }}"
                class="group p-6 bg-gray-900/40 border-2 border-gray-800 rounded-[2rem] hover:border-indigo-500 transition-all flex flex-col gap-4 shadow-xl">
                <div
                    class="w-12 h-12 bg-indigo-600/10 rounded-xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
                    💳
                </div>
                <div>
                    <h4 class="text-white font-black uppercase italic text-md leading-tight">Meios de Pagamento</h4>
                    <p class="text-[9px] text-gray-500 font-bold uppercase tracking-widest">Pix, Cartão e Dinheiro</p>
                </div>
            </a>

            {{-- Resumo Diário --}}
            <a href="{{ route('bar.reports.daily') }}"
                class="group p-6 bg-gray-900/40 border-2 border-gray-800 rounded-[2rem] hover:border-amber-500 transition-all flex flex-col gap-4 shadow-xl">
                <div
                    class="w-12 h-12 bg-amber-600/10 rounded-xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
                    🗓️
                </div>
                <div>
                    <h4 class="text-white font-black uppercase italic text-md leading-tight">Resumo Diário</h4>
                    <p class="text-[9px] text-gray-500 font-bold uppercase tracking-widest">Faturamento Dia a Dia</p>
                </div>
            </a>

            {{-- Descontos e Cancelamentos --}}
            <a href="{{ route('bar.reports.cancelations') }}"
                class="group p-6 bg-gray-900/40 border-2 border-gray-800 rounded-[2rem] hover:border-red-600 transition-all flex flex-col gap-4 shadow-xl">
                <div
                    class="w-12 h-12 bg-red-600/10 rounded-xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
                    🚫
                </div>
                <div>
                    <h4 class="text-white font-black uppercase italic text-md leading-tight">Cancelamentos</h4>
                    <p class="text-[9px] text-gray-500 font-bold uppercase tracking-widest">Estornos e Descontos</p>
                </div>
            </a>

            {{-- Desempenho por Operador --}}
            <a href="{{ route('bar.reports.operators') }}"
                class="group p-6 bg-gray-900/40 border-2 border-gray-800 rounded-[2rem] hover:border-orange-500 transition-all flex flex-col gap-4 shadow-xl">
                <div
                    class="w-12 h-12 bg-orange-600/10 rounded-xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
                    👤
                </div>
                <div>
                    <h4 class="text-white font-black uppercase italic text-md leading-tight">Vendas por Operador</h4>
                    <p class="text-[9px] text-gray-500 font-bold uppercase tracking-widest">Ranking e Produtividade</p>
                </div>
            </a>

            {{-- Relatório de Cortesias (Vouchers) --}}
<a href="{{ route('bar.reports.vouchers') }}"
    class="group p-8 bg-gray-900 rounded-[2.5rem] border border-gray-800 hover:border-orange-500 transition-all shadow-2xl relative overflow-hidden">
    <div class="relative z-10">
        <div class="w-14 h-14 bg-orange-500/10 rounded-2xl flex items-center justify-center text-orange-500 mb-6 group-hover:scale-110 transition-transform">
            <span class="text-2xl">🎁</span>
        </div>
        <h3 class="text-white font-black uppercase italic text-lg leading-tight">Cortesias &<br>Vouchers</h3>
        <p class="text-[10px] text-gray-500 font-bold uppercase mt-2 tracking-widest italic">Auditoria de Brindes</p>
    </div>
    <div class="absolute -right-4 -bottom-4 text-7xl opacity-[0.02] group-hover:opacity-[0.05] transition-opacity">
        🎁
    </div>
</a>

        </div>
    </div>
</x-bar-layout>
