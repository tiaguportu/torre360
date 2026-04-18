<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Questionario extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'titulo',
        'descricao',
        'inicio_aplicacao',
        'fim_aplicacao',
        'is_anonimo',
        'is_ativo',
    ];

    protected $casts = [
        'inicio_aplicacao' => 'datetime',
        'fim_aplicacao' => 'datetime',
        'is_anonimo' => 'boolean',
        'is_ativo' => 'boolean',
    ];

    /**
     * Scope para filtrar questionários visíveis para um usuário específico.
     */
    public function scopeVisivelPara($query, ?User $user = null)
    {
        $query->where('is_ativo', true)
            ->where(function ($q) {
                $q->whereNull('inicio_aplicacao')->orWhere('inicio_aplicacao', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('fim_aplicacao')->orWhere('fim_aplicacao', '>=', now());
            });

        if (! $user) {
            return $query->where('is_anonimo', true)->whereDoesntHave('alvos');
        }

        return $query->where(function ($q) use ($user) {
            // Se não houver alvos definidos, o questionário é visível para todos os usuários logados
            $q->whereDoesntHave('alvos')
                ->orWhereHas('alvos', function ($sq) use ($user) {
                    $sq->where(function ($ssq) use ($user) {
                        $ssq->where('alvo_type', 'User')
                            ->where('alvo_id', $user->id);
                    })->orWhere(function ($ssq) use ($user) {
                        $ssq->where('alvo_type', 'Role')
                            ->whereIn('alvo_id', $user->roles->pluck('id'));
                    });

                    // Caso o usuário tenha uma pessoa vinculada, checamos vínculos acadêmicos
                    if ($user->pessoa) {
                        $pessoaId = $user->pessoa_id; // Supondo que o User tem pessoa_id ou relação

                        // Nota: A relação User->Pessoa pode ser complexa.
                        // Verificando se existe o campo pessoa_id no User.
                    }
                });
        });
    }

    /**
     * Verifica se o questionário pode ser respondido por um usuário.
     */
    public function podeSerRespondidoPor(?User $user): bool
    {
        if (! $this->is_ativo) {
            return false;
        }

        $hoje = now();
        if ($this->inicio_aplicacao && $hoje->lt($this->inicio_aplicacao)) {
            return false;
        }
        if ($this->fim_aplicacao && $hoje->gt($this->fim_aplicacao)) {
            return false;
        }

        // Se não houver alvos, qualquer usuário logado pode responder
        if ($this->alvos()->count() === 0) {
            return $user !== null || $this->is_anonimo;
        }

        if (! $user) {
            return $this->is_anonimo && $this->alvos()->where('alvo_type', 'User')->exists() === false;
        }

        // Verifica matches nos alvos
        foreach ($this->alvos as $alvo) {
            if ($alvo->alvo_type === 'User' && $alvo->alvo_id == $user->id) {
                return true;
            }

            if ($alvo->alvo_type === 'Role' && $user->hasRole($alvo->alvo_id)) {
                return true;
            }

            // Implementação futura para Unidade, Curso, Serie, Turma se houver pessoa vinculada
        }

        return false;
    }

    public function blocos(): HasMany
    {
        return $this->hasMany(QuestionarioBloco::class)->orderBy('ordem');
    }

    public function alvos(): HasMany
    {
        return $this->hasMany(QuestionarioAlvo::class);
    }

    public function respostas(): HasMany
    {
        return $this->hasMany(QuestionarioResposta::class);
    }
}
