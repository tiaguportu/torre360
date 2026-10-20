{{-- Modal da ação "Redes sociais" da listagem de interessados: usado quando o lead tem mais de um perfil (com um só, o botão já abre direto). --}}
<div class="space-y-2 text-left">
    @foreach ($redes as $rede)
        <div class="flex items-center justify-between gap-3 p-3 border border-gray-200 dark:border-white/10 rounded-lg">
            <div class="min-w-0">
                <div class="font-bold">{{ $rede['rotulo'] }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $rede['url'] }}</div>
            </div>

            <x-filament::button
                tag="a"
                :href="$rede['url']"
                target="_blank"
                rel="noopener noreferrer"
                size="sm"
                icon="heroicon-m-arrow-top-right-on-square"
            >
                Abrir
            </x-filament::button>
        </div>
    @endforeach
</div>
