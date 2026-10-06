<div class="space-y-4 text-sm text-gray-700 dark:text-gray-200">
    <div class="flex items-center justify-between p-4 bg-primary-50 dark:bg-primary-950/40 rounded-xl border border-primary-100 dark:border-primary-900">
        <div>
            <div class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400">Proposta Comercial Oficial</div>
            <div class="text-xl font-bold text-gray-900 dark:text-white">{{ $proposta->codigo }}</div>
        </div>
        <div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold
                {{ $proposta->status->getColor() === 'success' ? 'bg-success-100 text-success-800 dark:bg-success-950/60 dark:text-success-300' : '' }}
                {{ $proposta->status->getColor() === 'warning' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' : '' }}
                {{ $proposta->status->getColor() === 'danger' ? 'bg-danger-100 text-danger-800 dark:bg-danger-950/60 dark:text-danger-300' : '' }}
                {{ $proposta->status->getColor() === 'gray' ? 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300' : '' }}">
                {{ $proposta->status->getLabel() }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 p-4 bg-gray-50 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
        <div>
            <span class="text-xs text-gray-500 uppercase">Responsável</span>
            <div class="font-semibold text-gray-900 dark:text-white">{{ $proposta->responsavel_nome }}</div>
            <div class="text-xs text-gray-500">{{ $proposta->responsavel_telefone ?? 'Sem telefone' }} • {{ $proposta->responsavel_email ?? 'Sem e-mail' }}</div>
        </div>
        <div>
            <span class="text-xs text-gray-500 uppercase">Estudante</span>
            <div class="font-semibold text-gray-900 dark:text-white">{{ $proposta->aluno_nome ?: 'Não informado' }}</div>
            <div class="text-xs text-gray-500">{{ $proposta->quantidade_alunos }} aluno(s) no plano</div>
        </div>
    </div>

    <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
        <span class="text-xs text-gray-500 uppercase">Segmento & Unidade</span>
        <div class="font-semibold text-gray-900 dark:text-white">
            {{ $proposta->curso?->nome }} — {{ $proposta->serie?->nome }}
            @if($proposta->turma)
                ({{ $proposta->turma->nome }})
            @endif
        </div>
        <div class="text-xs text-gray-500">
            Unidade: {{ $proposta->unidade?->nome }} • Turno: {{ $proposta->turno?->nome ?? 'A definir' }}
        </div>
    </div>

    <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border-2 border-primary-200 dark:border-primary-800 space-y-2">
        <div class="flex justify-between text-gray-600 dark:text-gray-300">
            <span>Mensalidade de Tabela:</span>
            <span>R$ {{ number_format((float) $proposta->valor_tabela_mensal, 2, ',', '.') }}</span>
        </div>
        <div class="flex justify-between text-warning-600 dark:text-warning-400 font-medium">
            <span>Desconto Concedido:</span>
            <span>- R$ {{ number_format((float) $proposta->valor_desconto_mensal, 2, ',', '.') }} ({{ $proposta->tipo_desconto === 'percentual' ? $proposta->desconto_solicitado.'%' : 'Fixo' }})</span>
        </div>
        <div class="pt-2 border-t border-gray-200 dark:border-gray-800 flex justify-between text-base font-bold text-gray-900 dark:text-white">
            <span>Mensalidade Líquida:</span>
            <span class="text-success-600 dark:text-success-400">R$ {{ number_format((float) $proposta->valor_liquido_mensal, 2, ',', '.') }}</span>
        </div>
        <div class="flex justify-between text-xs text-gray-500 pt-1">
            <span>Plano Contratual: {{ $proposta->quantidade_parcelas }} parcelas</span>
            <span>Investimento Anual: <strong>R$ {{ number_format((float) $proposta->valor_total_anual, 2, ',', '.') }}</strong></span>
        </div>
    </div>

    @if($proposta->motivo_desconto)
        <div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg text-xs text-gray-600 dark:text-gray-300">
            <strong>Justificativa Comercial:</strong> {{ $proposta->motivo_desconto }}
        </div>
    @endif

    <div class="flex items-center justify-between text-xs text-gray-500 pt-2 border-t border-gray-200 dark:border-gray-800">
        <div>Consultor: {{ $proposta->solicitante?->name ?? 'Sistema' }}</div>
        <div>Válida até: <strong class="text-gray-800 dark:text-gray-200">{{ $proposta->validade?->format('d/m/Y') }}</strong></div>
    </div>
</div>
