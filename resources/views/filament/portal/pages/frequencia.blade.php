@php
    $resumo = $this->getResumo();
    $minima = \App\Services\FrequenciaAlunoService::FREQUENCIA_MINIMA;
    $formatar = fn (?float $valor): string => $valor === null ? '—' : number_format($valor, 1, ',', '.').'%';
@endphp

<x-filament-panels::page>
    @include('filament.portal.partials.estilos')

    <div class="pf-stack">
        @include('filament.portal.partials.seletor-aluno')

        @if ($resumo === null)
            <div class="pf-vazio">Nenhum aluno vinculado ao seu cadastro foi encontrado. Entre em contato com a secretaria caso isso não esteja correto.</div>
        @elseif ($resumo['total'] === 0)
            <div class="pf-vazio">Ainda não há registros de frequência para este aluno.</div>
        @else
            @if ($resumo['abaixo_minimo'])
                <div class="pf-aviso" role="alert">
                    A frequência geral está em <strong>{{ $formatar($resumo['percentual']) }}</strong>, abaixo do mínimo exigido de {{ $formatar($minima) }}. Em caso de dúvida, procure a secretaria.
                </div>
            @endif

            <div class="pf-cards">
                <div class="pf-card">
                    <div class="pf-valor {{ $resumo['abaixo_minimo'] ? 'pf-baixo' : 'pf-ok' }}">{{ $formatar($resumo['percentual']) }}</div>
                    <div class="pf-rotulo">Frequência geral</div>
                </div>
                <div class="pf-card">
                    <div class="pf-valor">{{ $resumo['total'] }}</div>
                    <div class="pf-rotulo">Aulas registradas</div>
                </div>
                <div class="pf-card">
                    <div class="pf-valor">{{ $resumo['presencas'] }}</div>
                    <div class="pf-rotulo">Presenças</div>
                </div>
                <div class="pf-card">
                    <div class="pf-valor {{ $resumo['faltas'] > 0 ? 'pf-baixo' : '' }}">{{ $resumo['faltas'] }}</div>
                    <div class="pf-rotulo">Faltas</div>
                </div>
            </div>

            <section class="pf-box">
                <h3>Por disciplina</h3>
                <div class="pf-scroll">
                    <table class="pf-tabela">
                        <thead>
                            <tr>
                                <th>Disciplina</th>
                                <th class="pf-num">Aulas</th>
                                <th class="pf-num">Presenças</th>
                                <th class="pf-num">Faltas</th>
                                <th class="pf-num">Frequência</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($resumo['por_disciplina'] as $linha)
                                <tr>
                                    <td>{{ $linha['nome'] }}</td>
                                    <td class="pf-num">{{ $linha['total'] }}</td>
                                    <td class="pf-num">{{ $linha['presencas'] }}</td>
                                    <td class="pf-num">{{ $linha['faltas'] }}</td>
                                    <td class="pf-num {{ $linha['abaixo_minimo'] ? 'pf-baixo' : '' }}">{{ $formatar($linha['percentual']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <div>
                {{ $this->table }}
            </div>
        @endif
    </div>
</x-filament-panels::page>
