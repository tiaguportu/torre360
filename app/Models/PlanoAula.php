<?php

namespace App\Models;

use Database\Factories\PlanoAulaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * O que o professor planeja lecionar numa data futura: objetivos, metodologia
 * e habilidades BNCC previstas. Ao ser executado, gera o registro real no
 * diário de aulas (`CronogramaAula`).
 */
class PlanoAula extends Model
{
    /** @use HasFactory<PlanoAulaFactory> */
    use HasFactory;

    protected $table = 'plano_aula';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data_prevista' => 'date',
            'anexo_material' => 'array',
            'executado_em' => 'datetime',
        ];
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'professor_id');
    }

    public function cronogramaAula(): BelongsTo
    {
        return $this->belongsTo(CronogramaAula::class);
    }

    public function habilidades(): BelongsToMany
    {
        return $this->belongsToMany(Habilidade::class, 'plano_aula_habilidade');
    }

    public function foiExecutado(): bool
    {
        return $this->executado_em !== null;
    }
}
