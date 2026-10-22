@php
    $propostas = collect($sugestao['propostas']);
@endphp

<div class="space-y-4 text-sm">
    @foreach ($sugestao['avisos'] as $aviso)
        <div class="rounded-lg border border-warning-300 bg-warning-50 p-3 text-warning-800 dark:border-warning-700 dark:bg-warning-950/30 dark:text-warning-200">{{ $aviso }}</div>
    @endforeach

    @if ($propostas->isEmpty())
        <p class="text-gray-600 dark:text-gray-300">Não há horário para colocar agora: a grade já está completa, ou o que falta não encontrou lugar (veja abaixo).</p>
    @else
        <div>
            <div class="mb-1 font-medium">{{ $propostas->count() }} horário(s) a colocar</div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <th class="py-2 pe-3">Dia</th>
                            <th class="py-2 pe-3">Horário</th>
                            <th class="py-2 pe-3">Disciplina</th>
                            <th class="py-2">Professor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($propostas as $proposta)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="py-1.5 pe-3">{{ $dias[$proposta['dia_semana']] ?? $proposta['dia_semana'] }}</td>
                                <td class="py-1.5 pe-3 whitespace-nowrap">{{ $proposta['tempo_ordem'] }}º · {{ $proposta['hora_inicio'] }}–{{ $proposta['hora_fim'] }}</td>
                                <td class="py-1.5 pe-3">{{ $proposta['disciplina'] }}</td>
                                <td class="py-1.5">{{ $proposta['professor'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($sugestao['nao_alocadas'] !== [])
        <div class="rounded-lg border border-danger-300 bg-danger-50 p-3 text-danger-800 dark:border-danger-700 dark:bg-danger-950/30 dark:text-danger-200">
            <div class="font-medium">Aulas que não acharam lugar</div>
            <ul class="list-disc pl-5">
                @foreach ($sugestao['nao_alocadas'] as $item)
                    <li><strong>{{ $item['disciplina'] }}</strong> ({{ $item['quantidade'] }}): {{ $item['motivo'] }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
