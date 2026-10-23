<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Carga letiva — {{ $periodo->nome }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        .sub { color: #555; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #bbb; padding: 4px 5px; }
        th { background: #f3f4f6; text-align: left; }
        td.n, th.n { text-align: right; }
        .nota { margin-top: 10px; color: #555; font-size: 9px; }
    </style>
</head>
<body>
    @php $fmt = fn ($n) => number_format((float) $n, 1, ',', '.'); @endphp

    <h1>{{ $escola }} — Carga letiva</h1>
    <div class="sub">Período letivo: {{ $periodo->nome }} ({{ $periodo->data_inicio->format('d/m/Y') }} a {{ $periodo->data_fim->format('d/m/Y') }}) · gerado em {{ $geradoEm }}</div>

    <table>
        <thead>
            <tr>
                <th>Turma</th>
                <th>Etapa</th>
                <th class="n">Dias previstos</th>
                <th class="n">Dias realizados</th>
                <th class="n">Dias projetados</th>
                <th class="n">Horas previstas</th>
                <th class="n">Horas realizadas</th>
                <th class="n">Horas projetadas</th>
                <th class="n">Mínimo (h / dias)</th>
                <th class="n">Não realizadas</th>
                <th class="n">Reposições</th>
                <th>Situação</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($apuracoes as $a)
                <tr>
                    <td>{{ $a['turma']->nome }}</td>
                    <td>{{ $a['etapa'] ? ucfirst($a['etapa']) : '—' }}</td>
                    <td class="n">{{ $a['dias_previstos'] }}</td>
                    <td class="n">{{ $a['dias_realizados'] }}</td>
                    <td class="n">{{ $a['dias_projetados'] }}</td>
                    <td class="n">{{ $fmt($a['horas_previstas']) }}</td>
                    <td class="n">{{ $fmt($a['horas_realizadas']) }}</td>
                    <td class="n">{{ $fmt($a['horas_projetadas']) }}</td>
                    <td class="n">{{ $a['minimo'] ? $a['minimo']['horas'].' / '.$a['minimo']['dias'] : '—' }}</td>
                    <td class="n">{{ $a['aulas_nao_realizadas'] }}</td>
                    <td class="n">{{ $a['reposicoes'] }}</td>
                    <td>{{ $a['situacao']->getLabel() }}</td>
                </tr>
            @empty
                <tr><td colspan="12">Não há turmas neste período.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="nota">
        Horas em relógio (60 minutos), somadas da duração de cada aula do cronograma. Aula realizada é a que tem chamada ou conteúdo
        registrado. Dias não letivos do calendário e aulas marcadas como não realizadas ficam de fora; a reposição agendada entra.
        O mínimo e o que vale como dia de efetivo trabalho escolar variam por sistema de ensino: este documento é um apoio ao
        planejamento, e a validação do calendário é do órgão da rede.
    </p>
</body>
</html>
