<?php

namespace App\Models;

use App\Enums\QuantidadeRefeicao;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefeicaoRotina extends Model
{
    use HasFactory;

    protected $fillable = [
        'registro_rotina_diaria_id',
        'nome',
        'quantidade',
        'observacao',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => QuantidadeRefeicao::class,
        ];
    }

    public function registroRotinaDiaria(): BelongsTo
    {
        return $this->belongsTo(RegistroRotinaDiaria::class);
    }
}
