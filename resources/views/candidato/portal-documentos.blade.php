<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal de Pré-Admissão | Documentos do Candidato</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen pb-16">
    <!-- Topbar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
        <div class="max-w-3xl mx-auto px-4 py-3.5 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="text-2xl">🏫</span>
                <div>
                    <h1 class="font-bold text-slate-900 text-sm sm:text-base leading-tight">Portal de Admissão</h1>
                    <p class="text-xs text-slate-500">Envio de Documentos do Candidato</p>
                </div>
            </div>
            <div class="text-right">
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200">
                    🔒 Ambiente Seguro
                </span>
            </div>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 pt-6 space-y-6">
        <!-- Feedback Messages -->
        @if(session('sucesso'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-start gap-3 shadow-xs">
                <span class="text-xl">✅</span>
                <div class="text-sm font-medium">{{ session('sucesso') }}</div>
            </div>
        @endif

        @if(session('erro'))
            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 flex items-start gap-3 shadow-xs">
                <span class="text-xl">⚠️</span>
                <div class="text-sm font-medium">{{ session('erro') }}</div>
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm space-y-1">
                <span class="font-bold block mb-1">Por favor, verifique os erros abaixo:</span>
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Card de Boas-vindas e Identificação -->
        <div class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 shadow-xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                <div>
                    <span class="text-xs font-semibold tracking-wider uppercase text-slate-400">Responsável</span>
                    <h2 class="text-lg sm:text-xl font-bold text-slate-900">{{ $interessado->pessoa?->nome ?? 'Família' }}</h2>
                    @if($interessado->pessoa?->telefone)
                        <p class="text-xs text-slate-500 mt-0.5">📞 {{ $interessado->pessoa->telefone }}</p>
                    @endif
                </div>

                @if($interessado->dependentes->isNotEmpty())
                    <div class="bg-slate-50 rounded-xl p-3 border border-slate-100 sm:text-right">
                        <span class="text-[11px] font-semibold uppercase text-slate-400 block mb-0.5">Aluno(s) Pretendente(s)</span>
                        <div class="space-y-0.5">
                            @foreach($interessado->dependentes as $dep)
                                <p class="text-xs font-bold text-slate-800">
                                    {{ $dep->nome_crianca }}
                                    <span class="text-indigo-600 font-normal">({{ $dep->serie?->nome ?? 'Série a definir' }})</span>
                                </p>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- Progresso Geral -->
            <div class="pt-4 space-y-2">
                <div class="flex justify-between items-center text-xs font-semibold">
                    <span class="text-slate-600">Progresso do Checklist:</span>
                    <span class="{{ $progresso['completo'] ? 'text-emerald-600' : 'text-indigo-600' }}">
                        {{ $progresso['aprovados'] }} de {{ $progresso['total'] }} validados ({{ $progresso['percentual'] }}%)
                    </span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                    <div class="h-2.5 rounded-full transition-all duration-500 {{ $progresso['completo'] ? 'bg-emerald-500' : 'bg-indigo-600' }}" style="width: {{ $progresso['percentual'] }}%"></div>
                </div>
                <p class="text-[11px] text-slate-400">
                    Você pode fotografar os documentos originais pelo celular ou enviar arquivos em PDF.
                </p>
            </div>
        </div>

        <!-- Aviso de Transparência e LGPD: Validação Inteligente -->
        <div class="bg-indigo-50/70 border border-indigo-100 rounded-2xl p-4 flex items-start gap-3 text-xs text-indigo-950 shadow-xs">
            <span class="text-xl">🤖</span>
            <div class="space-y-1">
                <span class="font-bold text-indigo-900 block">Validação Inteligente & Proteção de Dados (LGPD)</span>
                <p class="text-indigo-800 leading-relaxed">
                    Para agilizar a sua matrícula e evitar retrabalho, nosso sistema conta com pré-análise assistida por inteligência artificial para verificar a legibilidade e nitidez do arquivo em segundo plano. Os dados são tratados com sigilo absoluto para fins pré-contratuais (Art. 7º, V da LGPD) e a aprovação final é sempre confirmada pela Secretaria Escolar.
                </p>
            </div>
        </div>

        <!-- Lista de Documentos -->
        <div class="space-y-4">
            <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                <span>📋</span> Checklist de Documentação
            </h3>

            @forelse($tiposRequeridos as $tipo)
                @php
                    $inserido = $documentosInseridos->firstWhere('tipo_documento_id', $tipo->id);
                    $status = $inserido?->status;
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
                                @if($tipo->flag_obrigatorio)
                                    <span class="text-[10px] font-semibold uppercase px-2 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200">
                                        Obrigatório
                                    </span>
                                @else
                                    <span class="text-[10px] font-semibold uppercase px-2 py-0.5 rounded bg-slate-100 text-slate-600">
                                        Opcional
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

                    <!-- Retorno da Pré-análise por IA -->
                    @if($inserido && $status !== \App\Enums\SituacaoDocumento::VERIFICADO)
                        @if($inserido->temAnaliseIa())
                            @php
                                $legivel = $inserido->isLegivelIa();
                                $tipoOk = $inserido->confereTipoIa();
                                $score = data_get($inserido->dados_ia, 'score_confianca', 100);
                                $alerta = data_get($inserido->dados_ia, 'mensagem_para_familia') ?? data_get($inserido->dados_ia, 'alerta_para_familia');
                            @endphp

                            @if(! $legivel || ! $tipoOk || $score < 70)
                                <div class="mt-3 p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs flex items-start gap-2.5">
                                    <span class="text-base">⚡</span>
                                    <div class="space-y-1">
                                        <span class="font-bold block text-amber-950">Atenção ao documento enviado:</span>
                                        <p class="text-amber-800">
                                            {{ $alerta ?: 'A imagem parece com pouca nitidez ou pode não corresponder exatamente ao documento solicitado. Se preferir, você pode clicar em "Remover e trocar" para enviar uma versão mais legível antes da aprovação da secretaria.' }}
                                        </p>
                                    </div>
                                </div>
                            @else
                                <div class="mt-3 px-3 py-2 rounded-xl bg-emerald-50/60 border border-emerald-200/70 text-emerald-800 text-xs flex items-center gap-2">
                                    <span>✨</span>
                                    <span><strong>Pré-conferência IA:</strong> Documento identificado com boa legibilidade. Aguarde a validação formal da secretaria.</span>
                                </div>
                            @endif
                        @elseif($inserido->created_at && $inserido->created_at->gt(now()->subMinutes(3)))
                            <div class="mt-3 px-3 py-2 rounded-xl bg-indigo-50/50 border border-indigo-100 text-indigo-700 text-xs flex items-center gap-2 animate-pulse">
                                <span>🤖</span>
                                <span>IA analisando nitidez e legibilidade em segundo plano...</span>
                            </div>
                        @endif
                    @endif

                    <!-- Detalhes do arquivo enviado -->
                    @if($inserido && $inserido->nome_arquivo_original)
                        <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                            <span class="truncate max-w-[280px]">
                                📎 {{ $inserido->nome_arquivo_original }}
                                @if($inserido->created_at)
                                    ({{ $inserido->created_at->format('d/m/Y H:i') }})
                                @endif
                            </span>

                            @if($status !== \App\Enums\SituacaoDocumento::VERIFICADO)
                                <form action="{{ route('candidato.documentos.remover', ['token' => $token, 'documento' => $inserido->id]) }}" method="POST" onsubmit="return confirm('Deseja remover este documento e enviar outro?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:text-rose-800 font-semibold text-xs ml-2">
                                        Remover e trocar
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endif

                    <!-- Formulário de Upload (quando ainda não foi enviado ou foi rejeitado) -->
                    @if(! $inserido || $status === \App\Enums\SituacaoDocumento::REJEITADO)
                        <div class="mt-4 pt-3 border-t border-slate-100">
                            <form action="{{ route('candidato.documentos.upload', ['token' => $token]) }}" method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                                @csrf
                                <input type="hidden" name="tipo_documento_id" value="{{ $tipo->id }}">

                                @if($interessado->dependentes->count() > 1)
                                    <select name="interessado_dependente_id" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-2 text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <option value="">Documento Geral da Família</option>
                                        @foreach($interessado->dependentes as $dep)
                                            <option value="{{ $dep->id }}">Para: {{ $dep->nome_crianca }}</option>
                                        @endforeach
                                    </select>
                                @elseif($interessado->dependentes->count() === 1)
                                    <input type="hidden" name="interessado_dependente_id" value="{{ $interessado->dependentes->first()->id }}">
                                @endif

                                <div class="flex-1 relative">
                                    <input 
                                        type="file" 
                                        name="arquivo" 
                                        id="arquivo_{{ $tipo->id }}"
                                        accept=".pdf,image/jpeg,image/png,image/webp" 
                                        class="text-xs file:mr-2.5 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer w-full"
                                        required
                                        onchange="this.form.submit()"
                                    >
                                </div>

                                <button 
                                    type="submit" 
                                    class="text-xs font-semibold px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition-colors text-center whitespace-nowrap shadow-xs"
                                >
                                    Enviar Arquivo ⬆️
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            @empty
                <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center text-slate-500 text-sm">
                    Nenhum documento obrigatório configurado no momento. A secretaria entrará em contato.
                </div>
            @endforelse
        </div>

        <!-- Dúvidas / Suporte -->
        <div class="bg-indigo-50/60 rounded-2xl border border-indigo-100 p-5 text-center text-xs text-indigo-950 space-y-1">
            <p class="font-bold">Precisa de ajuda com o envio dos documentos?</p>
            <p class="text-indigo-800">
                Nossa equipe de admissões está à disposição para tirar qualquer dúvida. Entre em contato pelo WhatsApp da escola.
            </p>
        </div>
    </main>
</body>
</html>
