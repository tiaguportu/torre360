<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Comprovante de Registro de Ponto</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 15px; margin: 0 0 4px; }
        .sub { color: #555; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 5px 6px; border-bottom: 1px solid #ddd; vertical-align: top; }
        td.rotulo { width: 32%; color: #555; }
        .hash { font-family: DejaVu Sans Mono, monospace; font-size: 8px; word-break: break-all; }
        .faixa { margin: 12px 0; padding: 8px; border: 2px solid #b45309; background: #fffbeb; color: #7c2d12; font-weight: bold; text-align: center; }
        .ok { border-color: #15803d; background: #f0fdf4; color: #14532d; }
        .rodape { margin-top: 14px; font-size: 9px; color: #555; }
    </style>
</head>
<body>
    <h1>Comprovante de Registro de Ponto</h1>
    <div class="sub">NSR {{ $comprovante['nsr'] }} · {{ $comprovante['modo'] }}</div>

    @if ($comprovante['assinado'])
        <div class="faixa ok">Assinado eletronicamente — {{ $comprovante['assinador'] }}</div>
    @else
        <div class="faixa">NÃO ASSINADO — registro de controle interno, sem assinatura eletrônica ICP-Brasil. Não substitui o comprovante de um REP oficial (Portaria MTP 671/2021).</div>
    @endif

    <table>
        <tr><td class="rotulo">Empregador</td><td>{{ $comprovante['empregador'] }}</td></tr>
        <tr><td class="rotulo">Estabelecimento</td><td>{{ $comprovante['estabelecimento'] }}</td></tr>
        <tr><td class="rotulo">CNPJ</td><td>{{ $comprovante['cnpj'] ?: 'Não informado' }}</td></tr>
        <tr><td class="rotulo">Trabalhador</td><td>{{ $comprovante['trabalhador'] }}</td></tr>
        <tr><td class="rotulo">CPF</td><td>{{ $comprovante['cpf'] ?: 'Não informado' }}</td></tr>
        <tr><td class="rotulo">Data e hora do registro</td><td><strong>{{ $comprovante['data_hora'] }}</strong> (UTC{{ $comprovante['fuso'] }})</td></tr>
        <tr><td class="rotulo">Origem</td><td>{{ $comprovante['origem'] }}</td></tr>
        <tr><td class="rotulo">Hora legal conferida</td><td>{{ $comprovante['hora_legal_ok'] ? 'Sim (variação dentro de 30 s)' : 'Não conferida no momento do registro' }}</td></tr>
        <tr><td class="rotulo">Código de verificação (SHA-256)</td><td class="hash">{{ $comprovante['hash'] }}</td></tr>
        @if ($comprovante['assinado'])
            <tr><td class="rotulo">Assinatura</td><td class="hash">{{ $comprovante['assinatura'] }}</td></tr>
        @endif
    </table>

    <div class="rodape">Guarde este comprovante. Divergências podem ser contestadas ao RH com um pedido de ajuste; o registro original nunca é alterado.</div>
</body>
</html>
