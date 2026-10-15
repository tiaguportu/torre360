<x-filament-panels::page>
    <div class="space-y-6">
        <!-- CABEÇALHO RESUMO DA AVALIAÇÃO -->
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Turma</span>
                    <p class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $record->turma?->nome ?? '-' }}</p>
                </div>
                <div>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Disciplina</span>
                    <p class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $record->disciplina?->nome ?? '-' }}</p>
                </div>
                <div>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Nota Máxima</span>
                    <p class="text-base font-bold text-primary-600 dark:text-primary-400">
                        {{ number_format((float) ($record->nota_maxima ?? 10), 2, ',', '.') }}
                    </p>
                </div>
                <div>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Gabarito Oficial</span>
                    <p class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        {{ $record->gabarito?->total_questoes ?? 0 }} questões ({{ $record->gabarito?->alternativas ?? 5 }} alts)
                    </p>
                </div>
            </div>
        </div>

        <!-- SEÇÃO DE CAPTURA / UPLOAD DE FOTOS -->
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
            <h3 class="mb-3 text-lg font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                <span class="text-primary-600">📸</span> Capturar ou Enviar Fotos das Folhas
            </h3>
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                Fotografe a folha de respostas inteira mantendo boa iluminação e sem sombras sobre os 4 marcadores de canto. Em smartphones, o botão abaixo abre diretamente a câmera traseira do aparelho.
            </p>

            <form wire:submit="processarFotos" class="space-y-4">
                {{ $this->form }}

                <div class="flex flex-wrap items-center gap-3">
                    <x-filament::button type="submit" size="lg" icon="heroicon-o-camera" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="processarFotos">Processar Imagens com OMR</span>
                        <span wire:loading wire:target="processarFotos">Processando e detectando bolhas...</span>
                    </x-filament::button>

                    @if(count($correcoes) > 0)
                        <x-filament::button type="button" color="success" size="lg" icon="heroicon-o-check-badge" wire:click="lancarTodasConfirmadas">
                            Lançar Notas Prontas
                        </x-filament::button>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            Só entram folhas com aluno identificado, sem marcação a conferir e sem conflito com nota já lançada.
                        </span>
                    @endif
                </div>
            </form>
        </div>

        <!-- LISTAGEM DE CONFERÊNCIA DAS FOLHAS CORRIGIDAS -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                    <span>📋</span> Folhas Lidas e Conferência de Notas ({{ count($correcoes) }})
                </h3>
            </div>

            @if(empty($correcoes))
                <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Nenhuma folha de gabarito enviada para esta avaliação ainda. Use o campo acima para fotografar ou enviar as folhas dos alunos.
                    </p>
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    @foreach($correcoes as $c)
                        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-5">
                            <div class="flex flex-wrap items-start justify-between gap-2 border-b border-gray-100 pb-3 dark:border-gray-800">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-bold text-gray-900 dark:text-gray-100 text-base">
                                            {{ $c['aluno_nome'] }}
                                        </h4>
                                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium {{ $c['status_classe'] }}">
                                            {{ $c['status_label'] }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Matrícula: {{ $c['matricula_codigo'] }} • Folha UUID: {{ substr($c['uuid'], 0, 8) }}...
                                    </p>
                                </div>

                                <div class="text-right">
                                    <span class="text-2xl font-black text-primary-600 dark:text-primary-400">
                                        {{ number_format((float) $c['nota_calculada'], 2, ',', '.') }}
                                    </span>
                                    <span class="text-xs text-gray-400">/ {{ number_format((float) ($record->nota_maxima ?? 10), 2, ',', '.') }}</span>
                                    <p class="text-xs text-gray-500">
                                        {{ $c['acertos'] }} acertos de {{ $c['total_validas'] }} questões
                                    </p>
                                </div>
                            </div>

                            <!-- ALERTAS DE CONFERÊNCIA -->
                            @if(!empty($c['alertas']))
                                <div class="mt-3 rounded-lg bg-amber-50 p-2.5 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                                    <p class="font-bold flex items-center gap-1">⚠️ Atenção para conferência:</p>
                                    <ul class="list-disc pl-4 mt-1 space-y-0.5">
                                        @foreach($c['alertas'] as $alerta)
                                            <li>{{ $alerta }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <!-- CONFLITO COM NOTA JÁ LANÇADA: exige confirmação explícita para sobrescrever -->
                            @if(!empty($c['conflito']))
                                <div class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-2.5 text-xs text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">
                                    <p class="font-bold">Este aluno já tem nota lançada nesta avaliação.</p>
                                    <p class="mt-1">
                                        Nota atual: <strong>{{ number_format((float) $c['conflito']['atual'], 2, ',', '.') }}</strong>
                                        &rarr; nota da folha: <strong>{{ number_format((float) $c['conflito']['nova'], 2, ',', '.') }}</strong>
                                    </p>
                                    <x-filament::button class="mt-2" size="xs" color="danger" icon="heroicon-o-exclamation-triangle" wire:click="lancarNotaIndividual({{ $c['id'] }}, true)">
                                        Sobrescrever a nota atual
                                    </x-filament::button>
                                </div>
                            @endif

                            <!-- GRADE RESUMIDA DE RESPOSTAS -->
                            <div class="mt-4">
                                <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">Respostas Detectadas:</span>
                                <div class="mt-2 flex flex-wrap gap-1.5 max-h-36 overflow-y-auto p-1">
                                    @foreach($c['respostas_detalhe'] as $num => $resp)
                                        <div class="inline-flex items-center gap-1 rounded border px-2 py-0.5 text-xs font-mono {{ $resp['cor_borda'] }}" title="{{ $resp['titulo'] }}">
                                            <span class="text-gray-400">{{ $num }}:</span>
                                            <span class="font-bold {{ $resp['cor_texto'] }}">{{ $resp['letra'] ?? '—' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- AÇÕES DA CORREÇÃO -->
                            <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3 dark:border-gray-800">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if($c['status'] !== 'confirmada')
                                        @if(empty($c['matricula_id']))
                                            <div class="flex flex-wrap items-center gap-2">
                                                <select
                                                    wire:change="associarMatricula({{ $c['id'] }}, $event.target.value)"
                                                    class="rounded-lg border border-rose-300 bg-white px-2.5 py-1 text-xs text-gray-900 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-rose-700 dark:bg-gray-800 dark:text-gray-100"
                                                >
                                                    <option value="">-- Selecione o Aluno da Turma --</option>
                                                    @foreach($this->getAlunosTurma() as $alunoOpcao)
                                                        <option value="{{ $alunoOpcao['id'] }}">{{ $alunoOpcao['nome'] }}</option>
                                                    @endforeach
                                                </select>

                                                <x-filament::button size="sm" color="gray" icon="heroicon-o-lock-closed" disabled="disabled" title="Vincule um aluno para habilitar o lançamento">
                                                    Lançar Nota (Bloqueado)
                                                </x-filament::button>
                                            </div>
                                        @else
                                            @if($c['status'] === 'aguardando_conferencia')
                                                @if($c['tem_marcacao_a_conferir'])
                                                    <x-filament::button size="sm" color="warning" icon="heroicon-o-check" wire:click="lancarNotaIndividual({{ $c['id'] }})" wire:confirm="Esta folha tem marcação dupla, ambígua ou ilegível. A nota calculada pode estar incorreta. Lançar mesmo assim?">
                                                        Lançar Nota ({{ number_format((float) $c['nota_calculada'], 2, ',', '.') }})
                                                    </x-filament::button>
                                                @else
                                                    <x-filament::button size="sm" color="success" icon="heroicon-o-check" wire:click="lancarNotaIndividual({{ $c['id'] }})">
                                                        Lançar Nota ({{ number_format((float) $c['nota_calculada'], 2, ',', '.') }})
                                                    </x-filament::button>
                                                @endif
                                            @endif
                                        @endif
                                    @else
                                        <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                            ✓ Nota lançada no diário
                                        </span>
                                    @endif
                                </div>

                                <x-filament::button size="sm" color="danger" variant="outlined" wire:click="descartarCorrecao({{ $c['id'] }})">
                                    Descartar
                                </x-filament::button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
