<?php

namespace App\Models;

use App\Enums\StatusConsentimento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsentimentoMatricula extends Model
{
    use HasFactory;

    protected $fillable = [
        'matricula_id',
        'tipo_consentimento_id',
        'status',
        'respondido_em',
        'respondido_por_user_id',
        'ip_resposta',
        'vigencia_inicio',
        'vigencia_fim',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusConsentimento::class,
            'respondido_em' => 'datetime',
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
        ];
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'matricula_id');
    }

    public function tipoConsentimento(): BelongsTo
    {
        return $this->belongsTo(TipoConsentimento::class, 'tipo_consentimento_id');
    }

    public function respondidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondido_por_user_id');
    }

    /**
     * Registra a resposta da família (ou da secretaria em nome dela), calculando a
     * vigência quando o tipo exige renovação periódica.
     */
    public function responder(StatusConsentimento $status, ?User $usuario = null, ?string $ip = null): void
    {
        $inicio = now();
        $fim = null;

        if ($status === StatusConsentimento::Autorizado && $this->tipoConsentimento->exige_renovacao_periodica) {
            $fim = $inicio->copy()->addMonths($this->tipoConsentimento->periodicidade_meses ?? 12);
        }

        $this->update([
            'status' => $status,
            'respondido_em' => $inicio,
            'respondido_por_user_id' => $usuario?->id,
            'ip_resposta' => $ip,
            'vigencia_inicio' => $status === StatusConsentimento::Autorizado ? $inicio->toDateString() : null,
            'vigencia_fim' => $fim?->toDateString(),
        ]);
    }

    /**
     * Status efetivo considerando a vigência: um consentimento autorizado cuja vigência
     * já venceu volta a exigir resposta, sem precisar de um job/observer para "rebaixar"
     * o registro — é só uma leitura derivada.
     */
    public function statusEfetivo(): StatusConsentimento
    {
        if ($this->status === StatusConsentimento::Autorizado
            && $this->vigencia_fim !== null
            && $this->vigencia_fim->isPast()) {
            return StatusConsentimento::Pendente;
        }

        return $this->status;
    }

    public function precisaResposta(): bool
    {
        return $this->statusEfetivo() === StatusConsentimento::Pendente;
    }

    /**
     * Cria (quando ainda não existir) um registro Pendente para a matrícula e tipo dados —
     * usado pelo Portal para sempre oferecer resposta a todo tipo de consentimento ativo,
     * mesmo sem um job prévio populando a tabela.
     */
    public static function localizarOuPendente(Matricula $matricula, TipoConsentimento $tipo): self
    {
        return static::firstOrCreate(
            ['matricula_id' => $matricula->id, 'tipo_consentimento_id' => $tipo->id],
            ['status' => StatusConsentimento::Pendente]
        );
    }
}
