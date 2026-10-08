<div class="space-y-4">
    @php
        $aluno = $preceptoria->matricula?->pessoa?->nome ?? 'Estudante';
        $emprestimos = $preceptoria->matricula?->emprestimos()->with('livro')->latest('data_emprestimo')->get() ?? collect();
    @endphp

    <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg border border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Estudante Acompanhado:</div>
            <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $aluno }}</div>
        </div>
        <div class="text-right">
            <div class="text-xs text-gray-500 dark:text-gray-400">Total no Histórico:</div>
            <div class="text-sm font-bold text-primary-600 dark:text-primary-400">{{ $emprestimos->count() }} livro(s)</div>
        </div>
    </div>

    @if($emprestimos->isEmpty())
        <div class="text-center py-6 text-gray-500 dark:text-gray-400 text-sm italic">
            Nenhum empréstimo ou leitura registrada na biblioteca escolar para este estudante até o momento.
        </div>
    @else
        <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
            @foreach($emprestimos as $emp)
                <div class="p-3 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 flex gap-3 items-center">
                    <img 
                        src="{{ $emp->livro->capa_url ?: 'https://ui-avatars.com/api/?name=Livro&background=e2e8f0&color=64748b' }}" 
                        alt="Capa de {{ $emp->livro->titulo }}"
                        class="w-12 h-16 object-cover rounded shadow-xs shrink-0"
                    />
                    <div class="flex-1 min-w-0">
                        <h4 class="text-sm font-bold text-gray-900 dark:text-white truncate" title="{{ $emp->livro->titulo }}">
                            {{ $emp->livro->titulo }}
                        </h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $emp->livro->autor }}</p>
                        <div class="mt-1 flex items-center gap-2 text-[11px] text-gray-500">
                            <span>Retirado: {{ $emp->data_emprestimo->format('d/m/Y') }}</span>
                            @if($emp->data_devolucao)
                                <span>• Devolvido: {{ $emp->data_devolucao->format('d/m/Y') }}</span>
                            @else
                                <span class="text-amber-600 dark:text-amber-400 font-semibold">• Em leitura (Prazo: {{ $emp->data_prevista_devolucao->format('d/m/Y') }})</span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold {{ $emp->status->value === 'devolvido' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' : ($emp->status->value === 'atrasado' ? 'bg-danger-100 text-danger-800 dark:bg-danger-950 dark:text-danger-200' : 'bg-primary-100 text-primary-800 dark:bg-primary-950 dark:text-primary-200') }}">
                            {{ $emp->status->getLabel() }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
