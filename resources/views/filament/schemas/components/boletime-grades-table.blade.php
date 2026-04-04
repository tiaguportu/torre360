@php
    $matricula = $getRecord()?->matriculas?->first();
    $aluno = $matricula?->pessoa;
    $turma = $matricula?->turma;
    $etapas = \App\Models\EtapaAvaliativa::orderBy('id')->get();
    $categorias = \App\Models\CategoriaAvaliacao::orderBy('id')->get();
    $notas = $matricula?->notas()
        ?->with(['avaliacao.disciplina', 'avaliacao.categoria', 'avaliacao.etapaAvaliativa'])
        ?->get() ?? collect();
    
    $disciplinas = $notas->pluck('avaliacao.disciplina')
        ->filter()
        ->unique('id')
        ->sortBy('nome');
@endphp

<div class="space-y-12 pb-12">
    @foreach($etapas as $etapa)
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
            <!-- Título da Etapa -->
            <div class="bg-gray-50/50 dark:bg-white/5 px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                <h3 class="text-sm font-black uppercase tracking-widest text-gray-900 dark:text-white">
                    {{ $etapa->nome }}
                </h3>
                <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-[2px]">Resumo de Aproveitamento</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs tabular-nums">
                    <thead>
                        <tr class="bg-gray-50/50 dark:bg-black/40">
                            <th rowspan="2" class="px-6 py-5 font-black uppercase border-r border-gray-100 dark:border-gray-800 min-w-[220px] text-gray-950 dark:text-gray-100 text-[10px] tracking-widest">Disciplina</th>
                            <th colspan="{{ count($categorias) + 2 }}" class="px-6 py-3 font-black uppercase text-center border-b border-gray-100 dark:border-gray-800 text-gray-950 dark:text-gray-100 text-[11px] tracking-[4px]">{{ $etapa->nome }}</th>
                            <th colspan="3" class="px-6 py-3 font-black uppercase text-center border-l border-b border-gray-100 dark:border-gray-800 text-gray-950 dark:text-gray-100 text-[11px] tracking-[4px]">Resultado</th>
                        </tr>
                        <tr class="bg-gray-50/20 dark:bg-black/20">
                            @foreach($categorias as $categoria)
                                @php
                                    $sigla = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', str_replace('-', ' ', $categoria->nome)))));
                                    $sigla = substr($sigla, 0, 3);
                                @endphp
                                <th class="px-3 py-4 font-black text-center border-r border-gray-100 dark:border-gray-800 w-20 text-gray-950 dark:text-gray-100" title="{{ $categoria->nome }}">
                                    {{ $sigla }}
                                </th>
                            @endforeach
                            <th class="px-3 py-4 font-black text-center border-r border-gray-100 dark:border-gray-800 w-20 text-primary-600 dark:text-primary-400">MP</th>
                            <th class="px-3 py-4 font-black text-center border-r border-gray-100 dark:border-gray-800 w-20 italic text-gray-400">MT</th>
                            <th class="px-3 py-4 font-black text-center border-r border-gray-100 dark:border-gray-800 w-20 text-gray-950 dark:text-gray-100">SP</th>
                            <th class="px-3 py-4 font-black text-center border-r border-gray-100 dark:border-gray-800 w-20 text-gray-950 dark:text-gray-100">FLT</th>
                            <th class="px-3 py-4 font-black text-center w-20 text-gray-950 dark:text-gray-100">FREQ.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($disciplinas as $disciplina)
                            @php
                                $notasFiltro = $notas->where('avaliacao.disciplina_id', $disciplina->id)
                                                    ->where('avaliacao.etapa_avaliativa_id', $etapa->id);
                                
                                $somaMediasCategorias = 0;
                                $categoriasComNota = 0;
                            @endphp
                            <tr class="hover:bg-gray-50/30 dark:hover:bg-white/5 transition-colors">
                                <td class="px-6 py-5 font-bold text-gray-900 dark:text-white border-r border-gray-100 dark:border-gray-800 text-sm">
                                    {{ $disciplina->nome }}
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
                                    <td class="px-3 py-4 text-center border-r border-gray-100 dark:border-gray-800 font-medium font-mono text-[11px]">
                                        @if($mediaCategoria !== null)
                                            <span @class([
                                                'text-danger-600 dark:text-danger-400 font-black px-1.5 py-0.5 rounded' => $mediaCategoria < 7,
                                                'bg-danger-50 dark:bg-danger-500/10' => $mediaCategoria < 7,
                                                'text-gray-900 dark:text-white font-bold' => $mediaCategoria >= 7,
                                            ])>
                                                {{ number_format($mediaCategoria, 1, ',', '.') }}
                                            </span>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-700">-</span>
                                        @endif
                                    </td>
                                @endforeach

                                <td class="px-3 py-4 text-center border-r border-gray-100 dark:border-gray-800 bg-primary-50/10 dark:bg-primary-400/5 font-mono">
                                    @php
                                        $mediaEtapa = $categoriasComNota > 0 ? ($somaMediasCategorias / $categoriasComNota) : null;
                                    @endphp
                                    @if($mediaEtapa !== null)
                                        <span @class([
                                            'font-black text-[13px] px-2 py-1 rounded-md shadow-sm',
                                            'text-danger-700 bg-danger-100 dark:bg-danger-500/20 dark:text-danger-400 border border-danger-200 dark:border-danger-500/30' => $mediaEtapa < 7,
                                            'text-primary-700 bg-primary-100/50 dark:bg-primary-400/20 dark:text-primary-400 border border-primary-200 dark:border-primary-400/30' => $mediaEtapa >= 7,
                                        ])>
                                            {{ number_format($mediaEtapa, 1, ',', '.') }}
                                        </span>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-700">-</span>
                                    @endif
                                </td>

                                <td class="px-3 py-4 text-center border-r border-gray-100 dark:border-gray-800 italic text-gray-500 dark:text-gray-400 text-[10px] font-mono">
                                    @php
                                        $mt = \App\Models\Nota::whereHas('avaliacao', fn($q) => $q->where('disciplina_id', $disciplina->id)->where('etapa_avaliativa_id', $etapa->id))
                                            ->whereHas('matricula', fn($q) => $q->where('turma_id', $matricula?->turma_id))
                                            ->avg('valor');
                                    @endphp
                                    {{ $mt ? number_format($mt, 1, ',', '.') : '-' }}
                                </td>

                                <td class="px-3 py-4 text-center border-r border-gray-100 dark:border-gray-800 font-bold text-gray-600 dark:text-gray-300 font-mono">
                                    @php
                                        // Somatório Parcial: Soma das Médias das Etapas (Bimestres) até o atual
                                        $sp = $notas->where('avaliacao.disciplina_id', $disciplina->id)
                                                  ->where('avaliacao.etapa_avaliativa_id', '<=', $etapa->id)
                                                  ->groupBy('avaliacao.etapa_avaliativa_id')
                                                  ->map(fn($items) => $items->avg('valor'))
                                                  ->sum();
                                    @endphp
                                    {{ $sp > 0 ? number_format($sp, 1, ',', '.') : '-' }}
                                </td>

                                <td class="px-3 py-4 text-center border-r border-gray-100 dark:border-gray-800 font-mono text-gray-600 dark:text-gray-400">
                                    @php
                                        $faltas = $matricula?->frequenciaEscolars()
                                            ?->where('disciplina_id', $disciplina->id)
                                            ?->where('presente', false)
                                            ?->count() ?? 0;
                                    @endphp
                                    {{ $faltas ?: '-' }}
                                </td>

                                <td class="px-3 py-4 text-center font-bold font-mono text-gray-900 dark:text-white">
                                    @php
                                        $totalAulas = $matricula?->frequenciaEscolars()?->where('disciplina_id', $disciplina->id)->count() ?? 0;
                                        $freq = $totalAulas > 0 ? (($totalAulas - $faltas) / $totalAulas) * 100 : 100;
                                    @endphp
                                    {{ number_format($freq, 0) }}%
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($categorias) + 6 }}" class="px-4 py-8 text-center bg-gray-50/50 dark:bg-white/5 text-gray-400 italic">
                                    Nenhum registro de avaliação disponível para esta etapa.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    <!-- Footer: Legendas e Canhoto -->
    <div class="mt-12 space-y-12">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <!-- Legendas colunadas -->
            <div class="space-y-6">
                <div class="flex items-center gap-3">
                    <div class="h-1 w-12 bg-primary-600 rounded-full"></div>
                    <h4 class="text-[10px] font-black uppercase tracking-[4px] text-gray-400">Legendas / Convenções</h4>
                </div>
                
                <div class="grid grid-cols-2 gap-x-8 gap-y-2">
                    <div class="space-y-2 text-[10px] uppercase font-bold tracking-wider text-gray-500 dark:text-gray-400">
                        @foreach($categorias as $categoria)
                            @php
                                $sigla = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', str_replace('-', ' ', $categoria->nome)))));
                                $sigla = substr($sigla, 0, 3);
                            @endphp
                            <p class="flex items-center gap-2 italic">
                                <span class="text-primary-600 dark:text-primary-400 font-black min-w-[30px]">{{ $sigla }}</span>
                                <span class="text-gray-400 dark:text-gray-600">|</span>
                                {{ $categoria->nome }}
                            </p>
                        @endforeach
                        <p class="flex items-center gap-2 italic">
                            <span class="text-primary-600 dark:text-primary-400 font-black min-w-[30px]">MP</span>
                            <span class="text-gray-400 dark:text-gray-600">|</span>
                            Média Parcial do Bimestre
                        </p>
                    </div>
                    <div class="space-y-2 text-[10px] uppercase font-bold tracking-wider text-gray-500 dark:text-gray-400">
                        <p class="flex items-center gap-2 italic">
                            <span class="text-primary-600 dark:text-primary-400 font-black min-w-[30px]">MT</span>
                            <span class="text-gray-400 dark:text-gray-600">|</span>
                            Média Global da Turma
                        </p>
                        <p class="flex items-center gap-2 italic">
                            <span class="text-primary-600 dark:text-primary-400 font-black min-w-[30px]">SP</span>
                            <span class="text-gray-400 dark:text-gray-600">|</span>
                            Somatório Parcial das Médias
                        </p>
                        <p class="flex items-center gap-2 italic">
                            <span class="text-primary-600 dark:text-primary-400 font-black min-w-[30px]">FLT</span>
                            <span class="text-gray-400 dark:text-gray-600">|</span>
                            Aulas Ausentes no Ano
                        </p>
                        <p class="flex items-center gap-2 italic">
                            <span class="text-primary-600 dark:text-primary-400 font-black min-w-[30px]">FREQ.</span>
                            <span class="text-gray-400 dark:text-gray-600">|</span>
                            Percentual de Frequência
                        </p>
                    </div>
                </div>
            </div>

            <!-- Notas e Critérios -->
            <div class="bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-2xl p-6 relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-8 transform translate-x-4 -translate-y-4 opacity-5 group-hover:opacity-10 transition-opacity">
                    <x-heroicon-o-information-circle class="h-32 w-32" />
                </div>
                <h4 class="text-[10px] font-black uppercase tracking-[4px] text-gray-400 mb-4">Informações de Aprovação</h4>
                <div class="space-y-4 relative z-10">
                    <p class="text-[11px] font-medium text-gray-600 dark:text-gray-300 leading-relaxed italic">
                        <span class="text-danger-600 dark:text-danger-400 font-black">NOTAS VERMELHAS:</span> Indicam que o(a) aluno(a) estará em recuperação na disciplina. Para o 1º bimestre é esperado nota maior ou igual a <span class="font-bold underline decoration-danger-500/50 decoration-2">7,0</span>.
                    </p>
                    <div class="h-px bg-gray-200 dark:bg-gray-700"></div>
                    <p class="text-[11px] font-medium text-gray-600 dark:text-gray-300 leading-relaxed italic">
                        <span class="text-primary-600 dark:text-primary-400 font-black">CRITÉRIO DE APROVAÇÃO:</span> Frequência anual superior a <span class="font-bold">75%</span> e somatório/média anual superior a <span class="font-bold">28,0</span>.
                    </p>
                </div>
            </div>
        </div>

        <!-- Canhoto Separador -->
        <div class="pt-8 border-t-4 border-dotted border-gray-200 dark:border-gray-800">
            <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl p-8 relative">
                <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-white dark:bg-gray-950 px-6 py-1 border border-gray-100 dark:border-gray-800 rounded-full">
                    <span class="text-[9px] font-black uppercase tracking-[3px] text-gray-400">Comprovante de Entrega</span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-end">
                    <div class="space-y-6">
                        <div class="space-y-1">
                             <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Aluno(a)</label>
                             <p class="text-sm font-black text-gray-900 dark:text-white uppercase">{{ $aluno?->nome ?? $getRecord()?->nome }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Período/Ano</label>
                                <p class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">{{ $turma?->periodoLetivo?->ano ?? now()->year }}</p>
                            </div>
                            <div class="space-y-1 text-right">
                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Data</label>
                                <p class="text-xs font-bold text-gray-700 dark:text-gray-300 font-mono">____/____/_______</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-8">
                        <div class="relative pt-6">
                            <div class="border-t-2 border-gray-900 dark:border-white w-full mx-auto"></div>
                            <p class="text-center text-[10px] font-black text-gray-400 uppercase tracking-widest mt-2">Assinatura do Responsável</p>
                        </div>
                        <p class="text-center text-[9px] font-medium text-gray-400 italic">Favor devolver este canhoto à secretaria do colégio devidamente assinado.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


