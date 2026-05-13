<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-clipboard-document-list" icon-color="primary">
        <x-slot name="heading">
            Questionários Pendentes
        </x-slot>

        <x-slot name="description">
            Você possui questionários ativos que ainda não foram respondidos. Sua participação é muito importante!
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
            @foreach ($this->getQuestionarios() as $questionario)
                <div class="flex flex-col justify-between p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-200">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="flex h-2 w-2 rounded-full bg-primary-500 animate-pulse"></span>
                            <h4 class="font-bold text-gray-900 dark:text-white line-clamp-1">
                                {{ $questionario->titulo }}
                            </h4>
                        </div>
                        
                        <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2 mb-4">
                            {{ strip_tags($questionario->descricao) ?: 'Nenhuma descrição fornecida.' }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between mt-auto pt-4 border-t border-gray-100 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-500">
                            @if ($questionario->fim_aplicacao)
                                Expira em: {{ $questionario->fim_aplicacao->format('d/m/Y') }}
                            @else
                                Sem prazo definido
                            @endif
                        </div>

                        <x-filament::button 
                            href="{{ \App\Filament\Resources\Questionarios\QuestionarioResource::getUrl('responder', ['record' => $questionario->id]) }}"
                            tag="a"
                            size="sm"
                            icon="heroicon-m-pencil-square"
                            color="primary"
                        >
                            Responder
                        </x-filament::button>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
