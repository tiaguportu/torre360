<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusAutoavaliacaoDocente;
use App\Support\FuncionarioDoUsuario;
use Database\Factories\AutoavaliacaoDocenteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Autoavaliação do professor num ciclo: ele dá nota aos mesmos critérios do instrumento que a coordenação usa. É
 * privada enquanto está em preenchimento; depois de enviada, a coordenação a compara com as observações. Dado
 * sensível: o comentário e as observações por critério são cifrados.
 */
class AutoavaliacaoDocente extends Model
{
    /** @use HasFactory<AutoavaliacaoDocenteFactory> */
    use HasFactory;

    use LogsActivity;

    protected $table = 'autoavaliacoes_docente';

    protected $fillable = [
        'ciclo_id',
        'funcionario_id',
        'status',
        'comentario',
        'pontuacao_percentual',
        'enviada_em',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusAutoavaliacaoDocente::class,
            'comentario' => 'encrypted',
            'pontuacao_percentual' => 'decimal:2',
            'enviada_em' => 'datetime',
        ];
    }

    /**
     * O log registra o fluxo, nunca o comentário nem as notas.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['ciclo_id', 'funcionario_id', 'status', 'pontuacao_percentual'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('autoavaliacao_docente')
            ->setDescriptionForEvent(fn (string $evento): string => match ($evento) {
                'created' => 'Autoavaliação docente iniciada.',
                'updated' => 'Autoavaliação docente atualizada.',
                default => "Autoavaliação docente: {$evento}",
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

    public function notas(): HasMany
    {
        return $this->hasMany(AutoavaliacaoNota::class, 'autoavaliacao_id')->orderBy('id');
    }

    public function estaEnviada(): bool
    {
        return $this->status === StatusAutoavaliacaoDocente::Enviada;
    }

    public function pertenceA(?User $user): bool
    {
        return $user !== null && $this->funcionario !== null && FuncionarioDoUsuario::ehOProprio($user, $this->funcionario);
    }

    /**
     * Quem atua como professor só enxerga a própria autoavaliação (em qualquer situação). Coordenação e
     * administração enxergam só as ENVIADAS: o rascunho é privado de quem escreve.
     */
    public function scopeVisiveisPara(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $pessoas = $user->pessoas()->pluck('pessoa.id');

        return $query->where(function (Builder $q) use ($user, $pessoas): void {
            $q->whereHas('funcionario', fn (Builder $f) => $f->whereIn('pessoa_id', $pessoas));

            if (! $user->atuaComoProfessor()) {
                $q->orWhere('status', StatusAutoavaliacaoDocente::Enviada->value);
            }
        });
    }
}
