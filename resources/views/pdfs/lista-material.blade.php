<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Lista de Material Escolar {{ $lista->periodoLetivo?->nome }} — {{ $lista->serie?->nome }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        h2 { font-size: 12px; margin: 14px 0 4px; }
        .sub { color: #555; margin-bottom: 8px; }
        .rascunho { color: #b45309; font-weight: bold; border: 1px solid #b45309; padding: 3px 6px; display: inline-block; margin-bottom: 8px; }
        .recado { background: #f3f4f6; border-left: 3px solid #9ca3af; padding: 6px 8px; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; }
        td.qtd, th.qtd { width: 9%; text-align: right; }
        td.und, th.und { width: 16%; }
        .nota { margin-top: 14px; font-size: 9px; color: #444; border-top: 1px solid #ddd; padding-top: 6px; }
    </style>
</head>
<body>
    <h1>Lista de Material Escolar — {{ $lista->periodoLetivo?->nome }}</h1>
    <div class="sub">{{ $escola }} · {{ $lista->serie?->nome }}@if ($lista->serie?->curso?->nome_externo) · {{ $lista->serie->curso->nome_externo }}@endif</div>

    @unless ($lista->estaPublicada())
        <div class="rascunho">RASCUNHO — ainda não publicada para as famílias</div>
    @endunless

    @if (filled($lista->observacao))
        <div class="recado">{{ $lista->observacao }}</div>
    @endif

    @forelse ($grupos as $categoria => $itens)
        <h2>{{ $categoria }}</h2>
        <table>
            <thead><tr><th class="qtd">Qtd.</th><th class="und">Unidade</th><th>Item</th></tr></thead>
            <tbody>
                @foreach ($itens as $item)
                    <tr>
                        <td class="qtd">{{ $item->quantidade }}</td>
                        <td class="und">{{ $item->unidade ?: '—' }}</td>
                        <td>{{ $item->descricao }}@if (filled($item->observacao)) <span style="color: #555;">— {{ $item->observacao }}</span>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p>A lista ainda não tem itens.</p>
    @endforelse

    <div class="nota">
        Esta lista traz apenas material de uso individual do aluno. Material de uso coletivo da escola (limpeza, higiene,
        papelaria administrativa, giz, pincel de quadro, toner e semelhantes) é custeado pela mensalidade (Lei nº 9.870/1999,
        art. 1º, § 7º). A escola não exige marca, loja nem kit fechado: compre onde preferir. Itens que o aluno já tem, em bom
        estado, podem ser reaproveitados.
        @if ($lista->publicada_em)
            Publicada em {{ $lista->publicada_em->format('d/m/Y') }}.
        @endif
    </div>
</body>
</html>
