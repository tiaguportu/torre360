@php
    $score = $record->lead_score;
    $cor = $score === null ? '#9ca3af' : ($score >= 70 ? '#16a34a' : ($score >= 40 ? '#d97706' : '#dc2626'));
    $faixa = $score === null ? 'Não calculado' : ($score >= 70 ? 'Lead quente' : ($score >= 40 ? 'Lead morno' : 'Lead frio'));
    $circ = 2 * pi() * 42;
    $offset = $circ * (1 - ($score ?? 0) / 100);
    $dias = $record->diasNoFunil();
    $semInteracao = $record->diasSemInteracao();
    $estagnado = $record->estaEstagnado();
    $metricas = [
        ['rotulo' => 'Dias no funil', 'valor' => $dias, 'sufixo' => $dias === 1 ? 'dia' : 'dias', 'icone' => 'heroicon-o-calendar-days'],
        ['rotulo' => 'Total de contatos', 'valor' => $record->totalContatos(), 'sufixo' => 'contato(s)', 'icone' => 'heroicon-o-chat-bubble-left-right'],
        ['rotulo' => 'Sem interação há', 'valor' => $semInteracao, 'sufixo' => $semInteracao === 1 ? 'dia' : 'dias', 'icone' => 'heroicon-o-clock', 'alerta' => $estagnado],
    ];
@endphp

{{--
    Espaçamentos do Resumo. `.lead-resumo-secao` é posto na Section pelo InteressadoForm (extraAttributes).

    Por que `!important` + seletor com `.lead-resumo-secao`: o tema do painel (resources/css/filament/admin/theme.css)
    aplica `.fi-section { padding:1.5rem !important }` em TODA seção, inclusive na própria seção do Resumo e nos
    cartões daqui (que usam `.fi-section` pelo visual de caixa). Um `padding` inline ou de classe simples nunca vale
    contra isso; somavam-se aba 24px + seção 24px + conteúdo 24px + cartão 24px por lado e, em 360px, sobravam
    ~136px de conteúdo. A regra mais específica abaixo vale só aqui, sem mexer nas demais seções do painel.
--}}
<style>
    .lead-resumo-topo { display:grid; gap:1rem; grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr)); align-items:stretch; }
    .lead-resumo-score { display:flex; align-items:center; gap:1.25rem; }
    .lead-resumo-metricas { display:grid; gap:.75rem; grid-template-columns:repeat(auto-fit,minmax(min(100%,150px),1fr)); }
    .lead-resumo-card,
    .lead-resumo-metrica { border-radius:.75rem; border:1px solid rgba(128,128,128,.25); }
    .lead-resumo-metrica { display:flex; flex-direction:column; justify-content:center; gap:.25rem; }
    .lead-resumo-detalhe { margin-top:1rem; }
    /* Detalhamento: 1 coluna no celular, 2 a partir de 768px (minmax(0,1fr): a barra nunca passa da tela) */
    .lead-score-fatores { display:grid; gap:.6rem 2rem; grid-template-columns:minmax(0,1fr); }

    /* A seção perde o padding extra do tema: o recuo fica por conta do cabeçalho e do conteúdo (padrão do Filament). */
    .lead-resumo-secao section.fi-section { padding:0 !important; }
    .lead-resumo-secao .lead-resumo-card { padding:1.25rem !important; }
    .lead-resumo-secao .lead-resumo-metrica { padding:1rem !important; }
    /* Lead estagnado: o tema zera `border-width` de `.fi-section` com !important, então a borda não aparece;
       `outline` não depende disso, não desloca o layout e acompanha o raio dos cantos. */
    .lead-resumo-secao .lead-resumo-alerta { outline:1px solid #dc2626; outline-offset:-1px; }

    @media (min-width: 768px) {
        .lead-score-fatores { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }

    @media (max-width: 640px) {
        .lead-resumo-secao .fi-section-header { padding:.625rem .75rem !important; }
        .lead-resumo-secao .fi-section-content { padding:.5rem !important; }
        .lead-resumo-secao .lead-resumo-card { padding:.75rem !important; }
        .lead-resumo-secao .lead-resumo-metrica { padding:.625rem .75rem !important; }
        .lead-resumo-topo,
        .lead-resumo-metricas { gap:.5rem; }
        .lead-resumo-score { gap:.75rem; }
        .lead-resumo-detalhe { margin-top:.5rem; }
        .lead-score-fatores { gap:.5rem; }
    }
</style>

<div class="lead-resumo-topo">
    {{-- Score --}}
    <div class="fi-section lead-resumo-card lead-resumo-score">
        <div style="position:relative; width:104px; height:104px; flex:none;">
            <svg viewBox="0 0 100 100" width="104" height="104" style="transform:rotate(-90deg);">
                <circle cx="50" cy="50" r="42" fill="none" stroke="rgba(128,128,128,.25)" stroke-width="9"/>
                <circle cx="50" cy="50" r="42" fill="none" stroke="{{ $cor }}" stroke-width="9" stroke-linecap="round"
                        stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $offset }}"/>
            </svg>
            <div style="position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                <span style="font-size:1.6rem; font-weight:700; line-height:1; color:{{ $cor }};">{{ $score ?? '—' }}</span>
                <span style="font-size:.65rem; opacity:.6;">de 100</span>
            </div>
        </div>
        <div style="min-width:0;">
            <div style="font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; opacity:.6; overflow-wrap:anywhere;">Lead Score (automático)</div>
            <div style="font-size:1.1rem; font-weight:600; color:{{ $cor }};">{{ $faixa }}</div>
            @if($record->lead_score_atualizado_em)
                <div style="font-size:.75rem; opacity:.6; margin-top:.25rem;">Atualizado em {{ \Illuminate\Support\Carbon::parse($record->lead_score_atualizado_em)->format('d/m/Y H:i') }}</div>
            @endif
        </div>
    </div>

    {{-- Métricas --}}
    <div class="lead-resumo-metricas">
        @foreach($metricas as $m)
            @php $corM = ! empty($m['alerta']) ? '#dc2626' : 'inherit'; @endphp
            <div @class(['fi-section', 'lead-resumo-metrica', 'lead-resumo-alerta' => ! empty($m['alerta'])])>
                <div style="display:flex; align-items:center; gap:.4rem; font-size:.75rem; opacity:.7; color:{{ $corM }};">
                    <x-filament::icon :icon="$m['icone']" style="width:1rem; height:1rem;"/>
                    {{ $m['rotulo'] }}
                </div>
                <div style="font-size:1.6rem; font-weight:700; line-height:1.1; color:{{ $corM }};">
                    {{ $m['valor'] }} <span style="font-size:.8rem; font-weight:500; opacity:.6;">{{ $m['sufixo'] }}</span>
                </div>
                @if(! empty($m['alerta']))
                    <div style="font-size:.7rem; color:#dc2626;">Lead estagnado</div>
                @endif
            </div>
        @endforeach
    </div>
