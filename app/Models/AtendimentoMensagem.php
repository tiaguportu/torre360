<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtendimentoMensagem extends Model
{
    use HasFactory;

    protected $table = 'atendimento_mensagens';

    protected $fillable = [
        'chamado_id',
        'user_id',
        'pessoa_id',
        'mensagem',
        'anexo_path',
        'lida_em',
    ];

    protected function casts(): array
    {
        return [
            'lida_em' => 'datetime',
        ];
    }

    public function chamado(): BelongsTo
    {
        return $this->belongsTo(AtendimentoChamado::class, 'chamado_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    public function getNomeRemetenteAttribute(): string
    {
        if ($this->user) {
            return $this->user->name.' (Equipe Escolar)';
        }

        if ($this->pessoa) {
            return $this->pessoa->nome.' (Família)';
        }

        return 'Sistema';
    }
}
