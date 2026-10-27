@php
    /** @var \App\Models\Contrato $record */
    $statusEnum = $record->statusAssinatura();
    $statusRotulo = \App\Enums\StatusAssinaturaContrato::rotuloDe($record->assinafy_status);
    $statusCor = \App\Enums\StatusAssinaturaContrato::corDe($record->assinafy_status);
    $signatarios = $record->getStatusSignatarios();
@endphp

<div class="space-y-4 text-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 p-4 rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700">
        <div class="flex items-center gap-3">
            <span class="text-xs uppercase font-semibold text-gray-500 dark:text-gray-400">Situação Geral:</span>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                @if($statusCor === 'success') bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400
                @elseif($statusCor === 'danger') bg-danger-100 text-danger-700 dark:bg-danger-900/30 dark:text-danger-400
                @elseif($statusCor === 'warning') bg-warning-100 text-warning-700 dark:bg-warning-900/30 dark:text-warning-400
                @elseif($statusCor === 'info') bg-info-100 text-info-700 dark:bg-info-900/30 dark:text-info-400
                @else bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300
                @endif">
                {{ $statusRotulo }}
            </span>
        </div>

        <div class="text-xs text-gray-500 dark:text-gray-400">
            <strong>Data de Aceite:</strong>
            @if($record->data_aceite)
                <span class="text-gray-900 dark:text-gray-200 font-medium">{{ $record->data_aceite->format('d/m/Y H:i') }}</span>
            @else
                <span class="italic">Pendente de conclusão</span>
            @endif
        </div>
    </div>

    <div>
        <h4 class="text-xs uppercase font-semibold text-gray-600 dark:text-gray-300 mb-2">Signatários do Contrato</h4>
        @if($signatarios->isEmpty())
            <p class="text-xs text-gray-400 italic">Sem signatários vinculados.</p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                @foreach($signatarios as $sig)
                    @php
                        $sigStatus = $sig['status'] ?? 'pending';
                        $sigAssinado = $sigStatus === 'signed';
                        $sigRecusado = $sigStatus === 'refused';
                    @endphp
                    <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 flex flex-col justify-between">
                        <div>
                            <div class="font-medium text-gray-900 dark:text-gray-100 text-xs">{{ $sig['nome'] }}</div>
                            <div class="text-[11px] text-gray-500 dark:text-gray-400 truncate">{{ $sig['email'] }}</div>
                        </div>
                        <div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                            <span class="text-[11px] font-semibold
                                @if($sigAssinado) text-success-600 dark:text-success-400
                                @elseif($sigRecusado) text-danger-600 dark:text-danger-400
                                @else text-warning-600 dark:text-warning-400
                                @endif">
                                @if($sigAssinado)
                                    ✓ Assinado
                                @elseif($sigRecusado)
                                    ✗ Recusado
                                @else
                                    ⏳ Pendente
                                @endif
                            </span>
                            @if(!empty($sig['signed_at']))
                                <span class="text-[10px] text-gray-400">{{ \Carbon\Carbon::parse($sig['signed_at'])->format('d/m/Y H:i') }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
