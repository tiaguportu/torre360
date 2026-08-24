<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-document-check" icon-color="primary">
        <x-slot name="heading">
            Contratos Pendentes de Assinatura Digital
        </x-slot>

        <x-slot name="description">
            Você possui contratos aguardando sua assinatura digital via Assinafy. Clique no botão de assinatura para concluir o processo de forma rápida e segura.
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
            @foreach ($this->getContratosPendentes() as $contrato)
                @php
                    $aluno = $contrato->matricula?->pessoa;
                    $serie = $contrato->matricula?->turma?->serie;
                    $assunto = $contrato->templateContrato?->nome ?? 'Contrato de Prestação de Serviços Educacionais';
                @endphp
                <div class="flex flex-col justify-between p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-200">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <div class="flex items-center gap-2">
                                <span class="flex h-2 w-2 rounded-full bg-primary-500 animate-pulse"></span>
                                <x-filament::badge color="warning" size="sm">
                                    Contrato #{{ $contrato->id }}
                                </x-filament::badge>
                            </div>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                {{ $contrato->created_at?->format('d/m/Y') }}
                            </span>
                        </div>

                        {{-- Título do Assunto --}}
                        <h4 class="mb-2 text-base font-bold text-gray-900 dark:text-white line-clamp-2" title="{{ $assunto }}">
                            {{ $assunto }}
                        </h4>

                        {{-- Informações do Aluno e Série --}}
                        <div class="space-y-1.5 mb-3 text-xs text-gray-600 dark:text-gray-400">
                            <div class="flex items-center gap-1.5">
                                <x-filament::icon icon="heroicon-m-user" class="w-4 h-4 text-gray-400 dark:text-gray-500 flex-shrink-0" />
                                <span class="truncate">
                                    Aluno: <strong class="text-gray-900 dark:text-gray-200">{{ $aluno?->nome ?? 'Aluno' }}</strong>
                                </span>
                            </div>

                            @if ($serie)
                                <div class="flex items-center gap-1.5">
                                    <x-filament::icon icon="heroicon-m-academic-cap" class="w-4 h-4 text-gray-400 dark:text-gray-500 flex-shrink-0" />
                                    <span class="truncate">
                                        Série: <strong class="text-gray-900 dark:text-gray-200">{{ $serie->nome }}</strong>
                                    </span>
                                </div>
                            @endif
                        </div>

                        <div class="p-2.5 mb-4 rounded-lg border bg-gray-50 dark:bg-gray-900/50 border-gray-100 dark:border-gray-800">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Valor Total</div>
                            <div class="text-sm font-bold text-gray-900 dark:text-white">
                                R$ {{ number_format((float) $contrato->valor_total, 2, ',', '.') }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-4 mt-auto border-t border-gray-100 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Aguardando assinatura
                        </div>

                        <x-filament::button
                            wire:click="assinarContrato({{ $contrato->id }})"
                            size="sm"
                            icon="heroicon-m-pencil-square"
                            color="primary"
                        >
                            Assinar Agora
                        </x-filament::button>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
