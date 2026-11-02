<?php

namespace App\Models;

use App\Enums\CategoriaDenuncia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Membro do comitê da ouvidoria: lê as denúncias da categoria (ou de todas, se a categoria ficar em branco).
 */
class DenunciaComite extends Model
{
    protected $table = 'denuncia_comites';

    protected $fillable = ['user_id', 'categoria'];

    protected function casts(): array
    {
        return ['categoria' => CategoriaDenuncia::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
