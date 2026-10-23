{{-- Links de assinatura de cada assinante (token de quem assina: só para quem pode enviar o documento). --}}
<div class="space-y-3 text-sm">
    <p class="text-gray-600 dark:text-gray-300">
        Cada assinante também recebeu o link por e-mail do Assinafy. Use estes endereços se a pessoa não achar o e-mail
        (por exemplo, para mandar pelo WhatsApp). Cada link é só daquela pessoa: não compartilhe com outras.
    </p>

    <ul class="divide-y divide-gray-100 dark:divide-white/5">
        @foreach ($assinantes as $assinante)
            <li class="py-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <span class="font-medium">{{ $assinante->nome }}</span>
                        @if ($assinante->papel)
                            <span class="text-xs text-gray-500">· {{ $assinante->papel }}</span>
                        @endif
                        <div class="text-xs text-gray-500">{{ $assinante->email }}</div>
                    </div>
                    <x-filament::badge :color="$assinante->status->getColor()">{{ $assinante->status->getLabel() }}</x-filament::badge>
                </div>

                @if ($assinante->status === \App\Enums\StatusAssinante::Pendente && $assinante->link_assinatura)
                    <input
                        type="text"
                        readonly
                        value="{{ $assinante->link_assinatura }}"
                        onclick="this.select()"
                        class="mt-1 w-full rounded-lg border-gray-300 text-xs dark:border-white/20 dark:bg-gray-900"
                    >
                @endif
            </li>
        @endforeach
    </ul>
</div>
