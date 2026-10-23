<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MeioContatoFamilia;
use App\Enums\ResultadoContatoFamilia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma tentativa de contato com a família sobre as faltas (Onda 57): a prova de que os recursos escolares foram esgotados
 * antes de comunicar o Conselho Tutelar.
 */
class ContatoCasoFrequencia extends Model
{
    protected $table = 'contatos_caso_frequencia';

    protected $fillable = ['caso_frequencia_id', 'data', 'meio', 'resultado', 'observacao', 'registrado_por_user_id'];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'meio' => MeioContatoFamilia::class,
            'resultado' => ResultadoContatoFamilia::class,
            // O que a família disse sobre o motivo das faltas.
            'observacao' => 'encrypted',
        ];
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(CasoFrequencia::class, 'caso_frequencia_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }
}
