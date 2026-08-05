<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-document-check" icon-color="warning">
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
                @endphp
                <div class="flex flex-col justify-between p-5 bg-white dark:bg-gray-800 border border-amber-200 dark:border-amber-900/50 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-200">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <div class="flex items-center gap-2">
                                <span class="flex h-2.5 w-2.5 rounded-full bg-amber-500 animate-pulse"></span>
                                <span class="text-xs font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/60 px-2 py-0.5 rounded-md">
                                    Contrato #{{ $contrato->id }}
                                </span>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                                {{ $contrato->created_at?->format('d/m/Y') }}
                            </span>
                        </div>

                        <h4 class="font-bold text-gray-900 dark:text-white text-base mb-1">
                            {{ $aluno?->nome ?? 'Aluno' }}
                        </h4>

                        @if ($serie)
                            <p class="text-xs text-gray-600 dark:text-gray-400 mb-3">
                                Série / Curso: <strong>{{ $serie->nome }}</strong>
                            </p>
                        @endif

                        <div class="bg-gray-50 dark:bg-gray-900/50 p-2.5 rounded-lg border border-gray-100 dark:border-gray-800 mb-4">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Valor Total</div>
                            <div class="text-sm font-bold text-emerald-600 dark:text-emerald-400">
                                R$ {{ number_format((float) $contrato->valor_total, 2, ',', '.') }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end pt-3 border-t border-gray-100 dark:border-gray-700/60">
                        <x-filament::button
                            wire:click="assinarContrato({{ $contrato->id }})"
                            size="sm"
                            icon="heroicon-m-document-check"
                            color="warning"
                        >
                            Assinar Agora
                        </x-filament::button>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
