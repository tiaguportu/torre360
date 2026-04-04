@php
    $etapas = $schemaComponent->getEtapas();
    $categorias = $schemaComponent->getCategorias();
    $notas = $schemaComponent->getNotas();
    $disciplinas = $schemaComponent->getDisciplinas($notas);
    $matricula = $schemaComponent->getMatricula();
    $aluno = $matricula?->pessoa;
    $turma = $matricula?->turma;
    $notasAgrupadasTurma = $schemaComponent->getNotasAgrupadasTurma();
    $faltasAgrupadas = $schemaComponent->getFaltasAgrupadas();
    $totalAulasAgrupadas = $schemaComponent->getTotalAulasAgrupadas();
@endphp

<div class="space-y-8">
    @forelse($etapas as $etapa)
        <x-filament::section>
            <x-slot name="heading">
                <span class="uppercase tracking-widest text-sm">{{ $etapa->nome }}</span>
            </x-slot>
            <x-slot name="description">
                <span class="text-[10px] font-bold uppercase tracking-[2px]">Resumo de Aproveitamento</span>
            </x-slot>

            <div class="overflow-x-auto -mx-6 -mb-6">
                <table class="w-full text-left border-collapse text-xs tabular-nums">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-white/5 border-y border-gray-200 dark:border-white/10">
                            <th rowspan="2" class="px-6 py-4 font-bold uppercase min-w-[220px] text-gray-900 dark:text-gray-100 tracking-wider">
                                Disciplina
                            </th>
                            <th colspan="{{ count($categorias) + 2 }}" class="px-6 py-2 font-bold uppercase text-center border-b border-gray-200 dark:border-white/10 text-gray-900 dark:text-gray-100 tracking-wider">
                                {{ $etapa->nome }}
                            </th>
                            <th colspan="3" class="px-6 py-2 font-bold uppercase text-center border-l border-b border-gray-200 dark:border-white/10 text-gray-900 dark:text-gray-100 tracking-wider">
                                Resultado
                            </th>
                        </tr>
                        <tr class="bg-gray-50 dark:bg-white/5 border-b border-gray-200 dark:border-white/10">
                            @foreach($categorias as $categoria)
                                @php
                                    $sigla = substr(strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', str_replace('-', ' ', $categoria->nome))))), 0, 3);
                                @endphp
                                <th class="px-3 py-3 font-bold text-center text-gray-600 dark:text-gray-400" title="{{ $categoria->nome }}">
                                    {{ $sigla }}
                                </th>
                            @endforeach
                            <th class="px-3 py-3 font-bold text-center text-primary-600 dark:text-primary-400">MP</th>
                            <th class="px-3 py-3 font-bold text-center text-gray-400 italic">MT</th>
                            <th class="px-3 py-3 font-bold text-center text-gray-600 dark:text-gray-400 border-l border-gray-200 dark:border-white/10">SP</th>
                            <th class="px-3 py-3 font-bold text-center text-gray-600 dark:text-gray-400">FLT</th>
                            <th class="px-3 py-3 font-bold text-center text-gray-600 dark:text-gray-400">FREQ.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse($disciplinas as $disciplina)
                            @php
                                $notasFiltro = $notas->where('avaliacao.disciplina_id', $disciplina->id)
                                                     ->where('avaliacao.etapa_avaliativa_id', $etapa->id);

                                // Determinar quais categorias foram "substituídas" com notas nesta disciplina/etapa.
                                // Uma categoria é ignorada se existir nota noutra categoria que a substitui.
                                $categoriesWithSubstituteGrade = $categorias
                                    ->filter(fn($cat) => $cat->categoria_avaliacao_substituicao_id !== null)
                                    ->filter(function($cat) use ($notasFiltro) {
                                        // Verifica se o aluno tem nota nesta categoria substituta
                                        return $notasFiltro->where('avaliacao.categoria_avaliacao_id', $cat->id)->count() > 0;
                                    })
                                    ->pluck('categoria_avaliacao_substituicao_id')
                                    ->values();

                                $somaMediasCategorias = 0;
                                $categoriasComNota = 0;
                            @endphp
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/5 transition duration-75">
                                <td class="px-6 py-4 font-medium text-gray-900 dark:text-white text-sm">
                                    {{ $disciplina->nome }}
                                </td>

                                @foreach($categorias as $categoria)
                                    @php
                                        $isSubstituted = $categoriesWithSubstituteGrade->contains($categoria->id);
                                        $notasNaCategoria = $notasFiltro->where('avaliacao.categoria_avaliacao_id', $categoria->id);
                                        $mediaCategoria = $notasNaCategoria->count() > 0 ? $notasNaCategoria->avg('valor') : null;

                                        // Se a categoria foi substituída e tem nota, ignora no cálculo
                                        if (!$isSubstituted && $mediaCategoria !== null) {
                                            $somaMediasCategorias += $mediaCategoria;
                                            $categoriasComNota++;
                                        } elseif ($isSubstituted && $mediaCategoria !== null) {
                                            // Tem nota mas está substituída — não conta
                                        } elseif (!$isSubstituted && $mediaCategoria === null) {
                                            // Sem nota e não substituída — não conta
                                        }
                                    @endphp
                                    <td class="px-3 py-4 text-center font-mono">
                                        @if($isSubstituted && $mediaCategoria !== null)
                                            {{-- Nota riscada porque foi substituída --}}
                                            <span class="line-through text-gray-400 dark:text-gray-600 text-xs" title="Substituída por outra categoria">
                                                {{ number_format($mediaCategoria, 1, ',', '.') }}
                                            </span>
                                        @elseif($mediaCategoria !== null)
                                            <span @class([
                                                'text-danger-600 dark:text-danger-400 font-bold' => $mediaCategoria < 7,
                                                'text-gray-900 dark:text-gray-200' => $mediaCategoria >= 7,
                                            ])>
                                                {{ number_format($mediaCategoria, 1, ',', '.') }}
                                            </span>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-600">-</span>
                                        @endif
                                    </td>
                                @endforeach

                                <td class="px-3 py-4 text-center font-mono bg-primary-50/30 dark:bg-primary-900/10">
                                    @php
                                        $mediaEtapa = $categoriasComNota > 0 ? ($somaMediasCategorias / $categoriasComNota) : null;
                                    @endphp
                                    @if($mediaEtapa !== null)
                                        <x-filament::badge :color="$mediaEtapa < 7 ? 'danger' : 'primary'">
                                            {{ number_format($mediaEtapa, 1, ',', '.') }}
                                        </x-filament::badge>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-600">-</span>
                                    @endif
                                </td>

                                <td class="px-3 py-4 text-center italic text-gray-500 font-mono">
                                    @php
                                        $notasTurmaDisciplinaEtapa = $notasAgrupadasTurma->get($etapa->id)?->get($disciplina->id);
                                        $mt = $notasTurmaDisciplinaEtapa ? $notasTurmaDisciplinaEtapa->avg('valor') : null;
                                    @endphp
                                    {{ $mt ? number_format($mt, 1, ',', '.') : '-' }}
                                </td>

                                <td class="px-3 py-4 text-center border-l border-gray-200 dark:border-white/10 font-medium font-mono text-gray-700 dark:text-gray-300">
                                    @php
                                        $sp = $notas->where('avaliacao.disciplina_id', $disciplina->id)
                                                    ->where('avaliacao.etapa_avaliativa_id', '<=', $etapa->id)
                                                    ->groupBy('avaliacao.etapa_avaliativa_id')
                                                    ->map(fn($items) => $items->avg('valor'))
                                                    ->sum();
                                    @endphp
                                    {{ $sp > 0 ? number_format($sp, 1, ',', '.') : '-' }}
                                </td>

                                <td class="px-3 py-4 text-center font-mono text-gray-600 dark:text-gray-400">
                                    @php
                                        $faltas = $faltasAgrupadas->get($disciplina->id)?->count() ?? 0;
                                    @endphp
                                    {{ $faltas ?: '-' }}
                                </td>

                                <td class="px-3 py-4 text-center font-bold font-mono text-gray-900 dark:text-white">
                                    @php
                                        $totalAulas = $totalAulasAgrupadas->get($disciplina->id)?->count() ?? 0;
                                        $freq = $totalAulas > 0 ? (($totalAulas - $faltas) / $totalAulas) * 100 : 100;
                                    @endphp
                                    {{ number_format($freq, 0) }}%
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($categorias) + 6 }}" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400 italic">
                                    Nenhum registro de avaliação disponível para esta etapa.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @empty
        <x-filament::section>
            <p class="text-center text-gray-500 dark:text-gray-400 py-4">Nenhuma etapa avaliativa encontrada.</p>
        </x-filament::section>
    @endforelse

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">
        <x-filament::section>
            <x-slot name="heading">Legendas / Convenções</x-slot>
            
            <div class="grid grid-cols-2 gap-4 text-xs">
                <div class="space-y-3 text-gray-600 dark:text-gray-400">
                    @foreach($categorias as $categoria)
                        @php
                            $sigla = substr(strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', str_replace('-', ' ', $categoria->nome))))), 0, 3);
                        @endphp
                        <p class="flex items-center gap-2">
                            <span class="text-primary-600 dark:text-primary-400 font-bold min-w-[30px]">{{ $sigla }}</span>
                            <span class="text-gray-300 dark:text-gray-700">|</span>
                            {{ $categoria->nome }}
                        </p>
                    @endforeach
                    <p class="flex items-center gap-2">
                        <span class="text-primary-600 dark:text-primary-400 font-bold min-w-[30px]">MP</span>
                        <span class="text-gray-300 dark:text-gray-700">|</span>
                        Média Parcial
                    </p>
                </div>
                <div class="space-y-3 text-gray-600 dark:text-gray-400">
                    <p class="flex items-center gap-2">
                        <span class="text-primary-600 dark:text-primary-400 font-bold min-w-[30px]">MT</span>
                        <span class="text-gray-300 dark:text-gray-700">|</span>
                        Média Global da Turma
                    </p>
                    <p class="flex items-center gap-2">
                        <span class="text-primary-600 dark:text-primary-400 font-bold min-w-[30px]">SP</span>
                        <span class="text-gray-300 dark:text-gray-700">|</span>
                        Somatório Parcial
                    </p>
                    <p class="flex items-center gap-2">
                        <span class="text-primary-600 dark:text-primary-400 font-bold min-w-[30px]">FLT</span>
                        <span class="text-gray-300 dark:text-gray-700">|</span>
                        Aulas Ausentes
                    </p>
                    <p class="flex items-center gap-2">
                        <span class="text-primary-600 dark:text-primary-400 font-bold min-w-[30px]">FREQ.</span>
                        <span class="text-gray-300 dark:text-gray-700">|</span>
                        Percentual de Frequência
                    </p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Informações de Aprovação</x-slot>
            
            <div class="space-y-4">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    <x-filament::badge color="danger" class="mr-2">Atenção</x-filament::badge>
                    Notas inferiores a <strong class="text-danger-600 dark:text-danger-400">7,0</strong> indicam que o(a) aluno(a) estará em recuperação na disciplina.
                </p>
                <hr class="border-gray-200 dark:border-white/10" />
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    <span class="text-primary-600 dark:text-primary-400 font-bold mr-2">Critério:</span>
                    Frequência anual superior a <strong>75%</strong> e somatório/média anual superior a <strong>28,0</strong>.
                </p>
            </div>
        </x-filament::section>
    </div>

    <div class="mt-12 pt-8 border-t-2 border-dashed border-gray-300 dark:border-gray-700">
        <x-filament::section class="bg-gray-50/50 dark:bg-white/5">
            <div class="flex flex-col md:flex-row justify-between items-end gap-8">
                <div class="space-y-6 w-full md:w-1/2">
                    <div>
                        <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Comprovante de Entrega - Aluno(a)</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white uppercase">{{ $aluno?->nome ?? $schemaComponent->getRecord()?->nome }}</p>
                    </div>
                    <div class="flex items-center gap-8">
                        <div>
                            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Período/Ano</p>
                            <p class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ $turma?->periodoLetivo?->ano ?? now()->year }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Data</p>
                            <p class="text-sm font-bold text-gray-700 dark:text-gray-300 font-mono">____/____/_______</p>
                        </div>
                    </div>
                </div>

                <div class="w-full md:w-1/2 space-y-2">
                    <div class="border-t border-gray-400 dark:border-gray-600 w-full pt-2 text-center">
                        <p class="text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-widest">Assinatura do Responsável</p>
                    </div>
                    <p class="text-center text-[10px] text-gray-500 italic">Favor devolver este canhoto à secretaria devidamente assinado.</p>
                </div>
            </div>
        </x-filament::section>
    </div>
</div>
