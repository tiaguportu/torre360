<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

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
        'max_respostas_por_usuario',
    ];

    protected $casts = [
        'inicio_aplicacao' => 'datetime',
        'fim_aplicacao' => 'datetime',
        'is_anonimo' => 'boolean',
        'is_ativo' => 'boolean',
        'max_respostas_por_usuario' => 'integer',
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
        // Se for super_admin ou dono do questionário, sempre pode responder/testar
        if ($user && ($user->hasRole('super_admin') || $this->ehDono($user))) {
            return true;
        }

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

    public function responsaveis(): HasMany
    {
        return $this->hasMany(QuestionarioResponsavel::class);
    }

    public function donos(): HasMany
    {
        return $this->responsaveis()->where('nivel', 'dono');
    }

    public function observadores(): HasMany
    {
        return $this->responsaveis()->where('nivel', 'observador');
    }

    public function ehDono(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $this->donos()
            ->where(function ($query) use ($user) {
                $query->where(function ($q) use ($user) {
                    $q->where('responsavel_type', 'User')->where('responsavel_id', $user->id);
                })->orWhere(function ($q) use ($user) {
                    $q->where('responsavel_type', 'Role')->whereIn('responsavel_id', $user->roles->pluck('id'));
                });
            })->exists();
    }

    public function ehObservador(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $this->observadores()
            ->where(function ($query) use ($user) {
                $query->where(function ($q) use ($user) {
                    $q->where('responsavel_type', 'User')->where('responsavel_id', $user->id);
                })->orWhere(function ($q) use ($user) {
                    $q->where('responsavel_type', 'Role')->whereIn('responsavel_id', $user->roles->pluck('id'));
                });
            })->exists();
    }

    /**
     * Clona o questionário com todos os seus blocos, perguntas, alvos e responsáveis.
     * As respostas não são clonadas.
     */
    public function clonar(): self
    {
        return DB::transaction(function () {
            // 1. Clonar o questionário principal
            // Removemos atributos virtuais (como respostas_count) que o Filament pode ter injetado na model
            $novoQuestionario = $this->replicate(['respostas_count']);
            $novoQuestionario->titulo = $this->titulo.' (Cópia)';
            $novoQuestionario->save();

            // 2. Clonar alvos
            foreach ($this->alvos as $alvo) {
                $novoAlvo = $alvo->replicate();
                $novoAlvo->questionario_id = $novoQuestionario->id;
                $novoAlvo->save();
            }

            // 3. Clonar responsáveis
            foreach ($this->responsaveis as $responsavel) {
                $novoResponsavel = $responsavel->replicate();
                $novoResponsavel->questionario_id = $novoQuestionario->id;
                $novoResponsavel->save();
            }

            // 4. Clonar blocos e perguntas
            $mapaPerguntas = []; // [ID antigo => ID novo]

            foreach ($this->blocos as $bloco) {
                $novoBloco = $bloco->replicate();
                $novoBloco->questionario_id = $novoQuestionario->id;
                $novoBloco->save();

                foreach ($bloco->perguntas as $pergunta) {
                    $novaPergunta = $pergunta->replicate();
                    $novaPergunta->questionario_bloco_id = $novoBloco->id;
                    $novaPergunta->save();

                    $mapaPerguntas[$pergunta->id] = $novaPergunta->id;
                }
            }

            // 5. Atualizar condições de exibição das novas perguntas
            foreach ($novoQuestionario->blocos as $novoBloco) {
                foreach ($novoBloco->perguntas as $novaPergunta) {
                    if (! empty($novaPergunta->condicao_exibicao)) {
                        $condicao = $novaPergunta->condicao_exibicao;
                        $perguntaReferenciadaId = $condicao['pergunta_id'] ?? null;

                        if ($perguntaReferenciadaId && isset($mapaPerguntas[$perguntaReferenciadaId])) {
                            $condicao['pergunta_id'] = $mapaPerguntas[$perguntaReferenciadaId];
                            $novaPergunta->condicao_exibicao = $condicao;
                            $novaPergunta->save();
                        }
                    }
                }
            }

            return $novoQuestionario;
        });
    }
}
