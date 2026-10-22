<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoDisponibilidadeProfessor;
use App\Services\DisponibilidadeProfessorService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma janela semanal em que o professor pode (ou não) dar aula. Regra de leitura em
 * {@see DisponibilidadeProfessorService}: sem nenhuma janela, o professor está livre; com janelas
 * "disponível", só vale dentro delas; "indisponível" sempre bloqueia.
 */
class DisponibilidadeProfessor extends Model
{
    protected $table = 'disponibilidades_professor';

    protected $fillable = ['pessoa_id', 'dia_semana', 'hora_inicio', 'hora_fim', 'tipo', 'observacao'];

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
            'tipo' => TipoDisponibilidadeProfessor::class,
        ];
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function inicio(): string
    {
        return substr((string) $this->hora_inicio, 0, 5);
    }

    public function fim(): string
    {
        return substr((string) $this->hora_fim, 0, 5);
    }
}
