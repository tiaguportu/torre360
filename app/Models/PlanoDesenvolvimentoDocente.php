<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusPlanoDesenvolvimento;
use App\Support\FuncionarioDoUsuario;
use Database\Factories\PlanoDesenvolvimentoDocenteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Plano de desenvolvimento individual (PDI) de um professor, criado pela coordenação a partir de uma avaliação
 * finalizada: ações com prazo para trabalhar os critérios mais fracos. O professor só o enxerga depois de ativado,
 * dá ciência e registra o andamento das ações. Dado sensível: objetivo, descrições e resultados são cifrados.
 */
class PlanoDesenvolvimentoDocente extends Model
{
    /** @use HasFactory<PlanoDesenvolvimentoDocenteFactory> */
    use HasFactory;

    use LogsActivity;

    protected $table = 'planos_desenvolvimento_docente';

    protected $fillable = [
        'avaliacao_docente_id',
        'funcionario_id',
        'status',
        'objetivo',
        'data_revisao',
        'criado_por_user_id',
        'ativado_em',
        'ciencia_em',
        'concluido_em',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusPlanoDesenvolvimento::class,
            'objetivo' => 'encrypted',
            'data_revisao' => 'date',
            'ativado_em' => 'datetime',
            'ciencia_em' => 'datetime',
            'concluido_em' => 'datetime',
        ];
    }

    /**
     * O log registra o fluxo, nunca o conteúdo do plano.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['avaliacao_docente_id', 'funcionario_id', 'status', 'data_revisao', 'ciencia_em'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('plano_desenvolvimento_docente')
            ->setDescriptionForEvent(fn (string $evento): string => match ($evento) {
                'created' => 'Plano de desenvolvimento docente criado.',
                'updated' => 'Plano de desenvolvimento docente atualizado.',
                'deleted' => 'Plano de desenvolvimento docente excluído.',
                default => "Plano de desenvolvimento docente: {$evento}",
            });
    }

    public function avaliacao(): BelongsTo
    {
        return $this->belongsTo(AvaliacaoDocente::class, 'avaliacao_docente_id');
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }

    public function acoes(): HasMany
    {
        return $this->hasMany(AcaoDesenvolvimentoDocente::class, 'plano_id')->orderBy('ordem')->orderBy('id');
    }

    public function pertenceA(?User $user): bool
    {
        return $user !== null && $this->funcionario !== null && FuncionarioDoUsuario::ehOProprio($user, $this->funcionario);
    }

    /**
     * Quem atua como professor só enxerga o PRÓPRIO plano e só depois de ativado; coordenação e administração
     * enxergam todos.
     */
    public function scopeVisiveisPara(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if (! $user->atuaComoProfessor()) {
            return $query;
        }

        return $query
            ->whereIn('status', [StatusPlanoDesenvolvimento::Ativo->value, StatusPlanoDesenvolvimento::Concluido->value])
            ->whereHas('funcionario', fn (Builder $f) => $f->whereIn('pessoa_id', $user->pessoas()->pluck('pessoa.id')));
    }
}
