@php
    $inserido = $documentosInseridos->firstWhere('tipo_documento_id', $tipo->id);
    $status = $inserido?->status;
    $etapaFamiliaConcluida = $etapaFamiliaConcluida ?? false;
    $bloquearUpload = $etapaFamiliaConcluida && $status !== \App\Enums\SituacaoDocumento::REJEITADO;

    $iconeStatus = match ($status) {
        \App\Enums\SituacaoDocumento::VERIFICADO => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        \App\Enums\SituacaoDocumento::EM_ANALISE => 'bg-amber-50 text-amber-700 ring-amber-200',
        \App\Enums\SituacaoDocumento::REJEITADO => 'bg-rose-50 text-rose-700 ring-rose-200',
        default => 'bg-slate-50 text-slate-400 ring-slate-200',
    };
@endphp

<article class="bg-white rounded-xl border shadow-sm
    {{ $status === \App\Enums\SituacaoDocumento::REJEITADO ? 'border-rose-200 border-l-[3px] border-l-rose-500' : 'border-slate-200' }}
">
    <div class="p-4 sm:p-5">
        <div class="flex items-start gap-3.5">
            <!-- Ícone de situação -->
            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full ring-1 ring-inset {{ $iconeStatus }}">
                @if($status === \App\Enums\SituacaoDocumento::VERIFICADO)
                    <x-heroicon-o-check class="h-5 w-5" />
                @elseif($status === \App\Enums\SituacaoDocumento::EM_ANALISE)
                    <x-heroicon-o-clock class="h-5 w-5" />
                @elseif($status === \App\Enums\SituacaoDocumento::REJEITADO)
                    <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                @else
                    <x-heroicon-o-document-text class="h-5 w-5" />
                @endif
            </span>

            <div class="min-w-0 flex-1 space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-x-4 gap-y-2">
                    <div class="min-w-0 space-y-1.5">
                        <h4 class="font-semibold text-slate-900 text-sm sm:text-base leading-snug">{{ $tipo->nome }}</h4>

                        <div class="flex items-center gap-1.5 flex-wrap">
                            @if($tipo->isObrigatorioContrato())
                                <span class="text-[11px] font-medium px-2 py-0.5 rounded-md bg-slate-100 text-slate-600">
                                    Obrigatório para Contrato
                                </span>
                            @elseif($tipo->isObrigatorioHistorico())
                                <span class="text-[11px] font-medium px-2 py-0.5 rounded-md bg-slate-100 text-slate-600">
                                    Histórico do Aluno
                                </span>
                            @else
                                <span class="text-[11px] font-medium px-2 py-0.5 rounded-md bg-slate-100 text-slate-600">
                                    Opcional
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Status Badge -->
                    <div class="shrink-0">
                        @if($status === \App\Enums\SituacaoDocumento::VERIFICADO)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                <x-heroicon-s-check-circle class="h-4 w-4" /> Aprovado pela Secretaria
                            </span>
                        @elseif($status === \App\Enums\SituacaoDocumento::EM_ANALISE)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-600/20">
                                <x-heroicon-s-clock class="h-4 w-4" /> Em Análise
                            </span>
                        @elseif($status === \App\Enums\SituacaoDocumento::REJEITADO)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20">
                                <x-heroicon-s-exclamation-triangle class="h-4 w-4" /> Necessita Correção
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-slate-50 text-slate-600 ring-1 ring-inset ring-slate-300/70">
                                Pendente de Envio
                            </span>
                        @endif
                    </div>
                </div>

                @if($tipo->modelo_link || $tipo->modelo_arquivo)
                    <a href="{{ $tipo->modelo_link ?: asset('storage/' . $tipo->modelo_arquivo) }}" target="_blank" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:text-brand-900 hover:underline underline-offset-2">
                        <x-heroicon-o-arrow-down-tray class="h-4 w-4" /> Baixar modelo / instruções
                    </a>
                @endif

                <!-- Mensagem de Rejeição / Justificativa da Secretaria -->
                @if($status === \App\Enums\SituacaoDocumento::REJEITADO && filled($inserido->observacoes))
                    <div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-900 text-sm flex items-start gap-2.5">
                        <x-heroicon-o-chat-bubble-left-ellipsis class="h-5 w-5 shrink-0 text-rose-500" />
                        <div>
                            <span class="font-semibold block">Motivo informado pela secretaria:</span>
                            {{ $inserido->observacoes }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Ações e Formulário de Upload -->
    <div class="px-4 sm:px-5 py-3 border-t border-slate-100 bg-slate-50/60 rounded-b-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-sm">
        @if($inserido)
            <div class="flex items-center gap-2 min-w-0 text-slate-500">
                <x-heroicon-o-paper-clip class="h-4 w-4 shrink-0 text-slate-400" />
                <span class="shrink-0">Arquivo enviado:</span>
                <strong class="font-medium text-slate-700 truncate">{{ $inserido->nome_arquivo_original ?? 'documento' }}</strong>
            </div>
        @else
            <span class="text-slate-400">Nenhum arquivo enviado até o momento.</span>
        @endif

        <div class="flex flex-wrap items-center gap-2 sm:shrink-0">
            @if($status === \App\Enums\SituacaoDocumento::VERIFICADO)
                <span class="text-sm text-emerald-700 font-medium flex items-center gap-1.5">
                    <x-heroicon-o-lock-closed class="h-4 w-4" /> Documento homologado
                </span>
            @elseif($bloquearUpload)
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-white text-slate-600 ring-1 ring-inset ring-slate-300">
                    <x-heroicon-o-lock-closed class="h-4 w-4 text-slate-400" /> Em análise pela Secretaria
                </span>
            @else
                <form action="{{ route('candidato.documentos.upload', ['token' => $token]) }}" method="POST" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                    @csrf
                    <input type="hidden" name="tipo_documento_id" value="{{ $tipo->id }}">
                    @if($interessado->dependentes->count() === 1)
                        <input type="hidden" name="interessado_dependente_id" value="{{ $interessado->dependentes->first()->id }}">
                    @elseif($interessado->dependentes->count() > 1)
                        <select name="interessado_dependente_id" class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-sm bg-white text-slate-700">
                            <option value="">Para o Aluno...</option>
                            @foreach($interessado->dependentes as $dep)
                                <option value="{{ $dep->id }}" {{ $inserido?->interessado_dependente_id === $dep->id ? 'selected' : '' }}>
                                    {{ $dep->nome_crianca }}
                                </option>
                            @endforeach
                        </select>
                    @endif

                    <label class="cursor-pointer px-3.5 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors flex items-center gap-2 focus-within:ring-2 focus-within:ring-brand-500 focus-within:ring-offset-2">
                        <x-heroicon-o-arrow-up-tray class="h-4 w-4" />
                        <span>{{ $inserido ? 'Substituir' : 'Enviar Arquivo' }}</span>
                        <input type="file" name="arquivo" accept=".pdf,image/png,image/jpeg,image/webp" class="sr-only" onchange="this.form.submit()">
                    </label>
                </form>

                @if($inserido && $status !== \App\Enums\SituacaoDocumento::VERIFICADO)
                    <form action="{{ route('candidato.documentos.remover', ['token' => $token, 'documento' => $inserido->id]) }}" method="POST" onsubmit="return confirm('Deseja realmente remover este documento?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-1.5 px-2.5 py-2 text-sm text-slate-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg font-medium transition-colors">
                            <x-heroicon-o-trash class="h-4 w-4" /> Remover
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </div>
</article>
