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

<div style="display:grid; gap:1rem; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); align-items:stretch;">
    {{-- Score --}}
    <div class="fi-section" style="padding:1.25rem; display:flex; align-items:center; gap:1.25rem; border-radius:.75rem; border:1px solid rgba(128,128,128,.25);">
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
        <div>
            <div style="font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; opacity:.6;">Lead Score (automático)</div>
            <div style="font-size:1.1rem; font-weight:600; color:{{ $cor }};">{{ $faixa }}</div>
            @if($record->lead_score_atualizado_em)
                <div style="font-size:.75rem; opacity:.6; margin-top:.25rem;">Atualizado em {{ \Illuminate\Support\Carbon::parse($record->lead_score_atualizado_em)->format('d/m/Y H:i') }}</div>
            @endif
        </div>
    </div>

    {{-- Métricas --}}
    <div style="display:grid; gap:.75rem; grid-template-columns:repeat(auto-fit,minmax(150px,1fr));">
        @foreach($metricas as $m)
            @php $corM = ! empty($m['alerta']) ? '#dc2626' : 'inherit'; @endphp
            <div class="fi-section" style="padding:1rem; border-radius:.75rem; border:1px solid {{ ! empty($m['alerta']) ? '#dc2626' : 'rgba(128,128,128,.25)' }}; display:flex; flex-direction:column; justify-content:center; gap:.25rem;">
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
<div class="fi-section" style="margin-top:1rem; padding:1.25rem; border-radius:.75rem; border:1px solid rgba(128,128,128,.25);">
    <div style="font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; opacity:.7; margin-bottom:.75rem;">Detalhamento do score</div>
    <div style="display:grid; gap:.6rem 2rem; grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
        @foreach($fatores as $f)
            @php
                $pct = $f['maximo'] > 0 ? round($f['pontos'] / $f['maximo'] * 100) : 0;
                $corF = $pct >= 70 ? '#16a34a' : ($pct >= 35 ? '#d97706' : '#dc2626');
            @endphp
            <div>
                <div style="display:flex; justify-content:space-between; font-size:.8rem; margin-bottom:.2rem;">
                    <span>{{ $f['fator'] }}</span>
                    <span style="font-weight:600;">{{ $f['pontos'] }}<span style="opacity:.5; font-weight:400;"> / {{ $f['maximo'] }}</span></span>
                </div>
                <div style="height:6px; border-radius:999px; background:rgba(128,128,128,.2); overflow:hidden;">
                    <div style="height:100%; width:{{ $pct }}%; background:{{ $corF }}; border-radius:999px;"></div>
                </div>
            </div>
        @endforeach
    </div>
</div>
