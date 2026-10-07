@php
    $interessado = $interessado ?? null;
    $documentos = $interessado ? $interessado->documentosInseridos()->with('tipoDocumento')->get() : collect();
@endphp

@if($interessado && $documentos->isNotEmpty())
    <div class="mb-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 p-4 space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
                <span>📎</span> Documentos Enviados pela Família no Portal
            </span>
            <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300 font-semibold">
                {{ $documentos->count() }} arquivo(s)
            </span>
        </div>

        <div class="divide-y divide-slate-200/60 dark:divide-slate-800 text-xs">
            @foreach($documentos as $doc)
                <div class="py-2.5 flex items-center justify-between gap-3">
                    <div class="space-y-0.5">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $doc->tipoDocumento?->nome ?? 'Documento' }}</span>
                            @if($doc->tipoDocumento?->isObrigatorioContrato())
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">Contrato</span>
                            @elseif($doc->tipoDocumento?->isObrigatorioHistorico())
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">Histórico</span>
                            @else
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">Opcional</span>
                            @endif
                        </div>
                        <span class="text-slate-500 text-[11px] block">{{ $doc->nome_arquivo_original }}</span>
                    </div>

                    <div class="flex items-center gap-2">
                        @if($doc->status === \App\Enums\SituacaoDocumento::VERIFICADO)
                            <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 text-[11px] font-bold">✓ Verificado</span>
                        @elseif($doc->status === \App\Enums\SituacaoDocumento::EM_ANALISE)
                            <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 text-[11px] font-semibold">⏳ Em Análise</span>
                        @elseif($doc->status === \App\Enums\SituacaoDocumento::REJEITADO)
                            <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 text-[11px] font-semibold">⚠️ Rejeitado</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@elseif($interessado)
    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50/50 dark:bg-amber-900/20 p-3 text-xs text-amber-800 dark:text-amber-300 flex items-center gap-2">
        <span>⚠️</span>
        <span>A família ainda não enviou nenhum documento pelo portal unificado. Você pode anexar os documentos físicos abaixo ou prosseguir criando a matrícula como <strong>Pendente</strong>.</span>
    </div>
@endif
