<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimentacaoPatrimonio extends Model
{
    use HasFactory;

    protected $table = 'movimentacoes_patrimonio';

    protected $fillable = [
        'bem_patrimonial_id',
        'tipo',
        'unidade_anterior_id',
        'unidade_nova_id',
        'sala_anterior_id',
        'sala_nova_id',
        'status_anterior',
        'status_novo',
        'data',
        'observacao',
        'registrado_por_user_id',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
        ];
    }

    public function bemPatrimonial(): BelongsTo
    {
        return $this->belongsTo(BemPatrimonial::class);
    }

    public function unidadeAnterior(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'unidade_anterior_id');
    }

    public function unidadeNova(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'unidade_nova_id');
    }

    public function salaAnterior(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_anterior_id');
    }

    public function salaNova(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_nova_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }
}
