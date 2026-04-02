<div class="space-y-12">
    @foreach($getRecord()->matriculas as $matricula)
        @php
            $etapas = \App\Models\EtapaAvaliativa::orderBy('id')->get();
            $categorias = \App\Models\CategoriaAvaliacao::orderBy('id')->get();
            $notas = $matricula->notas()
                ->with(['avaliacao.disciplina', 'avaliacao.categoria', 'avaliacao.etapaAvaliativa'])
                ->get();
            
            // Filtra disciplinas que possuem alguma nota nesta matrícula
            $disciplinas = $notas->pluck('avaliacao.disciplina')
                ->filter()
                ->unique('id')
                ->sortBy('nome');
        @endphp

        <div class="space-y-6">
            <div class="border-b border-gray-200 dark:border-white/10 pb-4 mb-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-primary-500/10 rounded-lg">
                            <x-heroicon-o-academic-cap class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-950 dark:text-white leading-tight">
                                {{ $matricula->turma?->nome }}
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Matrícula: <span class="font-medium">#{{ $matricula->id }}</span>
                                - Ano Letivo {{$matricula->turma?->periodoLetivo?->ano}}
                            </p>
                        </div>
                    </div>
                    <x-filament::badge :color="$matricula->situacaoMatricula?->nome === 'Ativa' ? 'success' : 'warning'" size="md" icon="heroicon-m-check-badge">
                        {{ $matricula->situacaoMatricula?->nome ?? 'Ativa' }}
                    </x-filament::badge>
                </div>
            </div>

            @foreach($etapas as $etapa)
                <x-filament::section collapsible>
                    <x-slot name="heading">
                        {{ $etapa->nome }}
                    </x-slot>

                    <div class="overflow-x-auto -mx-6 -mb-6">
                        <table class="w-full text-start divide-y divide-gray-200 dark:divide-white/5">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-white/5">
                                    <th class="px-6 py-4 text-start text-sm font-semibold text-gray-950 dark:text-white border-b border-gray-200 dark:border-white/10 w-1/4">Disciplina</th>
                                    @foreach($categorias as $categoria)
                                        <th class="px-3 py-4 text-center text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-l border-b border-gray-200 dark:border-white/10">
                                            {{ $categoria->nome }}
                                        </th>
                                    @endforeach
                                    <th class="px-6 py-4 text-center text-sm font-bold text-primary-600 dark:text-primary-400 bg-primary-50/50 dark:bg-primary-950/20 border-l border-b border-gray-200 dark:border-white/10">
                                        Média Etapa
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                                @forelse($disciplinas as $disciplina)
                                    @php
                                        $notasFiltro = $notas->where('avaliacao.disciplina_id', $disciplina->id)
                                                            ->where('avaliacao.etapa_avaliativa_id', $etapa->id);
                                        
                                        $somaMediasCategorias = 0;
                                        $categoriasComNota = 0;
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $disciplina->nome }}</span>
                                                <span class="text-xs text-gray-500 line-clamp-1">{{ $disciplina->areaConhecimento?->nome }}</span>
                                            </div>
                                        </td>

                                        @foreach($categorias as $categoria)
                                            @php
                                                $notasNaCategoria = $notasFiltro->where('avaliacao.categoria_avaliacao_id', $categoria->id);
                                                $mediaCategoria = $notasNaCategoria->count() > 0 ? $notasNaCategoria->avg('valor') : null;
                                                
                                                if ($mediaCategoria !== null) {
                                                    $somaMediasCategorias += $mediaCategoria;
                                                    $categoriasComNota++;
                                                }
                                            @endphp
                                            <td class="px-3 py-4 text-center border-l border-gray-100 dark:border-white/5">
                                                @if($mediaCategoria !== null)
                                                    <span @class([
                                                        'text-sm font-medium transition-all group-hover:scale-110',
                                                        'text-danger-600 dark:text-danger-400' => $mediaCategoria < 6,
                                                        'text-success-600 dark:text-success-400 font-bold' => $mediaCategoria >= 6,
                                                    ])>
                                                        {{ number_format($mediaCategoria, 1, ',', '.') }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-200 dark:text-gray-700 font-light">-</span>
                                                @endif
                                            </td>
                                        @endforeach

                                        <td class="px-6 py-4 text-center bg-primary-50/20 dark:bg-primary-950/10 border-l border-gray-100 dark:border-white/5">
                                            @php
                                                $mediaEtapa = $categoriasComNota > 0 ? ($somaMediasCategorias / $categoriasComNota) : null;
                                            @endphp
                                            @if($mediaEtapa !== null)
                                                <x-filament::badge
                                                    :color="$mediaEtapa < 6 ? 'danger' : 'success'"
                                                    size="md"
                                                    class="font-extrabold shadow-sm"
                                                >
                                                    {{ number_format($mediaEtapa, 1, ',', '.') }}
                                                </x-filament::badge>
                                            @else
                                                <span class="text-gray-300 dark:text-gray-600">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ 2 + count($categorias) }}" class="px-6 py-12 text-center text-gray-500 italic bg-gray-50/50 dark:bg-black/5">
                                            <div class="flex flex-col items-center gap-3">
                                                <x-heroicon-o-document-magnifying-glass class="h-10 w-10 text-gray-300 dark:text-gray-700" />
                                                <p>Nenhuma informação lançada para esta etapa.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endforeach
</div>

