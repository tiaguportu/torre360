<div class="space-y-4 text-xs sm:text-sm text-gray-800 dark:text-gray-200">
    <div class="border-b border-gray-200 dark:border-gray-700 pb-3 text-center">
        <h3 class="font-bold text-base text-gray-900 dark:text-white uppercase tracking-wide">
            Instrumento Particular de Confissão e Transação de Dívida Escolar
        </h3>
        <p class="text-xs text-gray-500">
            Título Executivo Extrajudicial • Art. 784, III do Código de Processo Civil • Acordo {{ $acordo->codigo }}
        </p>
    </div>

    <!-- Resumo do Acordo -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3 bg-gray-50 dark:bg-gray-800/40 rounded-lg border border-gray-200 dark:border-gray-700">
        <div>
            <span class="block text-xs text-gray-500">Dívida Original:</span>
            <span class="font-bold text-gray-800 dark:text-gray-100">R$ {{ number_format((float) $acordo->valor_original_total, 2, ',', '.') }}</span>
        </div>
        <div>
            <span class="block text-xs text-gray-500">Desconto Concedido:</span>
            <span class="font-bold text-emerald-600">R$ {{ number_format((float) $acordo->valor_desconto, 2, ',', '.') }}</span>
        </div>
        <div>
            <span class="block text-xs text-gray-500">Total a Quitar:</span>
            <span class="font-bold text-primary-600">R$ {{ number_format((float) $acordo->valor_total_acordo, 2, ',', '.') }}</span>
        </div>
        <div>
            <span class="block text-xs text-gray-500">Condições:</span>
            <span class="font-bold text-gray-800 dark:text-gray-100">{{ $acordo->quantidade_parcelas }}x R$ {{ number_format((float) $acordo->valor_parcela, 2, ',', '.') }}</span>
        </div>
    </div>

    <!-- Minuta Jurídica Completa -->
    <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 font-mono text-xs whitespace-pre-line leading-relaxed max-h-72 overflow-y-auto">
        {{ $acordo->termo_confissao_texto }}
    </div>

    <!-- Cronograma de Parcelas -->
    <div>
        <h4 class="font-bold text-xs uppercase text-gray-500 mb-2">Cronograma das Parcelas</h4>
        <div class="space-y-1.5 max-h-40 overflow-y-auto">
            @foreach($acordo->parcelas as $p)
                <div class="flex justify-between items-center p-2 rounded bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 text-xs">
                    <span>
                        <strong>{{ $p->numero_parcela === 0 ? 'Entrada' : 'Parcela '.$p->numero_parcela }}</strong>
                        — Vencimento: {{ $p->data_vencimento->format('d/m/Y') }}
                    </span>
                    <div class="flex items-center gap-2">
                        <span class="font-bold">R$ {{ number_format((float) $p->valor, 2, ',', '.') }}</span>
                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $p->status === 'pago' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700' }}">
                            {{ ucfirst($p->status) }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Link Público -->
    <div class="p-3 rounded-lg bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-900 text-xs flex justify-between items-center">
        <div>
            <span class="font-semibold block text-blue-900 dark:text-blue-300">Link de Assinatura Online para os Pais:</span>
            <span class="text-blue-700 dark:text-blue-400 select-all">{{ $acordo->urlAceitePublica() }}</span>
        </div>
        <a href="{{ $acordo->urlAceitePublica() }}" target="_blank" class="px-3 py-1.5 rounded bg-blue-600 hover:bg-blue-700 text-white font-semibold shrink-0">
            Abrir Página
        </a>
    </div>
</div>
