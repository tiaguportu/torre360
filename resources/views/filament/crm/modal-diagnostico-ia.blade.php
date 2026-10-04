<div class="space-y-4 text-sm">
    @php
        $dados = $record->dados_ia ?? [];
        $extraidos = $dados['dados_extraidos'] ?? [];
        $divergencias = $dados['divergencias'] ?? [];
        $score = (int) ($dados['score_confianca'] ?? 0);
        $legivel = $record->isLegivelIa();
        $tipoConfere = $record->confereTipoIa();
        $tipoDetectado = $dados['documento_identificado'] ?? ($dados['tipo_detectado'] ?? 'Desconhecido');
        $alertaFamilia = $dados['mensagem_para_familia'] ?? ($dados['alerta_para_familia'] ?? null);
        $resumo = $dados['motivo_rejeicao_sugerido'] ?? ($dados['resumo'] ?? ($tipoConfere && $legivel ? 'Documento analisado com boa legibilidade e conformidade com o solicitado.' : 'Atenção necessária na conferência deste arquivo.'));
    @endphp

    <!-- Card de Cabeçalho do Diagnóstico -->
    <div class="p-4 rounded-xl border {{ $score >= 70 && $legivel && $tipoConfere ? 'bg-emerald-50/70 border-emerald-200' : 'bg-amber-50/70 border-amber-200' }}">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-2">
                <span class="text-2xl">{{ $score >= 70 && $legivel && $tipoConfere ? '✨' : '⚠️' }}</span>
                <div>
                    <h4 class="font-bold text-slate-900 text-base">
                        {{ $score >= 70 && $legivel && $tipoConfere ? 'Documento Aprovado na Pré-Análise IA' : 'Atenção Necessária na Conferência' }}
                    </h4>
                    <p class="text-xs text-slate-600">
                        Analisado pelo Gemini Vision em {{ $record->analisado_ia_em?->format('d/m/Y H:i:s') ?? 'Agora' }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span class="px-3 py-1 rounded-full text-xs font-bold {{ $score >= 70 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                    Confiança: {{ $score }}%
                </span>
            </div>
        </div>

        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
            <div class="flex items-center gap-1.5">
                <span class="font-semibold text-slate-700">Legibilidade:</span>
                @if($legivel)
                    <span class="text-emerald-700 font-bold inline-flex items-center gap-1">✓ Nítido e Legível</span>
                @else
                    <span class="text-rose-700 font-bold inline-flex items-center gap-1">✕ Baixa Resolução / Ilegível</span>
                @endif
            </div>

            <div class="flex items-center gap-1.5">
                <span class="font-semibold text-slate-700">Tipo do Documento:</span>
                @if($tipoConfere)
                    <span class="text-emerald-700 font-bold inline-flex items-center gap-1">✓ Condizente ({{ $tipoDetectado }})</span>
                @else
                    <span class="text-rose-700 font-bold inline-flex items-center gap-1">✕ Divergente (Detectado: {{ $tipoDetectado }})</span>
                @endif
            </div>
        </div>

        @if($alertaFamilia)
            <div class="mt-3 p-2.5 rounded-lg bg-white/80 border border-amber-200 text-xs text-amber-900">
                <span class="font-bold">Orientação sugerida para a família:</span>
                <p class="mt-0.5">{{ $alertaFamilia }}</p>
            </div>
        @endif
    </div>

    <!-- Divergências Detectadas -->
    @if(!empty($divergencias))
        <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-950 space-y-1.5">
            <div class="font-bold flex items-center gap-1.5 text-rose-900">
                <span>⚠️</span> Inconsistências / Alertas Detectados pelo Perito IA:
            </div>
            <ul class="list-disc list-inside space-y-1 text-rose-900">
                @foreach($divergencias as $div)
                    <li>{{ $div }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Dados Extraídos via OCR & IA -->
    <div class="border border-slate-200 rounded-xl overflow-hidden bg-white shadow-xs">
        <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <span class="font-bold text-xs uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                <span>🔍</span> Dados Extraídos do Documento Original
            </span>
            <span class="text-[11px] text-slate-500">Extração segura de campos</span>
        </div>

        <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            <div>
                <span class="text-slate-500 block">Nome Completo:</span>
                <span class="font-semibold text-slate-900">{{ $extraidos['nome_completo'] ?? 'Não identificado' }}</span>
            </div>

            <div>
                <span class="text-slate-500 block">CPF:</span>
                <span class="font-semibold text-slate-900 font-mono">{{ $extraidos['cpf'] ?? 'Não identificado' }}</span>
            </div>

            <div>
                <span class="text-slate-500 block">RG / Identidade:</span>
                <span class="font-semibold text-slate-900 font-mono">{{ $extraidos['rg'] ?? 'Não identificado' }}</span>
            </div>

            <div>
                <span class="text-slate-500 block">Data de Nascimento:</span>
                <span class="font-semibold text-slate-900">{{ $extraidos['data_nascimento'] ?? 'Não identificado' }}</span>
            </div>

            <div>
                <span class="text-slate-500 block">Nome da Mãe:</span>
                <span class="font-semibold text-slate-900">{{ $extraidos['nome_mae'] ?? 'Não identificado' }}</span>
            </div>

            <div>
                <span class="text-slate-500 block">Nome do Pai:</span>
                <span class="font-semibold text-slate-900">{{ $extraidos['nome_pai'] ?? 'Não identificado' }}</span>
            </div>

            @if(!empty($extraidos['endereco_completo']))
                <div class="sm:col-span-2">
                    <span class="text-slate-500 block">Endereço Identificado:</span>
                    <span class="font-semibold text-slate-900">{{ $extraidos['endereco_completo'] }}</span>
                </div>
            @endif
        </div>
    </div>

    <!-- Parecer Resumido -->
    <div class="text-xs text-slate-600 bg-slate-50 p-3 rounded-lg border border-slate-200">
        <span class="font-bold text-slate-700 block mb-1">Parecer Geral da IA:</span>
        <p class="leading-relaxed">{{ $resumo }}</p>
    </div>
</div>
