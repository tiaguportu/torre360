<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Ordens de Serviço</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        h1 { text-align: center; }
    </style>
</head>
<body>
    <h1>Relatório de Ordens de Serviço</h1>
    <p>Gerado em: {{ now()->format('d/m/Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Título</th>
                <th>Prioridade</th>
                <th>Status</th>
                <th>Prazo</th>
                <th>Conclusão</th>
                <th>Custo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $os)
            <tr>
                <td>{{ $os->id }}</td>
                <td>{{ $os->titulo }}</td>
                <td>{{ $os->prioridade }}</td>
                <td>{{ $os->status }}</td>
                <td>{{ $os->prazo_conclusao ? $os->prazo_conclusao->format('d/m/Y') : '-' }}</td>
                <td>{{ $os->percentual_conclusao }}%</td>
                <td>R$ {{ number_format($os->custo_estimado, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
