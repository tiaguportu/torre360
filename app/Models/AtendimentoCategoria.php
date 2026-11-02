<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Assunto de um setor da Central de Atendimento ("segunda via de boleto", "horário"), que alimenta o relatório (Onda 83).
 */
class AtendimentoCategoria extends Model
{
    protected $table = 'atendimento_categorias';

    protected $fillable = ['setor_id', 'nome', 'ativo', 'ordem'];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(AtendimentoSetor::class, 'setor_id');
    }

    public function chamados(): HasMany
    {
        return $this->hasMany(AtendimentoChamado::class, 'categoria_id');
    }
}
