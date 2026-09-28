<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AtendimentoSetor extends Model
{
    use HasFactory;

    protected $table = 'atendimento_setores';

    protected $fillable = [
        'nome',
        'descricao',
        'email_notificacao',
        'ativo',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    public function chamados(): HasMany
    {
        return $this->hasMany(AtendimentoChamado::class, 'setor_id');
    }
}
