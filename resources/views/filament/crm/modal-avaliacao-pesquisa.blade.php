@props(['pesquisa'])

@php
    $visita = $pesquisa?->visita;
    $interessado = $pesquisa?->interessado;
    $pessoa = $interessado?->pessoa;
    $classificacao = $pesquisa?->classificacaoNps() ?? '—';
    $cor = $pesquisa?->corBadge() ?? 'gray';
@endphp

<div class="space-y-5 p-1 text-sm text-gray-700 dark:text-gray-200">
    <!-- Header Resumo NPS -->
    <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 p-4 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div @class([
                'w-14 h-14 rounded-2xl flex items-center justify-center font-bold text-2xl shadow-sm text-white',
                'bg-emerald-600' => $cor === 'success',
                'bg-amber-500' => $cor === 'warning',
                'bg-rose-600' => $cor === 'danger',
                'bg-gray-400' => $cor === 'gray',
            ])>
                {{ $pesquisa?->nota_nps ?? '—' }}
            </div>
            <div>
                <div class="text-xs uppercase tracking-wider font-semibold text-gray-500 dark:text-gray-400">
                    Net Promoter Score (NPS)
                </div>
                <div class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <span>{{ $classificacao }}</span>
                    @if($cor === 'success')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                            Alta probabilidade de matrícula
                        </span>
                    @elseif($cor === 'warning')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                            Família indecisa / Neutra
                        </span>
                    @elseif($cor === 'danger')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                            Atenção imediata requerida
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="text-right text-xs text-gray-500 dark:text-gray-400">
            <div><strong>Respondido em:</strong> {{ $pesquisa?->respondido_em ? $pesquisa->respondido_em->format('d/m/Y H:i') : 'Pendente' }}</div>
            @if($pesquisa?->ip)
                <div class="text-[11px] text-gray-400">IP: {{ $pesquisa->ip }}</div>
            @endif
        </div>
    </div>

    <!-- Pilares de Avaliação -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <!-- Acolhimento -->
        <div class="rounded-lg border border-gray-200 dark:border-gray-800 p-3 bg-white dark:bg-gray-900">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Acolhimento & Recepção</span>
            <div class="flex items-center gap-1.5">
                @if($pesquisa?->nota_atendimento)
                    <div class="flex text-amber-400">
                        @for($i = 1; $i <= 5; $i++)
                            <svg class="w-4 h-4 {{ $i <= $pesquisa->nota_atendimento ? 'fill-current' : 'text-gray-300 dark:text-gray-700' }}" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                        @endfor
                    </div>
                    <span class="font-bold text-gray-900 dark:text-white">{{ $pesquisa->nota_atendimento }}/5</span>
                @else
                    <span class="text-gray-400 italic">Não avaliado</span>
                @endif
            </div>
        </div>

        <!-- Estrutura -->
        <div class="rounded-lg border border-gray-200 dark:border-gray-800 p-3 bg-white dark:bg-gray-900">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Instalações & Estrutura</span>
            <div class="flex items-center gap-1.5">
                @if($pesquisa?->nota_infraestrutura)
                    <div class="flex text-amber-400">
                        @for($i = 1; $i <= 5; $i++)
                            <svg class="w-4 h-4 {{ $i <= $pesquisa->nota_infraestrutura ? 'fill-current' : 'text-gray-300 dark:text-gray-700' }}" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                        @endfor
                    </div>
                    <span class="font-bold text-gray-900 dark:text-white">{{ $pesquisa->nota_infraestrutura }}/5</span>
                @else
                    <span class="text-gray-400 italic">Não avaliado</span>
                @endif
            </div>
        </div>

        <!-- Proposta Pedagógica -->
        <div class="rounded-lg border border-gray-200 dark:border-gray-800 p-3 bg-white dark:bg-gray-900">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Proposta Pedagógica</span>
            <div class="flex items-center gap-1.5">
                @if($pesquisa?->nota_proposta_pedagogica)
                    <div class="flex text-amber-400">
                        @for($i = 1; $i <= 5; $i++)
                            <svg class="w-4 h-4 {{ $i <= $pesquisa->nota_proposta_pedagogica ? 'fill-current' : 'text-gray-300 dark:text-gray-700' }}" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                        @endfor
                    </div>
                    <span class="font-bold text-gray-900 dark:text-white">{{ $pesquisa->nota_proposta_pedagogica }}/5</span>
                @else
                    <span class="text-gray-400 italic">Não avaliado</span>
                @endif
            </div>
        </div>
    </div>

    <!-- Comentário / Depoimento -->
    <div class="rounded-lg border border-gray-200 dark:border-gray-800 p-4 bg-white dark:bg-gray-900">
        <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 block mb-2">
            Comentário & Percepções da Família
        </span>
        @if(filled($pesquisa?->comentario))
            <p class="text-gray-800 dark:text-gray-100 italic bg-gray-50 dark:bg-gray-950 p-3 rounded border border-gray-100 dark:border-gray-800">
                "{{ $pesquisa->comentario }}"
            </p>
        @else
            <p class="text-gray-400 text-sm italic">A família não deixou comentários por escrito.</p>
        @endif
    </div>

    <!-- Dados da Visita & Lead -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs text-gray-600 dark:text-gray-400 pt-2 border-t border-gray-100 dark:border-gray-800">
        <div>
            <span class="font-semibold block text-gray-700 dark:text-gray-300">Responsável:</span>
            <span>{{ $pessoa?->nome ?? '—' }}</span>
        </div>
        <div>
            <span class="font-semibold block text-gray-700 dark:text-gray-300">Aluno:</span>
            <span>{{ $visita?->dependente?->nome_crianca ?? 'Toda a família' }}</span>
        </div>
        <div>
            <span class="font-semibold block text-gray-700 dark:text-gray-300">Data da Visita:</span>
            <span>{{ $visita?->data_hora ? $visita->data_hora->format('d/m/Y H:i') : '—' }}</span>
        </div>
        <div>
            <span class="font-semibold block text-gray-700 dark:text-gray-300">Consultor:</span>
            <span>{{ $visita?->usuario?->name ?? '—' }}</span>
        </div>
    </div>
</div>
