<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusParecerDescritivo;
use Database\Factories\ParecerDescritivoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Parecer descritivo de um aluno numa etapa avaliativa: texto do professor (opcionalmente rascunhado com IA), revisado
 * pela coordenação e publicado para a família. Só o parecer publicado aparece no Portal.
 */
class ParecerDescritivo extends Model
{
    /** @use HasFactory<ParecerDescritivoFactory> */
    use HasFactory;

    use LogsActivity;

    protected $table = 'pareceres_descritivos';

    protected $fillable = [
        'matricula_id',
        'etapa_avaliativa_id',
        'texto',
        'status',
        'gerado_com_ia',
        'autor_user_id',
        'publicado_por_user_id',
        'publicado_em',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusParecerDescritivo::class,
            'gerado_com_ia' => 'boolean',
            'publicado_em' => 'datetime',
        ];
    }

    /**
     * O log registra o fluxo (rascunho, revisão, publicação), nunca o texto do parecer.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['matricula_id', 'etapa_avaliativa_id', 'status', 'gerado_com_ia'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('parecer_descritivo')
            ->setDescriptionForEvent(fn (string $evento): string => match ($evento) {
                'created' => 'Parecer descritivo iniciado.',
                'updated' => 'Parecer descritivo atualizado.',
                'deleted' => 'Parecer descritivo excluído.',
                default => "Parecer descritivo: {$evento}",
            });
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function etapaAvaliativa(): BelongsTo
    {
        return $this->belongsTo(EtapaAvaliativa::class, 'etapa_avaliativa_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_user_id');
    }

    public function publicadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicado_por_user_id');
    }

    public function scopePublicados(Builder $query): Builder
    {
        return $query->where('status', StatusParecerDescritivo::Publicado->value);
    }

    public function estaPublicado(): bool
    {
        return $this->status === StatusParecerDescritivo::Publicado;
    }
}
