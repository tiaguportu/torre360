<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Cabeçalho Informativo -->
        <div class="bg-gradient-to-r from-amber-500 via-orange-500 to-rose-500 rounded-2xl p-6 text-white shadow-lg">
            <div class="max-w-3xl">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-white/20 text-white backdrop-blur-md mb-3">
                    <x-heroicon-o-sparkles class="w-4 h-4" /> Atividades e Celebrações
                </span>
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Eventos Escolares e Confirmação de Presença</h2>
                <p class="mt-2 text-white/90 text-sm sm:text-base leading-relaxed">
                    Acompanhe as reuniões de pais, celebrações culturais, passeios pedagógicos e solenidades programadas pela escola. Confirme sua presença e assine os termos de autorização diretamente por aqui.
                </p>
            </div>
        </div>

        @if($this->eventos->isEmpty())
            <div class="text-center py-16 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-8">
                <div class="inline-flex p-4 rounded-full bg-amber-50 dark:bg-amber-900/30 text-amber-500 mb-4">
                    <x-heroicon-o-calendar-days class="w-10 h-10" />
                </div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Nenhum evento programado no momento</h3>
                <p class="text-gray-500 dark:text-gray-400 text-sm mt-1 max-w-md mx-auto">
                    Assim que novas atividades, reuniões ou passeios forem agendados pela coordenação, eles aparecerão aqui para você.
                </p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach($this->eventos as $evento)
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden flex flex-col hover:shadow-md transition-shadow">
                        <!-- Topo do Card -->
                        <div class="p-6 border-b border-gray-100 dark:border-gray-700/60 bg-gray-50/50 dark:bg-gray-800/50">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300 border border-primary-200/50">
                                        {{ $evento->tipo->getLabel() }}
                                    </span>
                                    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-2">
                                        {{ $evento->titulo }}
                                    </h3>
                                </div>
                                @if($evento->exige_autorizacao)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200" title="Exige autorização formal dos pais para saída">
                                        <x-heroicon-o-shield-check class="w-4 h-4" /> Exige Autorização
                                    </span>
                                @endif
                            </div>

                            <!-- Metadados: Data, Local, Custo -->
                            <div class="mt-4 grid grid-cols-2 gap-3 text-xs text-gray-600 dark:text-gray-300">
                                <div class="flex items-center gap-2">
                                    <x-heroicon-o-clock class="w-4 h-4 text-gray-400" />
                                    <span>{{ $evento->data_inicio->format('d/m/Y \à\s H:i') }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-heroicon-o-map-pin class="w-4 h-4 text-gray-400" />
                                    <span class="truncate">{{ $evento->local ?: 'Nas dependências da Escola' }}</span>
                                </div>
                                @if($evento->limite_vagas)
                                    <div class="flex items-center gap-2">
                                        <x-heroicon-o-users class="w-4 h-4 text-gray-400" />
                                        <span>Vagas Restantes: <strong>{{ $evento->vagas_restantes }}</strong></span>
                                    </div>
                                @endif
                                @if($evento->valor_por_pessoa > 0)
                                    <div class="flex items-center gap-2">
                                        <x-heroicon-o-currency-dollar class="w-4 h-4 text-gray-400" />
                                        <span>Valor: <strong>R$ {{ number_format($evento->valor_por_pessoa, 2, ',', '.') }}</strong></span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Descrição -->
                        @if($evento->descricao)
                            <div class="p-6 text-sm text-gray-600 dark:text-gray-300 leading-relaxed border-b border-gray-100 dark:border-gray-700/60 flex-1">
                                {!! $evento->descricao !!}
                            </div>
                        @endif

                        <!-- Seção de Confirmação por Filho / Estudante -->
                        <div class="p-6 bg-gray-50/80 dark:bg-gray-800/80 mt-auto">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">
                                Confirmação de Presença (RSVP)
                            </h4>

                            <div class="space-y-3">
                                @foreach($this->matriculasAcessiveis as $matricula)
                                    @php
                                        // Verifica se o evento é para todos ou para a turma desta matrícula
                                        $elegivel = $evento->publico_alvo === 'todos' || $evento->turmas->contains($matricula->turma_id);
                                    @endphp

                                    @if($elegivel)
                                        @php
                                            $resposta = $this->getConfirmacaoPara($evento, $matricula);
                                        @endphp

                                        <div class="bg-white dark:bg-gray-700/60 rounded-xl p-3.5 border border-gray-200 dark:border-gray-600 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                            <div>
                                                <div class="font-bold text-sm text-gray-900 dark:text-gray-100">
                                                    {{ $matricula->pessoa?->nome }}
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    Turma: {{ $matricula->turma?->nome ?? 'Não alocado' }}
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                @if($resposta)
                                                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold {{ $resposta->status->getColor() === 'success' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' }}">
                                                        @if($resposta->status->value === 'confirmado')
                                                            <x-heroicon-o-check-circle class="w-4 h-4" /> Confirmado (+{{ $resposta->quantidade_acompanhantes }})
                                                        @else
                                                            <x-heroicon-o-x-circle class="w-4 h-4" /> Recusado
                                                        @endif
                                                    </span>

                                                    @if(! $evento->isEncerrado() && ! $evento->isPrazoExpirado())
                                                        <button 
                                                            type="button" 
                                                            wire:click="abrirModalRsvp({{ $evento->id }}, {{ $matricula->id }}, '{{ $resposta->status->value }}')"
                                                            class="text-xs font-semibold text-primary-600 hover:text-primary-700 underline ml-2">
                                                            Alterar
                                                        </button>
                                                    @endif
                                                @else
                                                    @if($evento->isEncerrado() || $evento->isPrazoExpirado())
                                                        <span class="text-xs font-semibold text-gray-400 italic">Prazo encerrado</span>
                                                    @else
                                                        <button
                                                            type="button"
                                                            wire:click="abrirModalRsvp({{ $evento->id }}, {{ $matricula->id }}, 'confirmado')"
                                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm transition-colors">
                                                            <x-heroicon-o-check class="w-4 h-4" /> Confirmar
                                                        </button>

                                                        <button
                                                            type="button"
                                                            wire:click="abrirModalRsvp({{ $evento->id }}, {{ $matricula->id }}, 'recusado')"
                                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200 transition-colors">
                                                            <x-heroicon-o-x-mark class="w-4 h-4" /> Não irei
                                                        </button>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Modal de Confirmação e Autorização -->
        @if($showModal)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-700 relative animate-in fade-in duration-200">
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                            Confirmar Presença no Evento
                        </h3>
                        <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-500">
                            <x-heroicon-o-x-mark class="w-6 h-6" />
                        </button>
                    </div>

                    <div class="mt-4 space-y-4">
                        <!-- Seleção do Status -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                                Sua Resposta:
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <button 
                                    type="button" 
                                    wire:click="$set('rsvpStatus', 'confirmado')"
                                    class="py-2.5 px-4 rounded-xl border text-sm font-bold flex items-center justify-center gap-2 transition-all {{ $rsvpStatus === 'confirmado' ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 ring-2 ring-emerald-500/20' : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300' }}">
                                    <x-heroicon-o-check-circle class="w-5 h-5" /> Presença Confirmada
                                </button>
                                <button 
                                    type="button" 
                                    wire:click="$set('rsvpStatus', 'recusado')"
                                    class="py-2.5 px-4 rounded-xl border text-sm font-bold flex items-center justify-center gap-2 transition-all {{ $rsvpStatus === 'recusado' ? 'border-rose-500 bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300 ring-2 ring-rose-500/20' : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300' }}">
                                    <x-heroicon-o-x-circle class="w-5 h-5" /> Não Comparecerá
                                </button>
                            </div>
                        </div>

                        @if($rsvpStatus === 'confirmado')
                            <!-- Acompanhantes -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                                    Quantidade de Acompanhantes (Familiares):
                                </label>
                                <input 
                                    type="number" 
                                    min="0" 
                                    max="10" 
                                    wire:model="quantidadeAcompanhantes"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-primary-500 focus:border-primary-500" 
                                    placeholder="0" />
                                <span class="text-xs text-gray-400 mt-1 block">Ex: pai e mãe = 1 aluno + 2 acompanhantes.</span>
                            </div>

                            @php
                                $eventoAtual = $this->eventoSelecionado;
                            @endphp

                            <!-- Termo de Autorização se o evento exigir -->
                            @if($eventoAtual && $eventoAtual->exige_autorizacao)
                                <div class="bg-amber-50 dark:bg-amber-950/40 rounded-xl p-4 border border-amber-200 dark:border-amber-800">
                                    <h4 class="text-xs font-bold text-amber-900 dark:text-amber-200 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                        <x-heroicon-o-document-text class="w-4 h-4" /> Termo de Autorização de Saída / Passeio
                                    </h4>
                                    <p class="text-xs text-amber-800 dark:text-amber-300 leading-relaxed mb-3">
                                        {{ $eventoAtual->termo_autorizacao ?: 'Autorizo formalmente a participação e locomoção do estudante sob supervisão da equipe pedagógica e monitores responsáveis pelo evento.' }}
                                    </p>
                                    <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-amber-950 dark:text-amber-100">
                                        <input 
                                            type="checkbox" 
                                            wire:model="autorizado" 
                                            class="rounded border-amber-400 text-amber-600 focus:ring-amber-500 w-4 h-4" />
                                        <span>Declaro que li e AUTORIZO a participação do meu dependente.</span>
                                    </label>
                                </div>
                            @endif
                        @endif

                        <!-- Observações -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                                Observações ou Mensagem à Coordenação:
                            </label>
                            <textarea 
                                wire:model="observacoes" 
                                rows="2" 
                                class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-primary-500 focus:border-primary-500"
                                placeholder="Alguma restrição ou observação especial?"></textarea>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                        <button 
                            type="button" 
                            wire:click="$set('showModal', false)"
                            class="px-4 py-2 rounded-xl text-sm font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                            Cancelar
                        </button>
                        <button 
                            type="button" 
                            wire:click="salvarRsvp"
                            class="px-5 py-2 rounded-xl text-sm font-bold bg-primary-600 hover:bg-primary-700 text-white shadow-md transition-all">
                            Gravar Resposta
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
