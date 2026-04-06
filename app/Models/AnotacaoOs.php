<?php

namespace App\Models;

use Database\Factories\AnotacaoOsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnotacaoOs extends Model
{
    /** @use HasFactory<AnotacaoOsFactory> */
    use HasFactory;

    protected $fillable = [
        'ordem_servico_id',
        'user_id',
        'texto',
        'fotos',
    ];

    protected function casts(): array
    {
        return [
            'fotos' => 'array',
        ];
    }

    public function ordemServico()
    {
        return $this->belongsTo(OrdemServico::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
