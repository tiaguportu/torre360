<?php

namespace App\Models;

use App\Enums\TipoTemplateDocumento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateDocumento extends Model
{
    use HasFactory;

    protected $table = 'template_documentos';

    protected $guarded = [];

    public function solicitacoes(): HasMany
    {
        return $this->hasMany(SolicitacaoDocumento::class);
    }

    protected function casts(): array
    {
        return [
            'tipo' => TipoTemplateDocumento::class,
            'is_ativo' => 'boolean',
            'validade_dias' => 'integer',
        ];
    }
}
