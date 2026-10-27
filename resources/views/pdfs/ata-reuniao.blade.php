<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Ata — {{ $reuniao->titulo }}</title>
    <style>
        @page { margin: 28mm 20mm 22mm 20mm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #1f2937; line-height: 1.45; }
        h1 { font-size: 17px; margin: 0 0 4px 0; }
        h2 { font-size: 12px; margin: 18px 0 6px 0; padding-bottom: 3px; border-bottom: 1px solid #9ca3af; text-transform: uppercase; letter-spacing: .04em; }
        .sub { color: #4b5563; margin: 0 0 10px 0; }
        .selo { display: inline-block; padding: 2px 8px; border: 1px solid #b45309; color: #b45309; font-size: 10px; margin-top: 4px; }
        .minuta { display: inline-block; padding: 2px 8px; border: 1px solid #6b7280; color: #6b7280; font-size: 10px; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th { text-align: left; background: #f3f4f6; font-size: 10px; padding: 4px 6px; border: 1px solid #d1d5db; }
        td { padding: 4px 6px; border: 1px solid #e5e7eb; vertical-align: top; }
        .texto { white-space: normal; }
        .assinaturas { margin-top: 36px; }
        .linha-assinatura { display: inline-block; width: 45%; margin: 26px 2% 0 0; border-top: 1px solid #374151; padding-top: 3px; font-size: 10px; }
        .rodape { margin-top: 24px; font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>
    <h1>Ata — {{ $reuniao->titulo }}</h1>
    <p class="sub">
        {{ $reuniao->tipo->getLabel() }}
        · {{ $reuniao->inicio_em->format('d/m/Y') }}, das {{ $reuniao->inicio_em->format('H:i') }}@if($reuniao->fim_em) às {{ $reuniao->fim_em->format('H:i') }}@endif
        @if($reuniao->local) · {{ $reuniao->local }}@endif
    </p>
    @if($reuniao->ehConselho() && $reuniao->turma)
        <p class="sub">Turma: {{ $reuniao->turma->nome }}@if($reuniao->etapaAvaliativa) · Etapa: {{ $reuniao->etapaAvaliativa->nome }}@endif</p>
    @endif
    @if($reuniao->ehSigilosa())
        <span class="selo">Documento sigiloso</span>
    @endif
    @unless($reuniao->ataEstaFechada())
        <span class="minuta">Minuta: a ata ainda não foi fechada</span>
    @endunless

    <h2>Participantes</h2>
    <table>
        <thead>
            <tr><th>Nome</th><th style="width: 22%">Papel</th><th style="width: 18%">Presença</th></tr>
        </thead>
        <tbody>
            @foreach($reuniao->participantes as $participante)
                <tr>
                    <td>{{ $participante->user?->name ?? '—' }}</td>
                    <td>{{ match ($participante->papel) { 'organizador' => 'Organizador', 'presidente' => 'Presidente', default => 'Participante' } }}</td>
                    <td>{{ $participante->presente === null ? 'Não registrada' : ($participante->presente ? 'Presente' : 'Ausente') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if(filled($reuniao->pauta))
        <h2>Pauta</h2>
        <div class="texto">{!! nl2br(e($reuniao->pauta)) !!}</div>
    @endif

    <h2>Registro da reunião</h2>
    <div class="texto">
        @if(filled($reuniao->ata))
            {!! nl2br(e($reuniao->ata)) !!}
        @else
            <em>Sem texto registrado.</em>
        @endif
    </div>

    @if($reuniao->ehConselho() && $reuniao->alunos->isNotEmpty())
        <h2>Deliberações por aluno</h2>
        <table>
            <thead>
                <tr>
                    <th>Aluno</th>
                    <th style="width: 10%">Média</th>
                    <th style="width: 12%">Frequência</th>
                    <th style="width: 28%">Deliberação</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reuniao->alunos as $linha)
                    @php($ind = $linha->indicadores ?? [])
                    <tr>
                        <td>{{ $linha->matricula?->pessoa?->nome ?? '—' }}</td>
                        <td>{{ isset($ind['media_geral']) ? number_format((float) $ind['media_geral'], 1, ',', '.') : '—' }}</td>
                        <td>{{ isset($ind['frequencia']) ? number_format((float) $ind['frequencia'], 1, ',', '.').'%' : '—' }}</td>
                        <td>
                            <strong>{{ $deliberacoes[$linha->deliberacao] ?? ($linha->deliberacao ?: 'Sem deliberação') }}</strong>
                            @if(filled($linha->observacao))<br>{{ $linha->observacao }}@endif
                            @foreach($linha->encaminhamentos as $enc)<br><em>Encaminhamento: {{ $enc->descricao }}@if($enc->responsavel) ({{ $enc->responsavel->name }}@if($enc->prazo), até {{ $enc->prazo->format('d/m/Y') }}@endif)@endif</em>@endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @php($gerais = $reuniao->encaminhamentos->whereNull('reuniao_aluno_id'))
    @if($gerais->isNotEmpty())
        <h2>Encaminhamentos</h2>
        <table>
            <thead>
                <tr><th>O que foi combinado</th><th style="width: 24%">Responsável</th><th style="width: 14%">Prazo</th></tr>
            </thead>
            <tbody>
                @foreach($gerais as $enc)
                    <tr>
                        <td>{{ $enc->descricao }}</td>
                        <td>{{ $enc->responsavel?->name ?? '—' }}</td>
                        <td>{{ $enc->prazo?->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($reuniao->adendos->isNotEmpty())
        <h2>Adendos</h2>
        @foreach($reuniao->adendos as $adendo)
            <p class="texto">{!! nl2br(e($adendo->texto)) !!}<br><span class="rodape">{{ $adendo->autor?->name ?? 'Sistema' }}, em {{ $adendo->created_at->format('d/m/Y H:i') }}</span></p>
        @endforeach
    @endif

    <div class="assinaturas">
        @foreach($reuniao->participantes->where('presente', true) as $participante)
            <span class="linha-assinatura">{{ $participante->user?->name }}</span>
        @endforeach
    </div>

    <p class="rodape">
        @if($reuniao->ataEstaFechada())
            Ata fechada em {{ $reuniao->ata_fechada_em->format('d/m/Y H:i') }}@if($reuniao->ataFechadaPor) por {{ $reuniao->ataFechadaPor->name }}@endif.
        @endif
        Documento gerado pelo sistema em {{ now()->format('d/m/Y H:i') }}.
    </p>
</body>
</html>
