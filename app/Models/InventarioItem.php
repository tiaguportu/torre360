<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioItem extends Model
{
    use HasFactory;

    protected $table = 'inventario_itens';

    protected $fillable = [
        'inventario_id',
        'livro_id',
        'bipado_em',
        'quantidade_conferida',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'bipado_em' => 'datetime',
            'quantidade_conferida' => 'integer',
        ];
    }

    public function inventario(): BelongsTo
    {
        return $this->belongsTo(InventarioAcervo::class, 'inventario_id');
    }

    public function livro(): BelongsTo
    {
        return $this->belongsTo(Livro::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
