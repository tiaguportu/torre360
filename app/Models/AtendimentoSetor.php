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
        'prazo_primeira_resposta_horas',
        'prazo_resolucao_horas',
        'fecha_aguardando_dias',
        'recebe_requerimentos',
        'ativo',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'prazo_primeira_resposta_horas' => 'integer',
            'prazo_resolucao_horas' => 'integer',
            'fecha_aguardando_dias' => 'integer',
            'recebe_requerimentos' => 'boolean',
            'ativo' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    public function chamados(): HasMany
    {
        return $this->hasMany(AtendimentoChamado::class, 'setor_id');
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(AtendimentoCategoria::class, 'setor_id')->orderBy('ordem')->orderBy('nome');
    }

    public function respostasProntas(): HasMany
    {
        return $this->hasMany(AtendimentoRespostaPronta::class, 'setor_id');
    }
}
