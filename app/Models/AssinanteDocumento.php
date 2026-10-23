<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusAssinante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quem foi chamado para assinar um {@see DocumentoAssinatura}, com a situação de cada um. O endereço de assinatura
 * carrega o token do assinante e por isso é guardado criptografado.
 */
class AssinanteDocumento extends Model
{
    protected $table = 'assinantes_documento';

    protected $fillable = [
        'documento_assinatura_id',
        'nome',
        'email',
        'papel',
        'ordem',
        'status',
        'assinado_em',
        'assinafy_signer_id',
        'link_assinatura',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusAssinante::class,
            'assinado_em' => 'datetime',
            'link_assinatura' => 'encrypted',
        ];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoAssinatura::class, 'documento_assinatura_id');
    }
}
