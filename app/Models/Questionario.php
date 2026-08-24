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
        'ultimo_envio_aviso',
    ];

    protected function casts(): array
    {
        return [
            'inicio_aplicacao' => 'datetime',
            'fim_aplicacao' => 'datetime',
            'is_anonimo' => 'boolean',
            'is_ativo' => 'boolean',
            'max_respostas_por_usuario' => 'integer',
            'ultimo_envio_aviso' => 'datetime',
        ];
    }

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
        $totalAlvos = $this->relationLoaded('alvos') ? $this->alvos->count() : $this->alvos()->count();
        if ($totalAlvos === 0) {
            return $user !== null || $this->is_anonimo;
        }

        if (! $user) {
            if ($this->relationLoaded('alvos')) {
                return $this->is_anonimo && $this->alvos->where('alvo_type', 'User')->isEmpty();
            }

            return $this->is_anonimo && $this->alvos()->where('alvo_type', 'User')->exists() === false;
        }

        // Verifica matches nos alvos
        $alvos = $this->relationLoaded('alvos') ? $this->alvos : $this->alvos()->get();
        foreach ($alvos as $alvo) {
            if ($alvo->alvo_type === 'User' && $alvo->alvo_id == $user->id) {
                return true;
            }

            if ($alvo->alvo_type === 'Role' && $user->hasRole($alvo->alvo_id)) {
                return true;
            }
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

        if ($this->relationLoaded('responsaveis')) {
            return $this->responsaveis
                ->where('nivel', 'dono')
                ->contains(function ($responsavel) use ($user) {
                    if ($responsavel->responsavel_type === 'User') {
                        return $responsavel->responsavel_id == $user->id;
                    }
                    if ($responsavel->responsavel_type === 'Role') {
                        return $user->roles->pluck('id')->contains($responsavel->responsavel_id);
                    }

                    return false;
                });
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

        if ($this->relationLoaded('responsaveis')) {
            return $this->responsaveis
                ->where('nivel', 'observador')
                ->contains(function ($responsavel) use ($user) {
                    if ($responsavel->responsavel_type === 'User') {
                        return $responsavel->responsavel_id == $user->id;
                    }
                    if ($responsavel->responsavel_type === 'Role') {
                        return $user->roles->pluck('id')->contains($responsavel->responsavel_id);
                    }

                    return false;
                });
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
     * Obtém a lista de e-mails dos possíveis respondedores do questionário.
     *
     * @return array<string>
     */
    public function obterEmailsRespondedores(): array
    {
        $query = User::query()
            ->whereNotNull('activated_at')
            ->where('activated_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('deactivated_at')
                    ->orWhere('deactivated_at', '>', now());
            });

        $alvos = $this->alvos;

        if ($alvos->isNotEmpty()) {
            $query->where(function ($q) use ($alvos) {
                foreach ($alvos as $index => $alvo) {
                    $clause = $index === 0 ? 'where' : 'orWhere';

                    if ($alvo->alvo_type === 'User') {
                        $q->{$clause}('id', $alvo->alvo_id);
                    } elseif ($alvo->alvo_type === 'Role') {
                        $q->{$clause.'Has'}('roles', fn ($sq) => $sq->where('id', $alvo->alvo_id));
                    } elseif ($alvo->alvo_type === 'Turma') {
                        $q->{$clause.'Has'}('pessoas.matriculas', fn ($sq) => $sq->where('turma_id', $alvo->alvo_id));
                    } elseif ($alvo->alvo_type === 'Serie') {
                        $q->{$clause.'Has'}('pessoas.matriculas.turma', fn ($sq) => $sq->where('serie_id', $alvo->alvo_id));
                    } elseif ($alvo->alvo_type === 'Curso') {
                        $q->{$clause.'Has'}('pessoas.matriculas.turma.serie', fn ($sq) => $sq->where('curso_id', $alvo->alvo_id));
                    } elseif ($alvo->alvo_type === 'Unidade') {
                        $q->{$clause.'Has'}('pessoas.matriculas.turma.serie.curso', fn ($sq) => $sq->where('unidade_id', $alvo->alvo_id));
                    }
                }
            });
        }

        // Se houver limite de respostas por usuário, exclui os que já atingiram o limite
        if ($this->max_respostas_por_usuario !== null && ! $this->is_anonimo) {
            $respostasPorUsuario = QuestionarioResposta::where('questionario_id', $this->id)
                ->where('status', 'enviado')
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->select('user_id', DB::raw('count(*) as total'))
                ->pluck('total', 'user_id');

            $excluirUserIds = $respostasPorUsuario
                ->filter(fn ($total) => $total >= $this->max_respostas_por_usuario)
                ->keys()
                ->toArray();

            if (! empty($excluirUserIds)) {
                $query->whereNotIn('id', $excluirUserIds);
            }
        }

        return $query->pluck('email')->filter()->unique()->toArray();
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
