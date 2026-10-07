<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoriaExigenciaDocumento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TipoDocumento extends Model
{
    protected $table = 'tipo_documento';

    protected $fillable = [
        'curso_id',
        'nome',
        'categoria_exigencia',
        'flag_obrigatorio',
        'modelo_arquivo',
        'modelo_link',
    ];

    protected function casts(): array
    {
        return [
            'categoria_exigencia' => CategoriaExigenciaDocumento::class,
            'flag_obrigatorio' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            // Garante retrocompatibilidade: se a categoria for obrigatória (contrato ou histórico), flag_obrigatorio = true
            if ($model->categoria_exigencia instanceof CategoriaExigenciaDocumento) {
                $model->flag_obrigatorio = in_array($model->categoria_exigencia, [
                    CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO,
                    CategoriaExigenciaDocumento::OBRIGATORIO_HISTORICO,
                ], true);
            }
        });
    }

    public function isObrigatorioContrato(): bool
    {
        return $this->categoria_exigencia === CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO;
    }

    public function isObrigatorioHistorico(): bool
    {
        return $this->categoria_exigencia === CategoriaExigenciaDocumento::OBRIGATORIO_HISTORICO;
    }

    public function isOpcional(): bool
    {
        return $this->categoria_exigencia === CategoriaExigenciaDocumento::OPCIONAL;
    }

    public function isInterno(): bool
    {
        return $this->categoria_exigencia === CategoriaExigenciaDocumento::INTERNO;
    }

    public function isVisivelPortalFamilia(): bool
    {
        return $this->categoria_exigencia?->isVisivelPortalFamilia() ?? true;
    }

    public function scopeVisivelPortalFamilia(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('categoria_exigencia')
                ->orWhere('categoria_exigencia', '!=', CategoriaExigenciaDocumento::INTERNO->value);
        });
    }

    public function scopeObrigatoriosParaContrato(Builder $query): Builder
    {
        return $query->where('categoria_exigencia', CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO->value);
    }

    public function scopeObrigatoriosParaHistorico(Builder $query): Builder
    {
        return $query->where('categoria_exigencia', CategoriaExigenciaDocumento::OBRIGATORIO_HISTORICO->value);
    }

    public function scopeOpcionais(Builder $query): Builder
    {
        return $query->where('categoria_exigencia', CategoriaExigenciaDocumento::OPCIONAL->value);
    }

    /**
     * Escopo para filtrar documentos aplicáveis a determinados cursos (ou gerais).
     * Se IDs de cursos forem fornecidos, retorna documentos vinculados a esses cursos OU gerais (sem cursos vinculados).
     * Se nenhum curso for fornecido, retorna apenas documentos gerais (sem nenhum curso vinculado).
     */
    public function scopeParaCursos(Builder $query, mixed $cursoIds): Builder
    {
        $ids = collect($cursoIds)->filter()->values()->all();

        if (empty($ids)) {
            return $query->whereDoesntHave('cursos');
        }

        return $query->where(function (Builder $q) use ($ids): void {
            $q->whereHas('cursos', fn (Builder $cq) => $cq->whereIn('curso.id', $ids))
                ->orWhereDoesntHave('cursos');
        });
    }

    /**
     * Verifica se o tipo de documento se aplica a um curso específico.
     * Retorna true se estiver vinculado ao curso ou se for geral (sem cursos vinculados).
     */
    public function aplicaAoCurso(?int $cursoId): bool
    {
        if (! $cursoId) {
            return $this->relationLoaded('cursos')
                ? $this->cursos->isEmpty()
                : ! $this->cursos()->exists();
        }

        if ($this->relationLoaded('cursos')) {
            return $this->cursos->isEmpty() || $this->cursos->contains('id', $cursoId);
        }

        return ! $this->cursos()->exists() || $this->cursos()->where('curso.id', $cursoId)->exists();
    }

    public function cursos(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'tipo_documento_curso');
    }

    public function turmas(): BelongsToMany
    {
        return $this->belongsToMany(Turma::class, 'tipo_documento_turma');
    }

    public function matriculas(): BelongsToMany
    {
        return $this->belongsToMany(Matricula::class, 'tipo_documento_matricula');
    }
}
