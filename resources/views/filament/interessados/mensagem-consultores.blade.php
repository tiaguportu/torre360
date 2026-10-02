{{-- Modal da ação em lote "Enviar aos consultores (WhatsApp)": um link wa.me por consultor, com os leads dele numa só mensagem. --}}
<div class="space-y-4 text-left">
    @forelse ($grupos as $grupo)
        <div class="p-4 border border-gray-200 dark:border-white/10 rounded-lg">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-bold truncate">{{ $grupo['consultor']->name }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $grupo['interessados']->count() }} {{ $grupo['interessados']->count() === 1 ? 'lead' : 'leads' }}
                    </div>
                </div>

                <x-filament::button
                    tag="a"
                    :href="$grupo['url']"
                    target="_blank"
                    rel="noopener"
                    size="sm"
                    :color="$grupo['temTelefone'] ? 'success' : 'warning'"
                    icon="heroicon-m-arrow-top-right-on-square"
                >
                    Abrir WhatsApp
                </x-filament::button>
            </div>

            <ul class="mt-2 text-sm list-disc list-inside text-gray-700 dark:text-gray-300">
                @foreach ($grupo['interessados'] as $interessado)
                    <li>{{ $interessado->pessoa?->nome ?? 'Sem nome' }}</li>
                @endforeach
            </ul>

            @unless ($grupo['temTelefone'])
                <p class="mt-2 text-sm text-warning-700 dark:text-warning-400">
                    Este consultor está sem telefone cadastrado. O WhatsApp abrirá com a mensagem pronta e você escolhe o contato.
                </p>
            @endunless
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Nenhum dos leads selecionados tem consultor responsável.
        </p>
    @endforelse

    @if ($semConsultor->isNotEmpty())
        <div class="p-4 bg-warning-500/10 border border-warning-500/20 rounded-lg text-warning-700 dark:text-warning-400">
            <div class="flex items-center gap-2 font-bold mb-1">
                <x-filament::icon icon="heroicon-m-user-minus" class="h-5 w-5" />
                <span>{{ $semConsultor->count() }} {{ $semConsultor->count() === 1 ? 'lead sem consultor' : 'leads sem consultor' }}</span>
            </div>
            <p class="text-sm">
                {{ $semConsultor->map(fn ($interessado) => $interessado->pessoa?->nome ?? 'Sem nome')->join(', ', ' e ') }}
                não {{ $semConsultor->count() === 1 ? 'entrou' : 'entraram' }} na mensagem. Use <strong>Atribuir Consultor</strong> para definir o responsável.
            </p>
        </div>
    @endif
</div>
