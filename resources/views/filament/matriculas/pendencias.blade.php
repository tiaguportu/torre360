@php
    use App\Filament\Resources\Contratos\ContratoResource;
    use App\Filament\Resources\Matriculas\Pages\DocumentosMatricula;
    use App\Filament\Resources\Pessoas\PessoaResource;
@endphp

{{-- Detalhe das pendências de uma matrícula (modal da coluna "Pendências"). --}}
<div class="space-y-3 text-left">
    @if ($pendencias->semResponsavel)
        <div class="p-4 bg-danger-500/10 border border-danger-500/20 rounded-lg text-danger-700 dark:text-danger-400">
            <div class="flex items-center gap-2 font-bold mb-1">
                <x-filament::icon icon="heroicon-m-user-minus" class="h-5 w-5" />
                <span>Responsável não informado</span>
            </div>
            <p class="text-sm">
                Este aluno não possui nenhum <strong>Pai, Mãe ou Responsável</strong> associado ao seu cadastro de pessoa.
            </p>
            <div class="mt-2">
                <x-filament::link
                    :href="PessoaResource::getUrl('edit', ['record' => $matricula->pessoa_id])"
                    target="_blank"
                    size="sm"
                    color="danger"
                    icon="heroicon-m-arrow-top-right-on-square"
                >
                    Associar responsáveis na ficha do aluno
                </x-filament::link>
            </div>
        </div>
    @endif

    @foreach ($pendencias->cadastrosIncompletos as $item)
        <div class="p-4 bg-warning-500/10 border border-warning-500/20 rounded-lg text-warning-700 dark:text-warning-400">
            <div class="flex items-center gap-2 font-bold mb-1">
                <x-filament::icon icon="heroicon-m-identification" class="h-5 w-5" />
                <span>Cadastro incompleto ({{ $item['tipo'] }})</span>
            </div>
            <p class="text-sm">
                O cadastro de <strong>{{ $item['pessoa']->nome ?: 'Sem nome' }}</strong> está sem:
                <strong>{{ collect($item['campos'])->join(', ', ' e ') }}</strong>.
            </p>
            <div class="mt-2">
                <x-filament::link
                    :href="PessoaResource::getUrl('edit', ['record' => $item['pessoa']->id])"
                    target="_blank"
                    size="sm"
                    color="warning"
                    icon="heroicon-m-arrow-top-right-on-square"
                >
                    Editar os dados cadastrais desta pessoa
                </x-filament::link>
            </div>
        </div>
    @endforeach

    @if ($pendencias->temPendenciaDocumental())
        <div class="p-4 bg-danger-500/10 border border-danger-500/20 rounded-lg text-danger-700 dark:text-danger-400">
            <div class="flex items-center gap-2 font-bold mb-1">
                <x-filament::icon icon="heroicon-m-document-text" class="h-5 w-5" />
                <span>Documentos pendentes</span>
            </div>

            @if ($pendencias->documentosFaltantes->isNotEmpty())
                <p class="text-sm font-semibold mt-2">Faltando:</p>
                <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 mt-1">
                    @foreach ($pendencias->documentosFaltantes as $documento)
                        <li>{{ $documento->nome }}</li>
                    @endforeach
                </ul>
            @endif

            @if ($pendencias->documentosRejeitados->isNotEmpty())
                <p class="text-sm font-semibold mt-2">Rejeitados (é necessário reenviar):</p>
                <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 mt-1">
                    @foreach ($pendencias->documentosRejeitados as $inserido)
                        <li>
                            {{ $inserido->tipoDocumento?->nome }}
                            @if (filled($inserido->observacoes))
                                <span class="italic">(Motivo: {{ $inserido->observacoes }})</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="mt-3">
                <x-filament::link
                    :href="DocumentosMatricula::getUrl(['record' => $matricula])"
                    target="_blank"
                    size="sm"
                    color="danger"
                    icon="heroicon-m-arrow-top-right-on-square"
                >
                    Gerenciar os documentos da matrícula
                </x-filament::link>
            </div>
        </div>
    @endif

    @if ($pendencias->contratoNaoGerado)
        <div class="p-4 bg-warning-500/10 border border-warning-500/20 rounded-lg text-warning-700 dark:text-warning-400">
            <div class="flex items-center gap-2 font-bold mb-1">
                <x-filament::icon icon="heroicon-m-document-plus" class="h-5 w-5" />
                <span>Contrato não gerado</span>
            </div>
            <p class="text-sm">
                Esta matrícula ainda não tem contrato. Use a ação <strong>Gerar contrato</strong> no menu
                <strong>⋮ Mais ações</strong> da linha (disponível quando o aluno tem responsável) ou crie o contrato manualmente.
            </p>
            <div class="mt-2">
                <x-filament::link
                    :href="ContratoResource::getUrl('create')"
                    target="_blank"
                    size="sm"
                    color="warning"
                    icon="heroicon-m-arrow-top-right-on-square"
                >
                    Criar contrato manualmente
                </x-filament::link>
            </div>
        </div>
    @endif

    @if ($pendencias->contratoNaoAssinado && $matricula->contrato)
        <div class="p-4 bg-info-500/10 border border-info-500/20 rounded-lg text-info-700 dark:text-info-400">
            <div class="flex items-center gap-2 font-bold mb-1">
                <x-filament::icon icon="heroicon-m-pencil-square" class="h-5 w-5" />
                <span>Contrato não assinado</span>
            </div>
            <p class="text-sm">
                O contrato foi gerado, mas ainda não foi assinado pelos responsáveis.
            </p>
            <div class="mt-2">
                <x-filament::link
                    :href="ContratoResource::getUrl('edit', ['record' => $matricula->contrato])"
                    target="_blank"
                    size="sm"
                    color="info"
                    icon="heroicon-m-arrow-top-right-on-square"
                >
                    Abrir o contrato
                </x-filament::link>
            </div>
        </div>
    @endif

    @unless ($pendencias->temPendencias())
        <div class="p-4 bg-success-500/10 border border-success-500/20 rounded-lg text-success-700 dark:text-success-400">
            <div class="flex items-center gap-2 font-bold">
                <x-filament::icon icon="heroicon-m-check-circle" class="h-5 w-5" />
                <span>Esta matrícula está em dia.</span>
            </div>
        </div>
    @endunless
</div>
