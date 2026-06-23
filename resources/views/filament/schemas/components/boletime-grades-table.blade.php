@php
    use Filament\Infolists\Components\TextEntry;
    $etapas = $schemaComponent->getEtapasComNotas();
    $matricula = $schemaComponent->getMatricula();
@endphp

<div class="mt-6 space-y-8">
    @if ($etapas->isEmpty())
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Nenhuma nota registrada até o momento.</p>
        </div>
    @else
        @foreach ($etapas as $etapa)
            <div class="fi-boletim-etapa-section">
                @livewire(\App\Livewire\BoletimEtapaTable::class, ['matriculaId' => $matricula->id, 'etapaId' => $etapa->id], key($etapa->id))
            </div>
        @endforeach

        @php
            $ultimaEtapaDisponivel = $etapas->last();
            $frequenciasStr = [];
            if ($ultimaEtapaDisponivel) {
                $boletimService = app(\App\Services\BoletimService::class);
                $dados = $boletimService->getDadosBoletim($matricula, $ultimaEtapaDisponivel->id);
                if (!empty($dados['etapas'])) {
                    $dadosEtapa = $dados['etapas'][0];
                    foreach ($dadosEtapa['linhas'] as $linha) {
                        $freq = $linha['frequencia'] !== null ? number_format($linha['frequencia'], 1, ',', '.') . '%' : '—';
                        $frequenciasStr[] = $linha['disciplina']->nome . '=' . $freq;
                    }
                }
            }
        @endphp

        {{-- Legenda Unificada --}}
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
            {{-- $schemaComponent->getLegendInfolist() --}}
            
            @if(!empty($frequenciasStr))
                <div class="mb-4 text-xs text-gray-700 dark:text-gray-300">
                    <span class="font-semibold text-gray-900 dark:text-white">Frequências acumuladas:</span> {{ implode('; ', $frequenciasStr) }}.
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-6 text-xs text-gray-600 dark:text-gray-400 @if(!empty($frequenciasStr)) border-t border-gray-200 pt-4 dark:border-gray-700 @endif">
                <div class="flex items-center gap-2">
                    <span class="text-gray-600 dark:text-gray-400">As notas riscadas indicam avaliações substituídas por
                        outras de maior valor.</span>
                </div>

            </div>
        </div>
    @endif
</div>