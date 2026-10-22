{{-- Corpo do modal de confirmação do envio de uma comunicação em massa: total e lista de quem vai receber. --}}
<div class="space-y-3 text-left">
    @if ($destinatarios->isEmpty())
        <p class="text-sm font-bold text-danger-600 dark:text-danger-400">
            Nenhum destinatário encontrado para os filtros escolhidos. Revise os filtros antes de enviar.
        </p>
    @else
        <p class="text-sm">
            <strong>Destinatários:</strong>
            {{ $destinatarios->count() }} {{ $destinatarios->count() === 1 ? 'e-mail' : 'e-mails' }}
        </p>

        <ul class="max-h-64 divide-y divide-gray-200 overflow-y-auto rounded-lg border border-gray-200 text-sm dark:divide-white/10 dark:border-white/10">
            @foreach ($destinatarios as $pessoa)
                <li class="flex flex-col gap-0.5 px-3 py-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                    <span class="truncate font-medium text-gray-950 dark:text-white">{{ $pessoa->nome }}</span>
                    <span class="break-all text-gray-500 dark:text-gray-400">{{ $pessoa->email }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
