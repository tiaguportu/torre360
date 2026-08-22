<div class="space-y-6">
    @if ($pessoas->isEmpty())
        <div class="fi-section rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-500 dark:border-white/10 dark:bg-gray-900 dark:text-gray-400">
            Nenhum aluno vinculado ao seu cadastro foi encontrado. Entre em contato com a secretaria caso isso não esteja correto.
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($pessoas as $pessoa)
                <div class="fi-section rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $pessoa->nome }}</h3>

                    @foreach ($pessoa->matriculas as $matricula)
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ $matricula->turma?->nome ?? 'Sem turma' }}
                            @if ($matricula->periodoLetivo)
                                &middot; {{ $matricula->periodoLetivo->nome }}
                            @endif
                        </p>
                    @endforeach

                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ \App\Filament\Portal\Pages\Academico::getUrl() }}"
                           class="fi-btn fi-btn-size-sm inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500">
                            Boletim
                        </a>
                        <a href="{{ \App\Filament\Portal\Pages\Financeiro::getUrl() }}"
                           class="fi-btn fi-btn-size-sm inline-flex items-center gap-1 rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200">
                            Financeiro
                        </a>
                        <a href="{{ \App\Filament\Portal\Pages\Documentos::getUrl() }}"
                           class="fi-btn fi-btn-size-sm inline-flex items-center gap-1 rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200">
                            Documentos
                        </a>
                        <a href="{{ \App\Filament\Portal\Pages\Ocorrencias::getUrl() }}"
                           class="fi-btn fi-btn-size-sm inline-flex items-center gap-1 rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200">
                            Ocorrências
                        </a>
                        <a href="{{ \App\Filament\Portal\Pages\Preceptoria::getUrl() }}"
                           class="fi-btn fi-btn-size-sm inline-flex items-center gap-1 rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200">
                            Preceptoria
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
