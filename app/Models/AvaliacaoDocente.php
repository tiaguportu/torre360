<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusAvaliacaoDocente;
use Database\Factories\AvaliacaoDocenteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Uma observação da coordenação sobre um professor dentro de um ciclo: nota por critério do instrumento, pontuação
 * consolidada e parecer. Dado sensível de funcionário: o parecer é cifrado e a visibilidade é restrita (ver
 * `AvaliacaoDocentePolicy`). Depois de finalizada, não se altera mais.
 */
class AvaliacaoDocente extends Model
{
    /** @use HasFactory<AvaliacaoDocenteFactory> */
    use HasFactory;

    use LogsActivity;

    protected $table = 'avaliacoes_docente';

    protected $fillable = [
        'ciclo_id',
        'funcionario_id',
        'avaliador_user_id',
        'turma_id',
        'data_observacao',
        'pontuacao_percentual',
        'parecer',
        'status',
        'finalizada_em',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_observacao' => 'date',
            'pontuacao_percentual' => 'decimal:2',
            'parecer' => 'encrypted',
            'status' => StatusAvaliacaoDocente::class,
            'finalizada_em' => 'datetime',
        ];
    }

    /**
     * O log registra só o que mudou de estado, nunca o parecer nem as notas (dado sensível).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['ciclo_id', 'funcionario_id', 'avaliador_user_id', 'data_observacao', 'status', 'pontuacao_percentual'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('avaliacao_docente')
            ->setDescriptionForEvent(fn (string $evento): string => match ($evento) {
                'created' => 'Avaliação docente iniciada.',
                'updated' => 'Avaliação docente atualizada.',
                'deleted' => 'Avaliação docente excluída.',
                default => "Avaliação docente: {$evento}",
            });
    }

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(CicloAvaliacaoDocente::class, 'ciclo_id');
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function avaliador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'avaliador_user_id');
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function notas(): HasMany
    {
        return $this->hasMany(AvaliacaoDocenteCriterio::class, 'avaliacao_docente_id')->orderBy('id');
    }

    public function estaFinalizada(): bool
    {
        return $this->status === StatusAvaliacaoDocente::Finalizada;
    }

    /**
     * Avaliações que o usuário pode enxergar. Quem atua como professor só vê as FINALIZADAS em que ele mesmo é o
     * avaliado (nunca rascunhos, nunca as de colegas); coordenação e administração veem todas.
     */
    public function scopeVisiveisPara(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if (! $user->atuaComoProfessor()) {
            return $query;
        }

        $pessoasIds = $user->pessoas()->pluck('pessoa.id');

        return $query
            ->where('status', StatusAvaliacaoDocente::Finalizada->value)
            ->whereHas('funcionario', fn (Builder $q) => $q->whereIn('pessoa_id', $pessoasIds));
    }

    public function visivelPara(User $user): bool
    {
        return static::query()->whereKey($this->getKey())->visiveisPara($user)->exists();
    }
}
