@php
    $inserido = $documentosInseridos->firstWhere('tipo_documento_id', $tipo->id);
    $status = $inserido?->status;
    $etapaFamiliaConcluida = $etapaFamiliaConcluida ?? false;
    $bloquearUpload = $etapaFamiliaConcluida && $status !== \App\Enums\SituacaoDocumento::REJEITADO;
@endphp

<div class="bg-white rounded-2xl border transition-all duration-200 p-5 shadow-xs
    {{ $status === \App\Enums\SituacaoDocumento::VERIFICADO ? 'border-emerald-200 bg-emerald-50/20' : '' }}
    {{ $status === \App\Enums\SituacaoDocumento::EM_ANALISE ? 'border-amber-200 bg-amber-50/20' : '' }}
    {{ $status === \App\Enums\SituacaoDocumento::REJEITADO ? 'border-rose-200 bg-rose-50/30' : '' }}
    {{ ! $status ? 'border-slate-200 hover:border-slate-300' : '' }}
">
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
        <div class="space-y-1">
            <div class="flex items-center gap-2 flex-wrap">
                <h4 class="font-bold text-slate-900 text-sm sm:text-base">{{ $tipo->nome }}</h4>
                @if($tipo->isObrigatorioContrato())
                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200">
                        Obrigatório para Contrato
                    </span>
                @elseif($tipo->isObrigatorioHistorico())
                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200">
                        Histórico do Aluno
                    </span>
                @else
                    <span class="text-[10px] font-semibold uppercase px-2 py-0.5 rounded bg-slate-100 text-slate-600">
                        Opcional
                    </span>
                @endif

                @if($tipo->relationLoaded('cursos') ? $tipo->cursos->isNotEmpty() : $tipo->cursos()->exists())
                    <span class="text-[10px] font-medium px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200">
                        🎓 Curso: {{ ($tipo->relationLoaded('cursos') ? $tipo->cursos : $tipo->cursos)->pluck('nome_externo')->filter()->join(', ') ?: 'Específico' }}
                    </span>
                @endif
            </div>

            @if($tipo->modelo_link || $tipo->modelo_arquivo)
                <div class="pt-0.5">
                    <a href="{{ $tipo->modelo_link ?: asset('storage/' . $tipo->modelo_arquivo) }}" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-800 underline inline-flex items-center gap-1">
                        <span>📥</span> Baixar modelo / instruções
                    </a>
                </div>
            @endif
        </div>

        <!-- Status Badge -->
        <div>
            @if($status === \App\Enums\SituacaoDocumento::VERIFICADO)
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                    <span>✓</span> Aprovado pela Secretaria
                </span>
            @elseif($status === \App\Enums\SituacaoDocumento::EM_ANALISE)
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                    <span>⏳</span> Em Análise
                </span>
            @elseif($status === \App\Enums\SituacaoDocumento::REJEITADO)
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                    <span>⚠️</span> Necessita Correção
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-600">
                    Pendente de Envio
                </span>
            @endif
        </div>
    </div>

    <!-- Mensagem de Rejeição / Justificativa da Secretaria -->
    @if($status === \App\Enums\SituacaoDocumento::REJEITADO && filled($inserido->observacoes))
        <div class="mt-3 p-3 rounded-xl bg-rose-100/70 border border-rose-200 text-rose-900 text-xs flex items-start gap-2">
            <span class="text-sm">📌</span>
            <div>
                <span class="font-bold block">Motivo informado pela secretaria:</span>
                {{ $inserido->observacoes }}
            </div>
        </div>
    @endif

    <!-- Ações e Formulário de Upload -->
    <div class="mt-4 pt-3 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
        @if($inserido)
            <div class="text-slate-500 truncate max-w-xs">
                <span>Arquivo enviado:</span>
                <strong class="text-slate-700">{{ $inserido->nome_arquivo_original ?? 'documento' }}</strong>
            </div>
        @else
            <span class="text-slate-400">Nenhum arquivo enviado até o momento.</span>
        @endif

        <div class="flex items-center gap-2">
            @if($status === \App\Enums\SituacaoDocumento::VERIFICADO)
                <span class="text-xs text-emerald-700 font-semibold flex items-center gap-1">
                    <span>🔒</span> Documento homologado
                </span>
            @elseif($bloquearUpload)
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                    <span>🔒</span> Em análise pela Secretaria
                </span>
            @else
                <form action="{{ route('candidato.documentos.upload', ['token' => $token]) }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2">
                    @csrf
                    <input type="hidden" name="tipo_documento_id" value="{{ $tipo->id }}">
                    @if($interessado->dependentes->count() === 1)
                        <input type="hidden" name="interessado_dependente_id" value="{{ $interessado->dependentes->first()->id }}">
                    @elseif($interessado->dependentes->count() > 1)
                        <select name="interessado_dependente_id" class="px-2 py-1.5 border border-slate-300 rounded-lg text-xs bg-white text-slate-700">
                            <option value="">Para o Aluno...</option>
                            @foreach($interessado->dependentes as $dep)
                                <option value="{{ $dep->id }}" {{ $inserido?->interessado_dependente_id === $dep->id ? 'selected' : '' }}>
                                    {{ $dep->nome_crianca }}
                                </option>
                            @endforeach
                        </select>
                    @endif

                    <label class="cursor-pointer px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow-xs transition-colors flex items-center gap-1.5">
                        <span>📷</span>
                        <span>{{ $inserido ? 'Substituir' : 'Enviar Arquivo' }}</span>
                        <input type="file" name="arquivo" accept=".pdf,image/png,image/jpeg,image/webp" class="hidden" onchange="this.form.submit()">
                    </label>
                </form>

                @if($inserido && $status !== \App\Enums\SituacaoDocumento::VERIFICADO)
                    <form action="{{ route('candidato.documentos.remover', ['token' => $token, 'documento' => $inserido->id]) }}" method="POST" onsubmit="return confirm('Deseja realmente remover este documento?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-2.5 py-1.5 text-rose-600 hover:bg-rose-50 rounded-lg font-medium transition-colors">
                            Remover
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </div>
</div>
