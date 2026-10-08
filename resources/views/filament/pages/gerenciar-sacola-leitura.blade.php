<x-filament-panels::page>
    <div class="space-y-6">

        {{-- CABEÇALHO COM RESUMO DA SACOLA --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-primary-100 text-primary-800 dark:bg-primary-950 dark:text-primary-300">
                        {{ $record->codigo }}
                    </span>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $record->titulo }}</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $record->status?->getColor() ? 'bg-'.$record->status->getColor().'-100 text-'.$record->status->getColor().'-800 dark:bg-'.$record->status->getColor().'-950 dark:text-'.$record->status->getColor().'-200' : 'bg-primary-100 text-primary-800' }}">
                        {{ $record->status?->getLabel() ?? 'Em Circulação' }}
                    </span>
                </div>
                <div class="text-xs text-gray-500 mt-1 flex flex-wrap items-center gap-3">
                    <span><strong>Turma:</strong> {{ $record->turma?->nome ?? 'Geral' }}</span>
                    <span>•</span>
                    <span><strong>Professor(a):</strong> {{ $record->responsavel?->nome ?? 'Não informado' }}</span>
                    <span>•</span>
                    <span><strong>Retirada:</strong> {{ $record->data_retirada?->format('d/m/Y') }}</span>
                    <span>•</span>
                    <span class="{{ $record->isAtrasada() ? 'text-danger-600 font-bold' : '' }}">
                        <strong>Previsão:</strong> {{ $record->data_prevista_devolucao?->format('d/m/Y') }}
                    </span>
                </div>
            </div>

            @if($record->observacoes)
                <div class="text-xs text-gray-600 dark:text-gray-300 max-w-sm bg-gray-50 dark:bg-gray-800 p-2.5 rounded-lg border border-gray-100 dark:border-gray-700">
                    <span class="font-semibold">Notas:</span> {{ $record->observacoes }}
                </div>
            @endif
        </div>

        {{-- CARDS DE INDICADORES DA SACOLA --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-xs">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total de Livros na Sacola</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $record->totalLivros() }}</div>
                <div class="text-[11px] text-gray-400 mt-1">Exemplares vinculados</div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-emerald-200 dark:border-emerald-800/40 rounded-xl p-4 shadow-xs bg-emerald-50/20">
                <div class="text-xs font-semibold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">Livros Já Devolvidos</div>
                <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $record->totalDevolvidos() }}</div>
                <div class="text-[11px] text-emerald-600/70 mt-1">Recolocados no acervo</div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-amber-200 dark:border-amber-800/40 rounded-xl p-4 shadow-xs bg-amber-50/20">
                <div class="text-xs font-semibold text-amber-700 dark:text-amber-400 uppercase tracking-wider">Pendentes na Sala</div>
                <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ $record->totalPendentes() }}</div>
                <div class="text-[11px] text-amber-600/70 mt-1">Ainda com a turma</div>
            </div>
        </div>

        {{-- SEÇÃO DE OPERAÇÃO: BIPAGEM DE INCLUSÃO OU DEVOLUÇÃO --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- BOX 1: ADICIONAR LIVRO À SACOLA --}}
            <div class="bg-white dark:bg-gray-900 border border-primary-200 dark:border-primary-800/40 rounded-xl p-5 shadow-xs">
                <div class="flex items-center gap-2 mb-2">
                    <x-heroicon-o-plus-circle class="w-5 h-5 text-primary-600" />
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                        Colocar Livro na Sacola (Bipar Tombo ou ISBN)
                    </h3>
                </div>
                <div class="flex gap-2">
                    <input
                        type="text"
                        wire:model="codigo_livro_adicionar"
                        wire:keydown.enter.prevent="adicionarLivroPorCodigo"
                        placeholder="Bipe o código do livro para incluir..."
                        autofocus
                        class="flex-1 text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary-500 focus:ring-primary-500 font-mono"
                    />
                    <button
                        type="button"
                        @click="$dispatch('abrir-scanner-camera', { contexto: 'sacola_adicionar', titulo: 'Colocar Livro na Sacola', subtitulo: 'Aponte a câmera para o código do livro' })"
                        class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-medium text-xs rounded-lg transition-colors flex items-center gap-1.5 border border-gray-200 dark:border-gray-700 shadow-xs"
                        title="Escanear com a câmera do celular"
                    >
                        <x-heroicon-o-camera class="w-4 h-4 text-primary-600 dark:text-primary-400" />
                        <span class="hidden sm:inline">Câmera</span>
                    </button>
                    <button
                        type="button"
                        wire:click="adicionarLivroPorCodigo"
                        class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-semibold text-xs rounded-lg transition-colors flex items-center gap-1 shadow-xs"
                    >
                        <x-heroicon-m-plus class="w-4 h-4" />
                        Incluir
                    </button>
                </div>
                <p class="text-[11px] text-gray-400 mt-1.5">
                    O exemplar é reservado e adicionado imediatamente à sacola de leitura da turma.
                </p>
            </div>

            {{-- BOX 2: CONFERÊNCIA E DEVOLUÇÃO LIVRO A LIVRO --}}
            <div class="bg-white dark:bg-gray-900 border border-emerald-200 dark:border-emerald-800/40 rounded-xl p-5 shadow-xs">
                <div class="flex items-center gap-2 mb-2">
                    <x-heroicon-o-check-circle class="w-5 h-5 text-emerald-600" />
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                        Conferir Devolução da Sacola (Bipar Livro Devolvido)
                    </h3>
                </div>
                <div class="flex gap-2">
                    <input
                        type="text"
                        wire:model="codigo_livro_devolver"
                        wire:keydown.enter.prevent="devolverLivroPorCodigo"
                        placeholder="Bipe o livro devolvido para dar baixa..."
                        class="flex-1 text-sm rounded-lg border-emerald-300 dark:border-emerald-700 dark:bg-gray-800 dark:text-white focus:border-emerald-500 focus:ring-emerald-500 font-mono"
                    />
                    <button
                        type="button"
                        @click="$dispatch('abrir-scanner-camera', { contexto: 'sacola_devolver', titulo: 'Conferir Livro Devolvido', subtitulo: 'Aponte a câmera para o livro retirado da sacola' })"
                        class="px-3.5 py-2 bg-emerald-100 hover:bg-emerald-200 dark:bg-emerald-950 dark:hover:bg-emerald-900 text-emerald-800 dark:text-emerald-200 font-medium text-xs rounded-lg transition-colors flex items-center gap-1.5 border border-emerald-200 dark:border-emerald-800 shadow-xs"
                        title="Escanear devolução com a câmera do celular"
                    >
                        <x-heroicon-o-camera class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                        <span class="hidden sm:inline">Câmera</span>
                    </button>
                    <button
                        type="button"
                        wire:click="devolverLivroPorCodigo"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs rounded-lg transition-colors flex items-center gap-1 shadow-xs"
                    >
                        <x-heroicon-m-check class="w-4 h-4" />
                        Dar Baixa
                    </button>
                </div>
                <p class="text-[11px] text-emerald-700 dark:text-emerald-400 mt-1.5">
                    Ao retirar o livro da sacola física, bipe para confirmar a presença e devolvê-lo ao acervo.
                </p>
            </div>

        </div>

        {{-- LISTA DE LIVROS NA SACOLA --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-xs">
            <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3 mb-4">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-book-open class="w-4 h-4 text-primary-600" />
                    Obras na Sacola ({{ $record->totalLivros() }})
                </h3>
                <span class="text-xs text-gray-500">
                    {{ $record->totalDevolvidos() }} devolvido(s) • {{ $record->totalPendentes() }} pendente(s)
                </span>
            </div>

            @if($this->itensSacola->isEmpty())
                <div class="text-center py-8 text-gray-400">
                    <x-heroicon-o-shopping-bag class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-700 mb-2" />
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Esta sacola ainda não possui nenhum livro.</p>
                    <p class="text-xs text-gray-400 mt-0.5">Utilize o campo de bipagem acima para adicionar as obras selecionadas.</p>
                </div>
            @else
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($this->itensSacola as $item)
                        <div class="py-3 flex items-center justify-between gap-3 text-xs">
                            <div class="flex items-center gap-3 min-w-0">
                                @if($item->livro->capa)
                                    <img src="{{ $item->livro->capa_url }}" class="w-9 h-12 object-cover rounded shadow-xs shrink-0" alt="Capa" />
                                @else
                                    <div class="w-9 h-12 bg-gray-100 dark:bg-gray-800 rounded flex items-center justify-center text-[10px] text-gray-400 font-semibold shrink-0">
                                        LIV
                                    </div>
                                @endif

                                <div class="truncate">
                                    <div class="font-bold text-gray-900 dark:text-white truncate">
                                        {{ $item->livro->titulo }}
                                    </div>
                                    <div class="text-[11px] text-gray-500 truncate">
                                        {{ $item->livro->autor ?: 'Autor não informado' }} • <span class="font-mono text-primary-600 dark:text-primary-400 font-semibold">{{ $item->livro->codigo ?: $item->livro->isbn }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                @if($item->devolvido)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                                        <x-heroicon-m-check-circle class="w-3.5 h-3.5 text-emerald-600" />
                                        Devolvido ({{ $item->devolvido_em?->format('d/m H:i') }})
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                        <x-heroicon-m-clock class="w-3.5 h-3.5 text-amber-600" />
                                        Na Sacola
                                    </span>

                                    <button
                                        type="button"
                                        wire:click="devolverItemIndividual({{ $item->id }})"
                                        class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[11px] font-medium transition flex items-center gap-1"
                                        title="Marcar como devolvido ao acervo"
                                    >
                                        <x-heroicon-m-check class="w-3 h-3" />
                                        Devolver
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="removerItem({{ $item->id }})"
                                        wire:confirm="Remover esta obra da sacola e retornar o exemplar ao acervo?"
                                        class="p-1 text-gray-400 hover:text-danger-600 rounded transition"
                                        title="Remover da sacola"
                                    >
                                        <x-heroicon-m-trash class="w-4 h-4" />
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    {{-- MODAL REUTILIZÁVEL DE SCANNER COM CÂMERA DO CELULAR --}}
    @include('filament.components.barcode-scanner-modal')
</x-filament-panels::page>
