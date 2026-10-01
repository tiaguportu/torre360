@php
    /** @var \App\Models\VideoTutorial $video */
@endphp
<x-filament::section>
    <x-slot name="heading">
        <div class="flex items-center gap-2">
            <x-filament::icon
                icon="heroicon-m-play-circle"
                class="h-5 w-5 text-primary-500"
            />
            <span>{{ $video->titulo }}</span>
        </div>
    </x-slot>

    @if($video->url_assistir)
        <x-slot name="headerEnd">
            <x-filament::button
                color="gray"
                icon="heroicon-m-arrow-top-right-on-square"
                icon-position="after"
                tag="a"
                href="{{ $video->url_assistir }}"
                target="_blank"
                size="sm"
                variant="ghost"
            >
                Abrir em nova aba
            </x-filament::button>
        </x-slot>
    @endif

    @if($video->descricao)
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">{{ $video->descricao }}</p>
    @endif

    <div class="flex justify-center bg-gray-50 dark:bg-gray-900/50 rounded-xl overflow-hidden border border-gray-100 dark:border-gray-800 p-2 min-h-[300px]">
        @if($video->arquivo)
            <video controls preload="metadata" class="max-h-[500px] w-full rounded-lg bg-black">
                <source src="{{ $video->arquivo_url }}" type="video/mp4">
                Seu navegador não suporta a exibição de vídeo.
            </video>
        @elseif($video->url_embed)
            <iframe
                src="{{ $video->url_embed }}"
                class="w-full aspect-video rounded-lg border-0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
            ></iframe>
        @elseif($video->url_externo)
            <div class="flex flex-col items-center justify-center p-12 text-gray-400 gap-3">
                <x-filament::icon
                    icon="heroicon-m-play-circle"
                    class="w-16 h-16 opacity-50"
                />
                <a href="{{ $video->url_externo }}" target="_blank" class="text-primary-600 dark:text-primary-400 underline">
                    Abrir vídeo em nova aba
                </a>
            </div>
        @else
            <div class="flex flex-col items-center justify-center p-12 text-gray-400">
                <x-filament::icon
                    icon="heroicon-m-eye-slash"
                    class="w-16 h-16 mb-4 opacity-50"
                />
                <p class="text-base font-medium">Vídeo não disponível.</p>
            </div>
        @endif
    </div>
</x-filament::section>
