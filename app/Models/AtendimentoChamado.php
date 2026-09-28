<?php

namespace App\Models;

use App\Enums\PrioridadeChamado;
use App\Enums\StatusChamado;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AtendimentoChamado extends Model
{
    use HasFactory;

    protected $table = 'atendimento_chamados';

    protected $fillable = [
        'protocolo',
        'setor_id',
        'matricula_id',
        'solicitante_id',
        'responsavel_atendimento_id',
        'assunto',
        'prioridade',
        'status',
        'avaliacao_nota',
        'avaliacao_comentario',
        'fechado_em',
    ];

    protected function casts(): array
    {
        return [
            'prioridade' => PrioridadeChamado::class,
            'status' => StatusChamado::class,
            'avaliacao_nota' => 'integer',
            'fechado_em' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $chamado) {
            if (empty($chamado->protocolo)) {
                $ano = date('Y');
                $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
                $chamado->protocolo = "ATD-{$ano}-{$random}";
            }
        });
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(AtendimentoSetor::class, 'setor_id');
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'matricula_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'solicitante_id');
    }

    public function responsavelAtendimento(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_atendimento_id');
    }

    public function mensagens(): HasMany
    {
        return $this->hasMany(AtendimentoMensagem::class, 'chamado_id')->oldest();
    }
}