</div>

{{-- Detalhamento --}}
<div
    x-data="{ aberto: false }"
    wire:ignore.self
    class="fi-section lead-resumo-card lead-resumo-detalhe"
>
    <button
        type="button"
        @click="aberto = ! aberto"
        :aria-expanded="aberto.toString()"
        aria-controls="lead-score-fatores-bloco"
        style="width:100%; display:flex; align-items:center; justify-content:space-between; background:transparent; border:none; padding:0; cursor:pointer; text-align:left; color:inherit;"
    >
        <div style="display:flex; flex-direction:column; gap:.2rem;">
            <div style="font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; opacity:.7;">Detalhamento do score</div>
            <div style="font-size:.75rem; color:#2563eb; text-decoration:underline;">Ver como o score foi calculado</div>
        </div>
        <div style="transition:transform .2s ease;" :style="aberto ? 'transform:rotate(180deg)' : ''">
            <svg style="width:1.25rem; height:1.25rem; opacity:.7;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </div>
    </button>

    <div
        id="lead-score-fatores-bloco"
        x-show="aberto"
        x-cloak
        style="margin-top:.75rem; padding-top:.75rem; border-top:1px solid rgba(128,128,128,.15);"
    >
        <div class="lead-score-fatores">
            @foreach($fatores as $f)
                @php
                    $pct = $f['maximo'] > 0 ? round($f['pontos'] / $f['maximo'] * 100) : 0;
                    $corF = $pct >= 70 ? '#16a34a' : ($pct >= 35 ? '#d97706' : '#dc2626');
                @endphp
                <div style="min-width:0;">
                    <div style="display:flex; justify-content:space-between; gap:.5rem; font-size:.8rem; margin-bottom:.2rem;">
                        <span style="min-width:0; overflow-wrap:anywhere;">{{ $f['fator'] }}</span>
                        <span style="flex:none; white-space:nowrap; font-weight:600;">{{ $f['pontos'] }}<span style="opacity:.5; font-weight:400;"> / {{ $f['maximo'] }}</span></span>
                    </div>
                    <div style="height:6px; border-radius:999px; background:rgba(128,128,128,.2); overflow:hidden;">
                        <div style="height:100%; width:{{ $pct }}%; background:{{ $corF }}; border-radius:999px;"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
