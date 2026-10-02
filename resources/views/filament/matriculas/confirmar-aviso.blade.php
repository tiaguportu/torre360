{{-- Corpo dos modais de confirmação de aviso por e-mail (pendência de documentos e preceptoria). --}}
@php
    // Classes completas (e não montadas por interpolação) para o Tailwind conseguir detectá-las.
    $classesUltimoEnvio = $cor === 'success'
        ? 'bg-success-500/10 border-success-500/20 text-success-700 dark:text-success-400'
        : 'bg-warning-500/10 border-warning-500/20 text-warning-700 dark:text-warning-400';
@endphp

<div class="space-y-4 text-left">
    @if ($destinatarios->isEmpty())
        <p class="text-sm font-bold text-danger-600 dark:text-danger-400">
            Erro: nenhum e-mail encontrado para o aluno ou responsáveis desta matrícula.
        </p>
    @else
        @if ($ultimoEnvio)
            <div class="p-3 border rounded-lg text-sm italic {{ $classesUltimoEnvio }}">
                <strong>Último aviso enviado em:</strong> {{ $ultimoEnvio->format('d/m/Y H:i') }}
            </div>
        @endif

        @if (filled($mensagem))
            <p class="text-sm">{{ $mensagem }}</p>
        @endif

        <div class="text-sm">
            <strong>Destinatários:</strong><br>
            <span class="text-gray-500 dark:text-gray-400">{{ $destinatarios->join(', ') }}</span>
        </div>

        @if ($faltantes->isNotEmpty())
            <div class="text-sm">
                <strong class="text-danger-600 dark:text-danger-400">Documentos faltando:</strong>
                <ul class="list-disc list-inside text-gray-500 dark:text-gray-400">
                    @foreach ($faltantes as $documento)
                        <li>{{ $documento->nome }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($rejeitados->isNotEmpty())
            <div class="text-sm">
                <strong class="text-warning-600 dark:text-warning-400">Documentos rejeitados (necessário reenvio):</strong>
                <ul class="list-disc list-inside text-gray-500 dark:text-gray-400">
                    @foreach ($rejeitados as $inserido)
                        <li>
                            {{ $inserido->tipoDocumento?->nome }}
                            @if (filled($inserido->observacoes))
                                <span class="italic">(Motivo: {{ $inserido->observacoes }})</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</div>
