<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um tempo de aula do turno (o "sinal" da escola): 1º tempo das 7h30 às 8h20, 2º das 8h20 às 9h10... O intervalo não é
 * cadastrado: é o espaço entre dois tempos.
 */
class TempoAula extends Model
{
    protected $table = 'tempos_aula';

    protected $fillable = ['turno_id', 'ordem', 'hora_inicio', 'hora_fim'];

    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
        ];
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    /**
     * Início no formato HH:MM, para comparar horários como texto.
     */
    public function inicio(): string
    {
        return substr((string) $this->hora_inicio, 0, 5);
    }

    public function fim(): string
    {
        return substr((string) $this->hora_fim, 0, 5);
    }

    public function rotulo(): string
    {
        return "{$this->ordem}º tempo ({$this->inicio()} às {$this->fim()})";
    }
}
