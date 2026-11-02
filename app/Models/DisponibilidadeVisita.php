<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Horário em que um consultor recebe visitas (Onda 86): dia da semana, faixa, duração de cada visita e antecedência. Sem unidade,
 * vale para qualquer unidade.
 */
class DisponibilidadeVisita extends Model
{
    protected $table = 'disponibilidade_visitas';

    protected $fillable = [
        'user_id',
        'unidade_id',
        'dia_semana',
        'hora_inicio',
        'hora_fim',
        'duracao_minutos',
        'antecedencia_minima_horas',
        'antecedencia_maxima_dias',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
            'duracao_minutos' => 'integer',
            'antecedencia_minima_horas' => 'integer',
            'antecedencia_maxima_dias' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function consultor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public static function diasDaSemana(): array
    {
        return [1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado', 7 => 'Domingo'];
    }
}
