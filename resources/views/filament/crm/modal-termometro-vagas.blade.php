@props(['series' => null])

@php
    $seriesList = $series ?? app(\App\Services\TermometroVagasService::class)->calcularVagasPorSerie();
    $totalCapacidade = $seriesList->sum('capacidade_total');
    $totalOcupadas = $seriesList->sum('matriculas_ocupadas');
    $totalRestantes = $seriesList->sum('vagas_restantes');
    $ocupacaoGlobal = $totalCapacidade > 0 ? round(($totalOcupadas / $totalCapacidade) * 100, 1) : 0.0;
    
    // Ordena séries por urgência (maior taxa de ocupação primeiro)
    $seriesOrdenadas = $seriesList->sortByDesc('taxa_ocupacao');
@endphp

<div class="space-y-6 p-2 text-sm text-gray-700 dark:text-gray-200">
    <!-- Cards de Indicadores Globais -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3 shadow-xs text-center">
            <span class="text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 block mb-1">Capacidade Total</span>
            <span class="text-2xl font-black text-gray-900 dark:text-white">{{ number_format($totalCapacidade, 0, ',', '.') }}</span>
            <span class="text-[11px] text-gray-400 block">vagas máximas</span>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3 shadow-xs text-center">
            <span class="text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 block mb-1">Matrículas Ativas</span>
            <span class="text-2xl font-black text-indigo-600 dark:text-indigo-400">{{ number_format($totalOcupadas, 0, ',', '.') }}</span>
            <span class="text-[11px] text-gray-400 block">alunos confirmados</span>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3 shadow-xs text-center">
            <span class="text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 block mb-1">Vagas Restantes</span>
            <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ number_format($totalRestantes, 0, ',', '.') }}</span>
            <span class="text-[11px] text-gray-400 block">disponíveis para venda</span>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3 shadow-xs text-center">
            <span class="text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 block mb-1">Ocupação Geral</span>
            <span class="text-2xl font-black {{ $ocupacaoGlobal >= 85 ? 'text-rose-600' : ($ocupacaoGlobal >= 70 ? 'text-amber-500' : 'text-emerald-600') }}">
                {{ $ocupacaoGlobal }}%
            </span>
            <span class="text-[11px] text-gray-400 block">da capacidade</span>
        </div>
    </div>

    <!-- Lista de Séries e Termômetro -->
    <div class="space-y-3">
        <h4 class="font-bold text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
            <span>📊</span> Disponibilidade e Escassez por Série
        </h4>

        <div class="space-y-2.5 max-h-[480px] overflow-y-auto pr-1">
            @foreach($seriesOrdenadas as $serie)
                @php
                    $nivel = $serie['nivel_escassez'];
                    $taxa = $serie['taxa_ocupacao'];
                    $vagas = $serie['vagas_restantes'];
                @endphp
                <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3.5 shadow-xs hover:border-gray-300 dark:hover:border-gray-700 transition-colors">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                        <div>
                            <span class="font-bold text-gray-900 dark:text-white text-sm">
                                {{ $serie['serie_nome'] }}
                            </span>
                            <span class="text-xs text-gray-400 ml-1.5 font-normal">
                                ({{ $serie['curso_nome'] }})
                            </span>
                            @if(! empty($serie['periodo_letivo_nome']))
                                <span class="text-xs text-indigo-500 dark:text-indigo-400 ml-1.5 font-semibold" title="Período letivo das turmas que recebem novos leads">
                                    · turmas de {{ $serie['periodo_letivo_nome'] }}
                                </span>
                            @endif
                            @if($serie['capacidade_estimada'] ?? false)
                                <span class="ml-1.5 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300 border border-gray-200 dark:border-gray-700" title="Sem turma cadastrada ou turma sem número de vagas: o valor mostrado usa o padrão de {{ \App\Services\TermometroVagasService::VAGAS_PADRAO_TURMA }} vagas por turma e não é usado como argumento de urgência.">
                                    capacidade estimada
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center gap-2">
                            <!-- Badge de Escassez -->
                            @if($nivel === 'esgotado')
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border border-rose-200 dark:border-rose-900">
                                    ⛔ Esgotado (0 vagas)
                                </span>
                            @elseif($nivel === 'critico')
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border border-rose-200 dark:border-rose-900 animate-pulse">
                                    🔥 Últimas {{ $vagas }} vagas!
                                </span>
                            @elseif($nivel === 'alerta')
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 dark:border-amber-900">
                                    🟡 Vagas Limitadas ({{ $vagas }} restam)
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900">
                                    🟢 {{ $vagas }} vagas disponíveis
                                </span>
                            @endif

                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300 min-w-[50px] text-right">
                                {{ $taxa }}%
                            </span>
                        </div>
                    </div>

                    <!-- Barra de Progresso Visual -->
                    <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-2.5 overflow-hidden">
                        <div 
                            class="h-2.5 rounded-full transition-all duration-500 {{ $taxa >= 90 ? 'bg-rose-600' : ($taxa >= 75 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                            style="width: {{ min(100, $taxa) }}%"
                        ></div>
                    </div>

                    <div class="flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400 mt-1.5">
                        <span>{{ $serie['matriculas_ocupadas'] }} matriculados de {{ $serie['capacidade_total'] }} vagas</span>
                        <span>{{ $serie['total_turmas'] }} {{ $serie['total_turmas'] === 1 ? 'turma' : 'turmas' }}</span>
                    </div>

                    <!-- Detalhamento por Turma (quando houver mais de uma) -->
                    @if(count($serie['turmas_detalhes']) > 1)
                        <div class="mt-2.5 pt-2 border-t border-gray-100 dark:border-gray-800/60 grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
                            @foreach($serie['turmas_detalhes'] as $turma)
                                <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-800/40 px-2 py-1 rounded">
                                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $turma['turma_nome'] }} ({{ $turma['turno'] }})</span>
                                    <span class="text-gray-500 dark:text-gray-400">
                                        {{ $turma['ocupadas'] }}/{{ $turma['capacidade'] }} 
                                        <strong class="{{ $turma['restantes'] <= 3 ? 'text-rose-600' : 'text-emerald-600' }}">({{ $turma['restantes'] }} livres)</strong>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
