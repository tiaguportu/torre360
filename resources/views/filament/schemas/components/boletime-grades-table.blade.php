@php
    $matricula = $getRecord();
    $etapas = \App\Models\EtapaAvaliativa::orderBy('id')->get();
    $categorias = \App\Models\CategoriaAvaliacao::orderBy('id')->get();
    $notas = $matricula->notas()
        ->with(['avaliacao.disciplina', 'avaliacao.categoria', 'avaliacao.etapaAvaliativa'])
        ->get();
    
    $disciplinas = $notas->pluck('avaliacao.disciplina')
        ->filter()
        ->unique('id')
        ->sortBy('nome');
@endphp

<div class="relative">
    <!-- Header da Matrícula -->
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded-2xl p-6 mb-8 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="flex items-center gap-5">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">
                        {{ $matricula->turma?->nome }}
                    </h3>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">
                        <span class="flex items-center gap-1.5 focus:outline-none">
                            Período: {{ $matricula->turma?->periodoLetivo?->ano }}
                        </span>
                        <span class="hidden md:inline w-1 h-1 bg-gray-300 dark:bg-gray-700 rounded-full"></span>
                        <span class="flex items-center gap-1.5 text-primary-600 dark:text-primary-400">
                            Matrícula #{{ $matricula->id }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <x-filament::badge 
                    :color="$matricula->situacaoMatricula?->nome === 'Ativa' ? 'success' : 'warning'" 
                    size="lg" 
                    class="px-4 py-1.5 text-sm font-bold uppercase tracking-wider"
                >
                    {{ $matricula->situacaoMatricula?->nome ?? 'Ativa' }}
                </x-filament::badge>
            </div>
        </div>
    </div>

    <!-- Etapas -->
    <div class="grid grid-cols-1 gap-8">
        @foreach($etapas as $etapa)
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded-2xl overflow-hidden shadow-sm">
                <div class="bg-gray-50/80 dark:bg-white/5 px-6 py-4 border-b border-gray-200 dark:border-white/10 flex items-center justify-between">
                    <h4 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        {{ $etapa->nome }}
                    </h4>
                    <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">Resumo de Aproveitamento</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-start border-collapse">
                        <thead>
                            <tr class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50/50 dark:bg-black/20">
                                <th class="px-6 py-4 text-start min-w-[240px]">Disciplina</th>
                                @foreach($categorias as $categoria)
                                    <th class="px-3 py-4 text-center border-l border-gray-100 dark:border-white/5">{{ $categoria->nome }}</th>
                                @endforeach
                                <th class="px-6 py-4 text-center bg-primary-50/30 dark:bg-primary-500/5 text-primary-600 dark:text-primary-400 border-l border-primary-100 dark:border-primary-500/10">Média Etapa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @forelse($disciplinas as $disciplina)
                                @php
                                    $notasFiltro = $notas->where('avaliacao.disciplina_id', $disciplina->id)
                                                        ->where('avaliacao.etapa_avaliativa_id', $etapa->id);
                                    
                                    $somaMediasCategorias = 0;
                                    $categoriasComNota = 0;
                                @endphp
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-white/5 transition-all group">
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-bold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                                {{ $disciplina->nome }}
                                            </span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                {{ $disciplina->areaConhecimento?->nome }}
                                            </span>
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
                                        <td class="px-3 py-4 text-center border-l border-gray-50 dark:border-white/5">
                                            @if($mediaCategoria !== null)
                                                <span @class([
                                                    'text-sm font-bold tabular-nums',
                                                    'text-danger-600 dark:text-danger-400 px-2 py-0.5 rounded-lg bg-danger-50 dark:bg-danger-500/10' => $mediaCategoria < 6,
                                                    'text-success-600 dark:text-success-400 px-2 py-0.5 rounded-lg bg-success-50 dark:bg-success-500/10' => $mediaCategoria >= 6,
                                                ])>
                                                    {{ number_format($mediaCategoria, 1, ',', '.') }}
                                                </span>
                                            @else
                                                <span class="text-gray-300 dark:text-gray-700">-</span>
                                            @endif
                                        </td>
                                    @endforeach

                                    <td class="px-6 py-4 text-center bg-primary-50/20 dark:bg-primary-500/5 border-l border-primary-50 dark:border-primary-500/10">
                                        @php
                                            $mediaEtapa = $categoriasComNota > 0 ? ($somaMediasCategorias / $categoriasComNota) : null;
                                        @endphp
                                        @if($mediaEtapa !== null)
                                            <div class="flex justify-center">
                                                <span @class([
                                                    'text-base font-extrabold tabular-nums px-3 py-1 rounded-full shadow-sm ring-1 ring-inset',
                                                    'text-danger-700 bg-danger-50 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-400' => $mediaEtapa < 6,
                                                    'text-success-700 bg-success-50 ring-success-600/20 dark:bg-success-500/10 dark:text-success-400' => $mediaEtapa >= 6,
                                                ])>
                                                    {{ number_format($mediaEtapa, 1, ',', '.') }}
                                                </span>
                                            </div>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600 font-medium">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 2 + count($categorias) }}" class="px-6 py-10 text-center bg-gray-50/30 dark:bg-black/10">
                                        <div class="flex flex-col items-center gap-2">
                                            <p class="text-sm text-gray-400 italic font-medium">Nenhum registro encontrado para esta etapa.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
</div>

