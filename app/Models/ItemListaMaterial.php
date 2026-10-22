<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoriaItemMaterial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemListaMaterial extends Model
{
    protected $table = 'itens_lista_material';

    protected $fillable = ['lista_material_id', 'descricao', 'quantidade', 'unidade', 'categoria', 'observacao', 'uso_individual_confirmado', 'ordem'];

    protected function casts(): array
    {
        return [
            'categoria' => CategoriaItemMaterial::class,
            'quantidade' => 'integer',
            'uso_individual_confirmado' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    public function lista(): BelongsTo
    {
        return $this->belongsTo(ListaMaterialEscolar::class, 'lista_material_id');
    }
}
