<?php

namespace App\Models;

use App\Enums\HumorCrianca;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistroRotinaDiaria extends Model
{
    use HasFactory;

    protected $fillable = [
        'matricula_id',
        'turma_id',
        'data',
        'humor',
        'hora_inicio_soneca',
        'hora_fim_soneca',
        'higiene_observacoes',
        'atividades_dia',
        'foto_path',
        'registrado_por_user_id',
    ];

    protected function casts(): array
    {
        return [
            'humor' => HumorCrianca::class,
            'data' => 'date:Y-m-d',
        ];
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'matricula_id');
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'turma_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function refeicoes(): HasMany
    {
        return $this->hasMany(RefeicaoRotina::class)->orderBy('ordem');
    }

    public function temSoneca(): bool
    {
        return $this->hora_inicio_soneca !== null;
    }
}
