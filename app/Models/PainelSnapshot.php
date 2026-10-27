<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fotografia diária do valor de um indicador do Painel da Direção (Onda 45).
 */
class PainelSnapshot extends Model
{
    protected $table = 'painel_snapshots';

    protected $fillable = ['data', 'indicador', 'unidade_id', 'valor'];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'unidade_id' => 'integer',
            'valor' => 'float',
        ];
    }
}
