{{--
    Painel do roteiro de qualificação do lead (SDR): resultado, progresso e o estado de cada campo por dimensão.
    Recebe `$painel` de QualificacaoService::painel(); sem lead salvo, `$painel` é nulo e nada é mostrado.

    Usa um cartão próprio (`.roteiro-q-card`) e não `.fi-section`: o tema do painel aplica `padding: 1.5rem !important`
    em toda `.fi-section`, o que soma recuos dentro da aba e estreita o conteúdo no celular.
--}}
@if (! empty($painel))
    @php
        $cores = ['qualificado' => '#16a34a', 'em_qualificacao' => '#d97706', 'nao_iniciada' => '#9ca3af'];
        $cor = $cores[$painel['resultado']] ?? '#9ca3af';
        $porcento = $painel['total'] > 0 ? (int) round($painel['respondidos'] / $painel['total'] * 100) : 0;
        $marcas = [
            'respondido' => ['✔', '#16a34a'],
            'perguntado' => ['…', '#d97706'],
            'recusou' => ['⊘', '#6b7280'],
            'nao_perguntado' => ['○', '#9ca3af'],
        ];
    @endphp

    <style>
        .roteiro-q { display:grid; gap:.75rem; }
        .roteiro-q-card { border-radius:.75rem; border:1px solid rgba(128,128,128,.25); padding:1rem; }
        .roteiro-q-dimensoes { display:grid; gap:.75rem; grid-template-columns:minmax(0,1fr); }
        .roteiro-q-campo { display:flex; gap:.5rem; align-items:flex-start; padding:.2rem 0; min-width:0; }
        .roteiro-q-campo > span:last-child { min-width:0; overflow-wrap:anywhere; }
        @media (min-width: 768px) { .roteiro-q-dimensoes { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width: 640px) { .roteiro-q-card { padding:.75rem; } }
    </style>

    <div class="roteiro-q">
        <div class="roteiro-q-card">
            <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.5rem;">
                <div>
                    <div style="font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; opacity:.6;">Roteiro de qualificação</div>
                    <div style="font-size:1.1rem; font-weight:600; color:{{ $cor }};">{{ $painel['resultado_rotulo'] }}</div>
                </div>
                <div style="font-size:.85rem; opacity:.8;">
                    {{ $painel['respondidos'] }} de {{ $painel['total'] }} campos-chave para a visita
                </div>
            </div>

            <div style="margin-top:.6rem; height:.5rem; border-radius:999px; background:rgba(128,128,128,.2); overflow:hidden;"
                 role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $porcento }}">
                <div style="width:{{ $porcento }}%; height:100%; background:{{ $cor }};"></div>
            </div>

            @if (! empty($painel['etapa_sugerida']))
                <div style="margin-top:.6rem; font-size:.85rem; color:#16a34a;">
                    Roteiro completo: o lead pode ir para a etapa “{{ $painel['etapa_sugerida'] }}” (botão no topo da página).
                </div>
            @endif

            @if (! empty($painel['alertas']))
                <ul style="margin:.75rem 0 0; padding-left:1.1rem; font-size:.85rem; color:#d97706;">
                    @foreach ($painel['alertas'] as $alerta)
                        <li>{{ $alerta }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="roteiro-q-dimensoes">
            @foreach ($painel['dimensoes'] as $dimensao)
                <div class="roteiro-q-card">
                    <div style="font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; opacity:.7; margin-bottom:.35rem;">
                        {{ $dimensao['rotulo'] }}
                    </div>

                    @foreach ($dimensao['campos'] as $campo)
                        @php [$simbolo, $corMarca] = $marcas[$campo['estado']] ?? $marcas['nao_perguntado']; @endphp
                        <div class="roteiro-q-campo">
                            <span style="color:{{ $corMarca }}; font-weight:700; width:1rem; flex:none; text-align:center;" aria-hidden="true">{{ $simbolo }}</span>
                            <span>
                                <span style="font-weight:{{ $campo['gate'] ? '600' : '400' }};">{{ $campo['rotulo'] }}</span>
                                @if (! empty($campo['valor']))
                                    <span style="opacity:.85;">: {{ $campo['valor'] }}</span>
                                @endif
                                @if (! empty($campo['texto']))
                                    <span style="display:block; font-size:.75rem; opacity:.6;">{{ $campo['texto'] }}</span>
                                @endif
                                @if ($campo['nivel'] === 'aluno')
                                    <span style="display:block; font-size:.7rem; opacity:.5;">por aluno, na aba Dependentes</span>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        <div style="font-size:.75rem; opacity:.6;">
            Campos em destaque são os necessários para marcar a visita. A mensalidade nunca é exigida.
        </div>
    </div>
@endif
