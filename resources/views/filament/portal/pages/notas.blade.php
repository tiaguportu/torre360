@php
    $dados = $this->getDados();
    $formatar = fn (?float $valor): string => $valor === null ? '—' : number_format($valor, 1, ',', '.');
@endphp

<x-filament-panels::page>
    @include('filament.portal.partials.estilos')

    <div class="pf-stack">
        @include('filament.portal.partials.seletor-aluno')

        @if (! $dados['matricula'])
            <div class="pf-vazio">Nenhum aluno vinculado ao seu cadastro foi encontrado. Entre em contato com a secretaria caso isso não esteja correto.</div>
        @else
            <p class="pf-muted">
                Notas de {{ $dados['matricula']->pessoa?->nome }}. Nota mínima de aprovação: {{ $formatar($dados['nota_aprovacao']) }}.
                Notas em vermelho estão abaixo da média.
            </p>

            @forelse ($dados['etapas'] as $etapa)
                <section class="pf-box">
                    <h3>{{ $etapa['etapa']->nome }}</h3>

                    <div class="pf-scroll">
                        <table class="pf-tabela">
                            <thead>
                                <tr>
                                    <th>Disciplina</th>
                                    @foreach ($etapa['categorias'] as $categoria)
                                        <th class="pf-num">{{ $categoria->nome }}</th>
                                    @endforeach
                                    <th class="pf-num">Média</th>
                                    <th class="pf-num">Média da turma</th>
                                    <th class="pf-num">Frequência</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($etapa['linhas'] as $linha)
                                    <tr>
                                        <td>{{ $linha['disciplina']->nome }}</td>
                                        @foreach ($etapa['categorias'] as $categoria)
                                            @php $celula = $linha['categorias'][$categoria->id] ?? null; @endphp
                                            <td class="pf-num" @if ($celula && $celula['is_ignorada']) title="Nota substituída por outra avaliação" style="text-decoration: line-through; opacity: .6" @endif>
                                                {{ $celula && ! $celula['ausente'] ? $formatar($celula['valor']) : '—' }}
                                            </td>
                                        @endforeach
                                        <td class="pf-num {{ $linha['media_final'] !== null && $linha['media_final'] < $dados['nota_aprovacao'] ? 'pf-baixo' : '' }}">
                                            {{ $formatar($linha['media_final']) }}
                                        </td>
                                        <td class="pf-num">{{ $formatar($linha['media_turma']) }}</td>
                                        <td class="pf-num {{ $linha['frequencia'] !== null && $linha['frequencia'] < $dados['frequencia_minima'] ? 'pf-baixo' : '' }}">
                                            {{ $linha['frequencia'] === null ? '—' : number_format($linha['frequencia'], 1, ',', '.').'%' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if (count($etapa['faltas_datas']))
                        <p class="pf-muted" style="margin-top: .5rem">Faltas no período: {{ implode(', ', $etapa['faltas_datas']) }}.</p>
                    @endif
                </section>
            @empty
                @if ($dados['habilidades']->isEmpty())
                    <div class="pf-vazio">Ainda não há notas lançadas para este aluno.</div>
                @endif
            @endforelse

            @if ($dados['habilidades']->isNotEmpty())
                @foreach ($dados['habilidades'] as $nomeEtapa => $notas)
                    <section class="pf-box">
                        <h3>Habilidades — {{ $nomeEtapa }}</h3>

                        <div class="pf-scroll">
                            <table class="pf-tabela">
                                <thead>
                                    <tr>
                                        <th>Habilidade</th>
                                        <th class="pf-num">Conceito</th>
                                        <th>Observação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($notas as $nota)
                                        <tr>
                                            <td class="pf-wrap">
                                                <strong>{{ $nota->habilidade?->codigo }}</strong>
                                                {{ $nota->habilidade?->nome }}
                                                @if ($nota->habilidade?->descricao)
                                                    <div class="pf-muted">{{ $nota->habilidade->descricao }}</div>
                                                @endif
                                            </td>
                                            <td class="pf-num">
                                                @if ($nota->conceito)
                                                    <x-filament::badge :color="$nota->conceito->getColor()">
                                                        {{ $nota->conceito->getLabel() }}
                                                    </x-filament::badge>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="pf-wrap">{{ $nota->observacao ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endforeach
            @endif
        @endif
    </div>
</x-filament-panels::page>
