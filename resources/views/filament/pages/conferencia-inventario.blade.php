<x-filament-panels::page>
    <div class="space-y-6">

        {{-- CABEÇALHO DO INVENTÁRIO COM STATUS --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $record->titulo }}</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $record->isAberto() ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' }}">
                        {{ $record->isAberto() ? 'Auditoria em Andamento' : 'Auditoria Concluída' }}
                    </span>
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    Iniciado em {{ $record->data_inicio->format('d/m/Y') }}
                    @if($record->data_fim)
                        • Finalizado em {{ $record->data_fim->format('d/m/Y') }}
                    @endif
                </p>
            </div>
            @if($record->observacoes)
                <div class="text-xs text-gray-600 dark:text-gray-300 max-w-md bg-gray-50 dark:bg-gray-800 p-2.5 rounded-lg border border-gray-100 dark:border-gray-700">
                    <span class="font-semibold">Notas:</span> {{ $record->observacoes }}
                </div>
            @endif
        </div>

        {{-- WIDGETS DE MÉTRICAS EM TEMPO REAL --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            {{-- TOTAL ACERVO --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total de Obras</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $this->totalAcervo }}</div>
                <div class="text-[11px] text-gray-400 mt-1">Títulos cadastrados</div>
            </div>

            {{-- CONFERIDOS --}}
            <div class="bg-white dark:bg-gray-900 border border-emerald-200 dark:border-emerald-800/40 rounded-xl p-4 shadow-sm bg-emerald-50/20">
                <div class="text-xs font-semibold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">Presentes na Estante</div>
                <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $this->totalConferidos }}</div>
                <div class="text-[11px] text-emerald-600/70 mt-1">Exemplares bipados</div>
            </div>

            {{-- EMPRESTADOS --}}
            <div class="bg-white dark:bg-gray-900 border border-blue-200 dark:border-blue-800/40 rounded-xl p-4 shadow-sm bg-blue-50/20">
                <div class="text-xs font-semibold text-blue-700 dark:text-blue-400 uppercase tracking-wider">Em Circulação</div>
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ $this->totalEmprestados }}</div>
                <div class="text-[11px] text-blue-600/70 mt-1">Com alunos (justificados)</div>
            </div>

            {{-- FALTANTES / DIVERGÊNCIA --}}
            <div class="bg-white dark:bg-gray-900 border border-danger-200 dark:border-danger-800/40 rounded-xl p-4 shadow-sm bg-danger-50/20">
                <div class="text-xs font-semibold text-danger-700 dark:text-danger-400 uppercase tracking-wider">Faltantes / Extraviados</div>
                <div class="text-2xl font-bold text-danger-600 dark:text-danger-400 mt-1">{{ $this->livrosFaltantes->count() }}</div>
                <div class="text-[11px] text-danger-600/70 mt-1">Não encontrados na estante</div>
            </div>
        </div>

        {{-- CAMPO DE BIPAGEM RÁPIDA (LEITOR ÓPTICO) --}}
        @if($record->isAberto())
            <div class="bg-white dark:bg-gray-900 border border-primary-200 dark:border-primary-800/40 rounded-xl p-5 shadow-sm bg-primary-50/10">
                <div class="flex items-center gap-2 mb-2">
                    <x-heroicon-o-qr-code class="w-5 h-5 text-primary-600" />
                    <label class="text-sm font-bold text-gray-900 dark:text-white">
                        Bipar Livro na Estante (Código de Barras, Tombo ou ISBN)
                    </label>
                </div>
                <div class="flex gap-2">
                    <input
                        type="text"
                        wire:model="codigo_bipado"
                        wire:keydown.enter.prevent="biparLivro"
                        placeholder="Aponte o leitor de código de barras para o livro..."
                        autofocus
                        class="flex-1 text-base py-3 px-4 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-850 dark:text-white focus:border-primary-500 focus:ring-primary-500 font-mono"
                    />
                    <button
                        type="button"
                        wire:click="biparLivro"
                        class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold text-sm rounded-lg transition-colors flex items-center gap-2 shadow-xs"
                    >
                        <x-heroicon-m-check class="w-5 h-5" />
                        Conferir Livro
                    </button>
                </div>
                <p class="text-xs text-gray-500 mt-2">
                    ⚡ <strong>Dica de Produtividade:</strong> O leitor de código de barras envia automaticamente o comando Enter. Basta bipar os livros um atrás do outro nas prateleiras sem tocar no teclado.
                </p>
            </div>
        @endif

        {{-- TABELAS DE RESULTADOS E DIVERGÊNCIAS --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- ÚLTIMOS LIVROS BIPADOS --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3 mb-3">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-check-circle class="w-4 h-4 text-emerald-600" />
                        Últimos Exemplares Conferidos ({{ $this->totalConferidos }})
                    </h3>
                </div>

                @if($this->ultimosBipados->isEmpty())
                    <p class="text-xs text-gray-400 italic py-4 text-center">Nenhum livro conferido nesta auditoria ainda.</p>
                @else
                    <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                        @foreach($this->ultimosBipados as $item)
                            <div class="text-xs p-2.5 rounded-lg bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-700 flex items-center justify-between">
                                <div class="truncate pr-2">
                                    <div class="font-bold text-gray-900 dark:text-white truncate">{{ $item->livro->titulo }}</div>
                                    <div class="text-[11px] text-gray-500">{{ $item->livro->autor }} • <span class="font-mono text-gray-700 dark:text-gray-300 font-semibold">{{ $item->livro->codigo ?: 'LIV-' . $item->livro->id }}</span></div>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                                        Qtd: {{ $item->quantidade_conferida }}
                                    </span>
                                    <div class="text-[10px] text-gray-400 mt-0.5 font-mono">{{ $item->bipado_em->format('H:i:s') }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- LIVROS FALTANTES / DIVERGÊNCIA --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3 mb-3">
                    <h3 class="text-sm font-bold text-danger-600 dark:text-danger-400 flex items-center gap-2">
                        <x-heroicon-o-exclamation-triangle class="w-4 h-4" />
                        Livros Faltantes / Não Localizados ({{ $this->livrosFaltantes->count() }})
                    </h3>
                </div>

                @if($this->livrosFaltantes->isEmpty())
                    <div class="py-6 text-center text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex flex-col items-center gap-1">
                        <x-heroicon-o-check-badge class="w-8 h-8 text-emerald-500" />
                        Excelente! Todos os livros do acervo foram localizados na estante ou constam como emprestados.
                    </div>
                @else
                    <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                        @foreach($this->livrosFaltantes as $faltante)
                            <div class="text-xs p-2.5 rounded-lg bg-danger-50/40 dark:bg-danger-950/20 border border-danger-200/50 dark:border-danger-800/40 flex items-center justify-between">
                                <div class="truncate pr-2">
                                    <div class="font-bold text-gray-900 dark:text-white truncate">{{ $faltante->titulo }}</div>
                                    <div class="text-[11px] text-gray-500">{{ $faltante->autor }} • <span class="font-mono text-danger-700 dark:text-danger-300 font-semibold">{{ $faltante->codigo ?: 'LIV-' . $faltante->id }}</span></div>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold bg-danger-100 text-danger-800 dark:bg-danger-950 dark:text-danger-200">
                                        Faltando: {{ $faltante->quantidade_total }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>

    </div>
</x-filament-panels::page>
