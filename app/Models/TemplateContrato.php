<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateContrato extends Model
{
    protected $table = 'template_contratos';

    protected $fillable = [
        'nome',
        'versao',
        'conteudo',
        'arquivo_odt',
        'is_padrao',
        'cabecalho',
        'rodape',
    ];

    protected function casts(): array
    {
        return [
            'is_padrao' => 'boolean',
            'versao' => 'integer',
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

            // Se for versão 2 (ODT), garantir que conteudo não seja nulo para evitar erro no banco
            if ($template->versao == 2 && is_null($template->conteudo)) {
                $template->conteudo = '';
            }
        });
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    public function clonar(): self
    {
        $clone = $this->replicate();
        $clone->nome = $this->nome.' (Cópia)';
        $clone->is_padrao = false;
        $clone->save();

        return $clone;
    }
}
