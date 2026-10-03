<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PesquisaSatisfacaoVisita extends Model
{
    use HasFactory;

    protected $table = 'visita_pesquisa_satisfacao';

    protected $fillable = [
        'visita_interessado_id',
        'interessado_id',
        'token',
        'nota_nps',
        'nota_atendimento',
        'nota_infraestrutura',
        'nota_proposta_pedagogica',
        'comentario',
        'respondido_em',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'nota_nps' => 'integer',
            'nota_atendimento' => 'integer',
            'nota_infraestrutura' => 'integer',
            'nota_proposta_pedagogica' => 'integer',
            'respondido_em' => 'datetime',
        ];
    }

    public function visita(): BelongsTo
    {
        return $this->belongsTo(VisitaInteressado::class, 'visita_interessado_id');
    }

    public function interessado(): BelongsTo
    {
        return $this->belongsTo(Interessado::class);
    }

    public function isRespondida(): bool
    {
        return $this->respondido_em !== null;
    }

    public function classificacaoNps(): ?string
    {
        if ($this->nota_nps === null) {
            return null;
        }

        if ($this->nota_nps >= 9) {
            return 'Promotor';
        }

        if ($this->nota_nps >= 7) {
            return 'Neutro';
        }

        return 'Detrator';
    }

    public function corNps(): string
    {
        if ($this->nota_nps === null) {
            return 'gray';
        }

        return match ($this->classificacaoNps()) {
            'Promotor' => 'success',
            'Neutro' => 'warning',
            'Detrator' => 'danger',
            default => 'gray',
        };
    }

    public function iconeNps(): string
    {
        return match ($this->classificacaoNps()) {
            'Promotor' => 'heroicon-m-hand-thumb-up',
            'Neutro' => 'heroicon-m-minus-circle',
            'Detrator' => 'heroicon-m-hand-thumb-down',
            default => 'heroicon-m-question-mark-circle',
        };
    }

    public function urlPublica(): string
    {
        return route('pesquisa-visita.show', ['token' => $this->token]);
    }

    public function getUrlPublicaAttribute(): string
    {
        return $this->urlPublica();
    }

    public function corBadge(): string
    {
        return $this->corNps();
    }

    public function iconeBadge(): string
    {
        return $this->iconeNps();
    }

    /**
     * Gera o link direto do WhatsApp (api.whatsapp.com) para envio à família.
     */
    public function linkWhatsapp(): ?string
    {
        $pessoa = $this->interessado?->pessoa;
        $telefone = $pessoa?->telefone;

        if (blank($telefone)) {
            return null;
        }

        $numeroLimpo = preg_replace('/\D/', '', $telefone);
        if (strlen($numeroLimpo) >= 10 && strlen($numeroLimpo) <= 11) {
            $numeroLimpo = '55'.$numeroLimpo;
        }

        $texto = $this->gerarMensagemWhatsapp();

        return 'https://api.whatsapp.com/send?phone='.$numeroLimpo.'&text='.rawurlencode($texto);
    }

    /**
     * Monta a mensagem para envio no WhatsApp com link da pesquisa.
     * Busca preferencialmente o template ativo cadastrado em MensagemWhatsappTemplate.
     */
    public function gerarMensagemWhatsapp(): string
    {
        $nomeResponsavel = $this->interessado?->pessoa?->nome ?? 'Família';
        $primeiroNome = explode(' ', trim($nomeResponsavel))[0];
        $escola = Unidade::first()?->nome ?? InstituicaoEnsino::first()?->nome ?? 'nossa escola';
        $url = $this->urlPublica();

        $aluno = $this->visita?->dependente?->nome_crianca
            ?? $this->interessado?->dependentes?->first()?->nome_crianca
            ?? 'seu filho(a)';

        $dataHoraVisita = $this->visita?->data_hora ? $this->visita->data_hora->format('d/m/Y \à\s H:i\h') : 'recente';

        $template = MensagemWhatsappTemplate::ativos()
            ->where(function ($query) {
                $query->where('nome', 'Pesquisa de Satisfação Pós-Visita')
                    ->orWhere('nome', 'like', '%Pesquisa%Visita%')
                    ->orWhere('nome', 'like', '%Pós-Visita%');
            })
            ->first();

        if ($template && filled($template->conteudo)) {
            return strtr($template->conteudo, [
                '[Nome do Responsável]' => $nomeResponsavel,
                '[Primeiro Nome]' => $primeiroNome,
                '[Nome do Aluno]' => $aluno,
                '[Horário de Visita Agendada]' => $dataHoraVisita,
                '[Data da Visita]' => $dataHoraVisita,
                '[Link da Pesquisa da Visita]' => $url,
                '[Link da Pesquisa]' => $url,
                '[Link]' => $url,
                '[Nome da Escola]' => $escola,
                '[Escola]' => $escola,
            ]);
        }

        return "Olá, {$primeiroNome}! 😊 Ficamos muito felizes com a sua visita ao {$escola}.\n\nPara continuarmos melhorando nosso acolhimento, gostaríamos muito de saber como foi sua experiência! Você poderia nos avaliar rapidinho? Leva menos de 1 minuto:\n\n👉 {$url}\n\nAgradecemos muito pelo seu carinho e tempo!";
    }

    /**
     * Gera um token seguro para a pesquisa.
     */
    public static function gerarToken(): string
    {
        return Str::random(40);
    }
}
