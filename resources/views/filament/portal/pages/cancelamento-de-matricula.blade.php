<x-filament-panels::page>
    @php
        $matriculas = $this->getMatriculas();
        $pedidos = $this->getPedidos($matriculas);
        $reais = fn (float $valor): string => 'R$ '.number_format($valor, 2, ',', '.');
    @endphp

    @if ($matriculas->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-600 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-300">
            Não há aluno cuja matrícula você possa cancelar por aqui. O pedido é feito pelo responsável pelo aluno, pelo responsável financeiro do contrato ou pelo próprio aluno maior de idade.
        </div>
    @endif

    <div class="space-y-4">
        @foreach ($matriculas as $matricula)
            @php
                $pedido = $pedidos->get($matricula->id);
                $pendencias = $this->getPendencias($matricula);
            @endphp

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ $matricula->pessoa?->nome }}</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $matricula->turma?->nome }}@if ($matricula->turma?->serie) · {{ $matricula->turma->serie->nome }}@endif
                            · Matrícula {{ strtolower($matricula->situacao?->getLabel() ?? '') }}
                        </p>
                    </div>

                    @if ($this->podePedir($matricula, $pedido))
                        <div>{{ ($this->solicitarAction)(['matricula' => $matricula->id]) }}</div>
                    @endif
                </div>

                @if ($pendencias['faturas'] > 0 && $this->podePedir($matricula, $pedido))
                    <p class="mt-3 text-sm text-warning-700 dark:text-warning-400">
                        Há {{ $pendencias['faturas'] }} fatura(s) vencida(s): {{ $reais($pendencias['total']) }} com multa e juros até hoje. Débitos vencidos continuam devidos depois do cancelamento.
                    </p>
                @endif

                @if ($pedido)
                    <div class="mt-4 rounded-lg border border-gray-200 p-4 text-sm dark:border-white/10">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-gray-950 dark:text-white">Pedido de {{ $pedido->created_at->format('d/m/Y') }}</span>
                            <x-filament::badge :color="$pedido->status->getColor()">{{ $pedido->status->getLabel() }}</x-filament::badge>
                        </div>

                        <p class="mt-2 text-gray-600 dark:text-gray-300">
                            Último dia na escola: <strong>{{ $pedido->data_efetiva->format('d/m/Y') }}</strong>
                        </p>

                        @if ($pedido->estaSolicitado())
                            <p class="mt-2 text-gray-600 dark:text-gray-300">A secretaria vai analisar o pedido e retornar.</p>
                        @elseif ($pedido->estaAprovado())
                            <p class="mt-2 text-gray-600 dark:text-gray-300">
                                Pedido aprovado. Baixe o distrato, assine e entregue na secretaria.
                                A escola conclui o cancelamento a partir de {{ $pedido->data_efetiva->format('d/m/Y') }}.
                            </p>
                        @elseif ($pedido->estaConcluido())
                            <p class="mt-2 text-gray-600 dark:text-gray-300">Cancelamento concluído em {{ $pedido->concluido_em?->format('d/m/Y') }}.</p>
                        @elseif ($pedido->status === \App\Enums\StatusCancelamentoMatricula::Recusado && $pedido->motivo_recusa)
                            <p class="mt-2 text-danger-700 dark:text-danger-400">Motivo: {{ $pedido->motivo_recusa }}</p>
                        @endif

                        @if ($pedido->temDistrato())
                            <p class="mt-2 text-gray-600 dark:text-gray-300">
                                Valor que ainda deve: <strong>{{ $reais((float) $pedido->valor_devido) }}</strong>
                                @if ((float) $pedido->valor_a_devolver > 0)
                                    · A escola devolve: <strong>{{ $reais((float) $pedido->valor_a_devolver) }}</strong>
                                    @if ($pedido->devolucao_realizada_em)
                                        (devolvido em {{ $pedido->devolucao_realizada_em->format('d/m/Y') }})
                                    @elseif ($pedido->estaConcluido())
                                        (devolução pendente)
                                    @endif
                                @endif
                                @if ((float) $pedido->multa_rescisoria > 0)
                                    · Multa rescisória: <strong>{{ $reais((float) $pedido->multa_rescisoria) }}</strong>
                                @endif
                            </p>
                        @endif

                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($pedido->temDistrato())
                                <x-filament::button tag="a" size="sm" color="gray" icon="heroicon-o-document-text" :href="route('cancelamentos-matricula.distrato', ['cancelamento' => $pedido->id])" target="_blank">
                                    Baixar distrato
                                </x-filament::button>
                            @endif

                            @if ($pedido->temDistratoAssinado())
                                <x-filament::button tag="a" size="sm" color="gray" icon="heroicon-o-paper-clip" :href="route('documentos.visualizar', ['path' => $pedido->distrato_assinado_path])" target="_blank">
                                    Ver cópia assinada
                                </x-filament::button>
                            @endif

                            @if ($pedido->estaSolicitado())
                                {{ ($this->retirarAction)(['pedido' => $pedido->id]) }}
                            @endif
                        </div>
                    </div>
                @endif
            </section>
        @endforeach
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
