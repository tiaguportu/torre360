<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Banner de Autenticidade e Validação Jurídica --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary-900 via-primary-800 to-primary-950 p-6 text-white shadow-md">
            <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-start gap-4">
                    <div class="rounded-xl bg-white/10 p-3 backdrop-blur-sm">
                        <x-heroicon-o-shield-check class="h-8 w-8 text-secondary-300" />
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-white tracking-tight">Auto-Atendimento de Documentos e Declarações Oficiais</h2>
                        <p class="mt-1 text-sm text-primary-100 max-w-2xl leading-relaxed">
                            Emita certidões e declarações na hora, sem filas ou deslocamentos até a secretaria. Todos os documentos possuem carimbo digital rastreável e <strong>QR Code com validação pública instantânea</strong>.
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 self-start md:self-auto">
                    <a href="{{ route('documentos.validar-autenticidade') }}" target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-3.5 py-2 text-xs font-semibold text-white hover:bg-white/20 transition backdrop-blur-sm border border-white/20">
                        <x-heroicon-o-qr-code class="h-4 w-4" />
                        Conferir Autenticidade
                    </a>
                </div>
            </div>
        </div>

        {{-- Seletor de Estudante (caso o responsável tenha mais de 1 aluno) --}}
        @if ($this->matriculasAcessiveis->count() > 1)
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Selecione o Estudante para Emissão:
                    </span>
                    <span class="text-xs text-primary-600 dark:text-primary-400 font-medium">
                        {{ $this->matriculasAcessiveis->count() }} estudantes vinculados
                    </span>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->matriculasAcessiveis as $matriculaItem)
                        @php $isAtivo = ($this->alunoSelecionadoId === $matriculaItem->id); @endphp
                        <button type="button"
                                wire:click="selecionarAluno({{ $matriculaItem->id }})"
                                class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-semibold transition border {{ $isAtivo ? 'bg-primary-600 text-white border-primary-600 shadow-sm' : 'bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100 dark:bg-gray-800 dark:border-white/10 dark:text-gray-200 dark:hover:bg-gray-700' }}">
                            <x-heroicon-s-academic-cap class="h-4 w-4 {{ $isAtivo ? 'text-white' : 'text-primary-500' }}" />
                            <span>{{ $matriculaItem->pessoa?->nome ?? 'Estudante' }}</span>
                            <span class="text-[11px] opacity-75 font-normal">({{ $matriculaItem->turma?->nome ?? 'Regular' }})</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Aluno Selecionado Atual --}}
        @php
            $alunoAtivo = $this->matriculaAtual;
            $templates = $this->templatesDisponiveis;
        @endphp

        @if ($alunoAtivo)
            {{-- Painel de Emissão Rápida em 1 Clique --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 dark:border-white/10 pb-4 mb-6">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-700/10 dark:bg-primary-950/50 dark:text-primary-300">
                                Estudante Selecionado
                            </span>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white">
                                {{ $alunoAtivo->pessoa?->nome }}
                            </h3>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Matrícula nº {{ $alunoAtivo->id }} &middot; Turma: {{ $alunoAtivo->turma?->nome ?? 'Sem turma' }} &middot; {{ $alunoAtivo->turma?->serie?->curso?->nome_externo ?? 'Ensino Regular' }} &middot; Turno: {{ $alunoAtivo->turma?->turno?->nome ?? 'Regular' }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        {{-- Botão de Atalho para o Boletim Escolar --}}
                        <a href="{{ route('matriculas.boletim.download', $alunoAtivo) }}" target="_blank"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 transition">
                            <x-heroicon-o-chart-bar class="h-4 w-4 text-primary-500" />
                            Boletim Escolar
                        </a>

                        {{-- Botão de Atalho para o Histórico Escolar Oficial --}}
                        @if ($this->historicoEscolar)
                            <a href="{{ route('historicos-escolares.pdf', $this->historicoEscolar) }}" target="_blank"
                               class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 shadow-sm hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-300 transition">
                                <x-heroicon-o-academic-cap class="h-4 w-4 text-amber-600" />
                                Histórico Oficial
                            </a>
                        @endif
                    </div>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-m-bolt class="h-4 w-4 text-amber-500" />
                        Declarações Disponíveis para Emissão Imediata
                    </h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Escolha o documento desejado. O sistema processa as informações em tempo real e disponibiliza o arquivo oficial assinado.
                    </p>
                </div>

                {{-- Grade de Cards de Documentos --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($templates as $template)
                        <div class="group relative flex flex-col justify-between rounded-xl border border-gray-200 bg-gray-50/50 p-4 transition-all hover:border-primary-400 hover:bg-white hover:shadow-md dark:border-white/10 dark:bg-gray-800/40 dark:hover:border-primary-500 dark:hover:bg-gray-800">
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="rounded-lg bg-primary-100/70 p-2 text-primary-700 dark:bg-primary-950 dark:text-primary-300">
                                        @if ($template->tipo?->value === 'declaracao_matricula')
                                            <x-heroicon-o-academic-cap class="h-5 w-5" />
                                        @elseif ($template->tipo?->value === 'declaracao_frequencia')
                                            <x-heroicon-o-calendar-days class="h-5 w-5" />
                                        @elseif ($template->tipo?->value === 'declaracao_quitacao')
                                            <x-heroicon-o-banknotes class="h-5 w-5" />
                                        @elseif ($template->tipo?->value === 'declaracao_transporte')
                                            <x-heroicon-o-truck class="h-5 w-5" />
                                        @elseif ($template->tipo?->value === 'declaracao_horario')
                                            <x-heroicon-o-clock class="h-5 w-5" />
                                        @elseif ($template->tipo?->value === 'declaracao_conclusao')
                                            <x-heroicon-o-check-badge class="h-5 w-5" />
                                        @elseif ($template->tipo?->value === 'declaracao_transferencia')
                                            <x-heroicon-o-arrows-right-left class="h-5 w-5" />
                                        @else
                                            <x-heroicon-o-document-text class="h-5 w-5" />
                                        @endif
                                    </div>
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                        Validade: {{ $template->validade_dias ?? 30 }} dias
                                    </span>
                                </div>

                                <h5 class="mt-3 text-sm font-semibold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition">
                                    {{ $template->nome }}
                                </h5>

                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">
                                    {{ $template->descricao ?: 'Emissão de documento oficial autenticado com validação por QR Code.' }}
                                </p>
                            </div>

                            <div class="mt-4 pt-3 border-t border-gray-200/60 dark:border-white/5 flex items-center justify-between">
                                <span class="text-[11px] text-gray-400 dark:text-gray-500">
                                    Digital &middot; QR Code
                                </span>

                                <button type="button"
                                        wire:click="emitirInstantaneo({{ $template->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="emitirInstantaneo({{ $template->id }})"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 disabled:opacity-50 transition">
                                    <span wire:loading.remove wire:target="emitirInstantaneo({{ $template->id }})" class="flex items-center gap-1">
                                        <x-heroicon-m-arrow-down-tray class="h-3.5 w-3.5" />
                                        Emitir Agora
                                    </span>
                                    <span wire:loading wire:target="emitirInstantaneo({{ $template->id }})" class="flex items-center gap-1">
                                        <svg class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                        </svg>
                                        Gerando...
                                    </span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-500 dark:border-white/10 dark:bg-gray-900">
                Nenhum estudante matriculado foi localizado no seu cadastro para auto-emissão de declarações.
            </div>
        @endif

        {{-- Tabela Histórica com todas as declarações emitidas --}}
        <div class="mt-8">
            <div class="mb-3">
                <h3 class="text-base font-bold text-gray-950 dark:text-white">
                    Histórico de Documentos e Protocolos Emitidos
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Consulte os documentos gerados anteriormente, baixe novamente o PDF ou confira o código de validação digital.
                </p>
            </div>

            {{ $this->table }}
        </div>

    </div>
</x-filament-panels::page>
