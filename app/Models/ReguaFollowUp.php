<?php

namespace App\Models;

use App\Enums\CanalReguaFollowUp;
use App\Enums\GatilhoReguaFollowUp;
use App\Enums\StatusVisitaInteressado;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReguaFollowUp extends Model
{
    use HasFactory;

    protected $table = 'regua_follow_ups';

    protected $fillable = [
        'nome',
        'gatilho',
        'dias_offset',
        'canal',
        'assunto',
        'mensagem',
        'origem_interessado_id',
        'status_interessado_id',
        'is_ativo',
        'horario_envio',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'gatilho' => GatilhoReguaFollowUp::class,
            'canal' => CanalReguaFollowUp::class,
            'is_ativo' => 'boolean',
            'dias_offset' => 'integer',
            'ordem' => 'integer',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ReguaFollowUpLog::class);
    }

    public function origem(): BelongsTo
    {
        return $this->belongsTo(OrigemInteressado::class, 'origem_interessado_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(StatusInteressado::class, 'status_interessado_id');
    }

    public function getGatilhoDescricaoAttribute(): string
    {
        $dias = $this->dias_offset;

        return match ($this->gatilho) {
            GatilhoReguaFollowUp::LeadCriado => $dias === 0
                ? 'No dia do cadastro'
                : "{$dias} dia(s) após o cadastro",
            GatilhoReguaFollowUp::VisitaLembrete => $dias === 0
                ? 'No dia da visita'
                : "{$dias} dia(s) antes da visita",
            GatilhoReguaFollowUp::VisitaRealizada => $dias === 0
                ? 'No dia da visita realizada'
                : "{$dias} dia(s) após a visita realizada",
            GatilhoReguaFollowUp::VisitaFaltou => $dias === 0
                ? 'No dia da falta na visita'
                : "{$dias} dia(s) após a falta (no-show)",
            GatilhoReguaFollowUp::LeadEstagnado => "{$dias} dia(s) sem qualquer interação",
            GatilhoReguaFollowUp::ContatoAtrasado => $dias === 0
                ? 'No dia do vencimento do contato'
                : "{$dias} dia(s) de contato atrasado",
            default => "Offset: {$dias} dia(s)",
        };
    }

    /**
     * Interpola as variáveis dinâmicas no assunto e no corpo da mensagem.
     *
     * @param  string|null  $nomeEscola  nome já resolvido pelo chamador (a régua o busca uma vez por execução, não por mensagem)
     * @return array{assunto: string, mensagem: string}
     */
    public function interpolarMensagem(Interessado $interessado, ?VisitaInteressado $visita = null, ?string $nomeEscola = null): array
    {
        $pessoa = $interessado->pessoa;
        $nomeResponsavel = $pessoa?->nome ?? 'Família';
        $primeiroNome = explode(' ', trim($nomeResponsavel))[0];

        // Dependentes / Alunos
        $dependenteVisita = $visita?->dependente;
        $nomeAluno = $dependenteVisita?->nome_crianca
            ?? $interessado->dependentes->pluck('nome_crianca')->filter()->join(', ')
            ?: 'seu filho(a)';

        $serieInteresse = $dependenteVisita?->serie?->nome
            ?? $interessado->dependentes->first()?->serie?->nome
            ?: 'nossas turmas';

        $nomeConsultor = $interessado->usuario?->name ?? 'Equipe de Admissões';
        $nomeEscola ??= Unidade::first()?->nome ?? InstituicaoEnsino::first()?->nome ?? 'Nossa Escola';

        $dataVisita = $visita?->data_hora ? $visita->data_hora->format('d/m/Y') : '';
        $horarioVisita = $visita?->data_hora ? $visita->data_hora->format('H:i') : '';

        // Link da Pesquisa NPS de Visita (se houver visita associada ou realizada)
        $linkPesquisa = '';
        if ($visita) {
            $linkPesquisa = $visita->obterOuCriarPesquisa()->url_publica;
        } elseif ($visitaRealizada = $interessado->visitas()->where('status', StatusVisitaInteressado::Realizada)->latest('data_hora')->first()) {
            // Uma consulta só, e pelo enum: o valor gravado é 'realizada' e a comparação com 'Realizada' só
            // funcionava porque o MySQL ignora a caixa (em SQLite/PostgreSQL o link da pesquisa nunca saía).
            $linkPesquisa = $visitaRealizada->obterOuCriarPesquisa()->url_publica;
            if (empty($dataVisita) && $visitaRealizada->data_hora) {
                $dataVisita = $visitaRealizada->data_hora->format('d/m/Y');
                $horarioVisita = $visitaRealizada->data_hora->format('H:i');
            }
        }

        $placeholders = [
            '{{NOME_RESPONSAVEL}}' => $nomeResponsavel,
            '{{PRIMEIRO_NOME}}' => $primeiroNome,
            '[Nome]' => $primeiroNome,
            '{{NOME_ALUNO}}' => $nomeAluno,
            '[Aluno]' => $nomeAluno,
            '{{SERIE_INTERESSE}}' => $serieInteresse,
            '[Serie]' => $serieInteresse,
            '{{NOME_CONSULTOR}}' => $nomeConsultor,
            '[Consultor]' => $nomeConsultor,
            '{{ESCOLA_NOME}}' => $nomeEscola,
            '[Escola]' => $nomeEscola,
            '{{DATA_VISITA}}' => $dataVisita,
            '[DataVisita]' => $dataVisita,
            '{{HORARIO_VISITA}}' => $horarioVisita,
            '[HorarioVisita]' => $horarioVisita,
            '{{LINK_PESQUISA}}' => $linkPesquisa,
            '[LinkPesquisa]' => $linkPesquisa,
            '{{EMAIL_RESPONSAVEL}}' => $pessoa?->email ?? '',
            '{{TELEFONE_RESPONSAVEL}}' => $pessoa?->telefone_formatado ?? $pessoa?->telefone ?? '',
        ];

        $assuntoProcessado = str_replace(array_keys($placeholders), array_values($placeholders), $this->assunto);
        $mensagemProcessada = str_replace(array_keys($placeholders), array_values($placeholders), $this->mensagem);

        return [
            'assunto' => $assuntoProcessado,
            'mensagem' => $mensagemProcessada,
        ];
    }
}
