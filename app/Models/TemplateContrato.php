<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateContrato extends Model
{
    protected $table = 'template_contratos';

    protected $fillable = [
        'nome',
        'conteudo',
        'is_padrao',
    ];

    protected function casts(): array
    {
        return [
            'is_padrao' => 'boolean',
        ];
    }

    /**
     * Boot do modelo para garantir a unicidade da flag is_padrao.
     */
    protected static function booted(): void
    {
        static::saving(function ($template) {
            if ($template->is_padrao) {
                // Se este for padrão, desativar todos os outros
                static::where('id', '!=', $template->id)->update(['is_padrao' => false]);
            }
        });
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }
}
