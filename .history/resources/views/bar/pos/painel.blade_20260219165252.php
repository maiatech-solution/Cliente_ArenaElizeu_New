<x-bar-layout>
    <div class="min-h-[80vh] flex flex-col items-center justify-center px-6">

        {{-- Cabeçalho --}}
        <div class="text-center mb-16">
            <div class="inline-block p-4 bg-indigo-600/10 border border-indigo-600/20 rounded-3xl mb-6 shadow-lg">
                <span class="text-4xl">🛒</span>
            </div>
            <h1 class="text-5xl font-black text-white uppercase tracking-tighter italic leading-none">
                Módulo <span class="text-indigo-500">PDV</span>
            </h1>
            <p class="text-gray-500 font-bold text-[10px] uppercase tracking-[0.3em] mt-4">
                Vendas Diretas e Atendimento de Balcão
            </p>
        </div>

        {{-- Grid de Seleção --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 w-full max-w-4xl">

            {{-- CARD 1: Interface de Venda --}}
            <a href="{{ route('bar.pdv') }}"
                class="group relative bg-gray-900 border border-gray-800 p-10 rounded-[3rem] hover:border-indigo-500 transition-all duration-500 shadow-2xl hover:-translate-y-2">
                <div class="relative z-10">
                    <div class="w-16 h-16 bg-indigo-600/10 border border-indigo-600/20 rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:bg-indigo-600 group-hover:text-white transition-all shadow-lg">
                        ⚡
                    </div>
                    <h2 class="text-3xl font-black text-white uppercase italic tracking-tighter mb-2">Fazer Venda</h2>
                    <p class="text-gray-500 text-sm font-bold leading-relaxed">
                        Lançar produtos, processar pagamentos e finalizar vendas rápidas no balcão.
                    </p>
                </div>
            </a>

            {{-- CARD 2: Histórico de Vendas --}}
            <a href="{{ route('bar.vendas.pdv.index') }}"
                class="group relative bg-gray-900 border border-gray-800 p-10 rounded-[3rem] hover:border-gray-400 transition-all duration-500 shadow-2xl hover:-translate-y-2">
                <div class="relative z-10">
                    <div class="w-16 h-16 bg-gray-800 border border-gray-700 rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:bg-white group-hover:text-black transition-all shadow-lg">
                        📋
                    </div>
                    <h2 class="text-3xl font-black text-white uppercase italic tracking-tighter mb-2">Histórico</h2>
                    <p class="text-gray-500 text-sm font-bold leading-relaxed">
                        Consultar vendas realizadas, verificar status e realizar estornos autorizados.
                    </p>
                </div>
            </a>

        </div>

        {{-- Voltar --}}
        <a href="{{ route('bar.dashboard') }}" class="mt-16 text-gray-600 hover:text-white font-black uppercase text-[10px] tracking-widest transition-colors flex items-center gap-2">
            <span>←</span> VOLTAR AO DASHBOARD
        </a>
    </div>
</x-bar-layout>
