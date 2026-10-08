<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- CARD 1: EMPRÉSTIMO RÁPIDO --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-3 border-b border-gray-100 dark:border-gray-800 pb-4 mb-4">
                    <div class="w-10 h-10 rounded-lg bg-primary-50 dark:bg-primary-950/40 text-primary-600 dark:text-primary-400 flex items-center justify-center font-bold">
                        <x-heroicon-o-book-open class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">1. Registrar Empréstimo</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Busca rápida do estudante + bipagem do livro</p>
                    </div>
                </div>

                {{-- SELEÇÃO DO ALUNO --}}
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                            Selecione o Estudante <span class="text-danger-500">*</span>
                        </label>
                        <select
                            wire:model.live="matricula_id"
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                        >
                            <option value="">Selecione pelo nome do aluno ou turma...</option>
                            @foreach($this->alunosOptions as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Como o aluno não possui crachá, digite para filtrar diretamente na lista.</p>
                    </div>

                    {{-- RESUMO DO ALUNO SELECIONADO --}}
                    @if($this->matricula_id)
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800/60 p-3 border border-gray-100 dark:border-gray-800">
                            <div class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2 flex items-center justify-between">
                                <span>Livros atualmente com o estudante:</span>
                                <span class="font-bold text-gray-900 dark:text-white">{{ $this->livrosEmprestadosComAluno->count() }} livro(s)</span>
                            </div>

                            @if($this->livrosEmprestadosComAluno->isEmpty())
                                <p class="text-xs text-emerald-600 dark:text-emerald-400 italic">Nenhum livro pendente de devolução.</p>
                            @else
                                <div class="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                                    @foreach($this->livrosEmprestadosComAluno as $emp)
                                        <div class="text-xs flex items-center justify-between p-1.5 rounded bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                            <span class="truncate max-w-[200px] font-medium text-gray-800 dark:text-gray-200" title="{{ $emp->livro->titulo }}">
                                                {{ $emp->livro->titulo }}
                                            </span>
                                            <span class="text-[11px] {{ $emp->data_prevista_devolucao->isPast() ? 'text-danger-600 dark:text-danger-400 font-bold' : 'text-gray-500' }}">
                                                Devolver: {{ $emp->data_prevista_devolucao->format('d/m/Y') }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- BIPAGEM DO LIVRO --}}
                    <div class="pt-2 border-t border-gray-100 dark:border-gray-800">
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                            Bipar Código do Livro (Tombo ou ISBN) <span class="text-danger-500">*</span>
                        </label>
                        <div class="flex gap-2">
                            <input
                                type="text"
                                wire:model="codigo_livro_emprestimo"
                                wire:keydown.enter.prevent="realizarEmprestimo"
                                placeholder="Bipe o código de barras ou digite..."
                                autofocus
                                class="flex-1 text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                            />
                            <button
                                type="button"
                                wire:click="realizarEmprestimo"
                                class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium text-sm rounded-lg transition-colors flex items-center gap-1.5"
                            >
                                <x-heroicon-m-plus class="w-4 h-4" />
                                Emprestar
                            </button>
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1">Ao bipar com o leitor óptico, o empréstimo é gerado automaticamente (pressione Enter).</p>
                    </div>

                    {{-- DATA PREVISTA --}}
                    <div class="pt-1">
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                            Devolução Prevista
                        </label>
                        <input
                            type="date"
                            wire:model="data_prevista_devolucao"
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        />
                    </div>
                </div>
            </div>
        </div>

        {{-- CARD 2: DEVOLUÇÃO RÁPIDA (1 BIP) --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-3 border-b border-gray-100 dark:border-gray-800 pb-4 mb-4">
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
                        <x-heroicon-o-check-circle class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">2. Devolução em 1 Bip</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Sem precisar buscar o aluno: bipe o livro recebido</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/40 rounded-lg p-4">
                        <label class="block text-xs font-bold text-emerald-900 dark:text-emerald-300 uppercase tracking-wide mb-2">
                            Bipe o Código de Barras ou ISBN do Livro Entregue
                        </label>
                        <div class="flex gap-2">
                            <input
                                type="text"
                                wire:model="codigo_livro_devolucao"
                                wire:keydown.enter.prevent="realizarDevolucao"
                                placeholder="Bipe o código do livro para devolver..."
                                class="flex-1 text-base py-2.5 rounded-lg border-emerald-300 dark:border-emerald-700 dark:bg-gray-800 dark:text-white focus:border-emerald-500 focus:ring-emerald-500 font-mono"
                            />
                            <button
                                type="button"
                                wire:click="realizarDevolucao"
                                class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm rounded-lg transition-colors flex items-center gap-1.5 shadow-sm"
                            >
                                <x-heroicon-m-check class="w-5 h-5" />
                                Devolver
                            </button>
                        </div>
                        <p class="text-xs text-emerald-700 dark:text-emerald-400 mt-2">
                            O sistema localiza automaticamente o empréstimo ativo, recoloca a obra no acervo disponível e encerra o empréstimo imediatamente.
                        </p>
                    </div>
                </div>
            </div>

            {{-- HISTÓRICO DA SESSÃO ATUAL --}}
            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
                    Movimentações desta Sessão
                </h4>

                @if(empty($historicoSessao))
                    <p class="text-xs text-gray-400 italic">Nenhuma operação realizada nesta sessão ainda.</p>
                @else
                    <div class="space-y-2 max-h-52 overflow-y-auto pr-1">
                        @foreach($historicoSessao as $op)
                            <div class="flex items-center justify-between text-xs p-2 rounded-lg border {{ $op['tipo'] === 'emprestimo' ? 'border-primary-200 bg-primary-50/50 dark:bg-primary-950/20 text-primary-900 dark:text-primary-200' : 'border-emerald-200 bg-emerald-50/50 dark:bg-emerald-950/20 text-emerald-900 dark:text-emerald-200' }}">
                                <div class="flex items-center gap-2 truncate">
                                    <span class="font-bold uppercase text-[10px] px-1.5 py-0.5 rounded {{ $op['tipo'] === 'emprestimo' ? 'bg-primary-200 text-primary-800 dark:bg-primary-800 dark:text-primary-100' : 'bg-emerald-200 text-emerald-800 dark:bg-emerald-800 dark:text-emerald-100' }}">
                                        {{ $op['tipo'] === 'emprestimo' ? 'Empréstimo' : 'Devolução' }}
                                    </span>
                                    <span class="font-semibold truncate">{{ $op['livro'] }}</span>
                                    <span class="text-gray-500 text-[11px]">({{ $op['aluno'] }})</span>
                                </div>
                                <span class="text-[11px] font-mono opacity-70">{{ $op['data'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

    </div>
</x-filament-panels::page>
