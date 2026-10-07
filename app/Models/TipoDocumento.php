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
