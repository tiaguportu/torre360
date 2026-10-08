<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SacolaLeituraItem extends Model
{
    use HasFactory;

    protected $table = 'sacola_leitura_itens';

    protected $fillable = [
        'sacola_id',
        'livro_id',
        'devolvido',
        'devolvido_em',
        'observacao_devolucao',
    ];

    protected function casts(): array
    {
        return [
            'devolvido' => 'boolean',
            'devolvido_em' => 'datetime',
        ];
    }

    public function sacola(): BelongsTo
    {
        return $this->belongsTo(SacolaLeitura::class, 'sacola_id');
    }

    public function livro(): BelongsTo
    {
        return $this->belongsTo(Livro::class, 'livro_id');
    }
}
