<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Matricula extends Model
{
    protected $table = 'matricula';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function ($matricula) {
            if (! $matricula->codigo) {
                $anoCorrente = now()->year;
                $ultimoNumero = static::where('codigo', 'like', $anoCorrente.'%')
                    ->count();

                $proximoNumero = $ultimoNumero + 1;
                $matricula->codigo = $anoCorrente.str_pad($proximoNumero, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function situacaoMatricula(): BelongsTo
    {
        return $this->belongsTo(SituacaoMatricula::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class);
    }

    public function documentoInseridos(): HasMany
    {
        return $this->hasMany(DocumentoInserido::class, 'matricula_id');
    }

    public function frequenciaEscolars(): HasMany
    {
        return $this->hasMany(FrequenciaEscolar::class);
    }

    /**
     * Verifica se faltam documentos obrigatórios para esta matrícula.
     * Os documentos obrigatórios se relacionam com curso, o curso se relaciona com serie, a serie se relaciona com turma, a turma se relaciona com matricula.
     */
    public function hasMissingMandatoryDocuments(): bool
    {
        return $this->getMissingMandatoryDocumentsCount() > 0;
    }

    /**
     * Retorna a coleção de documentos obrigatórios que faltam para esta matrícula.
     * Considera apenas documentos com flag_obrigatorio = true e flag_ativo = true.
     *
     * @return Collection<DocumentoObrigatorio>
     */
    public function getMissingMandatoryDocuments(): Collection
    {
        $curso = $this->turma?->serie?->curso;

        if (! $curso) {
            return collect();
        }

        // Busca documentos que são obrigatórios e ativos no curso
        $obrigatorios = $curso->documentos()
            ->where('flag_obrigatorio', true)
            ->where('flag_ativo', true)
            ->get();

        if ($obrigatorios->isEmpty()) {
            return collect();
        }

        $inseridosIds = $this->documentoInseridos()
            ->pluck('documento_obrigatorio_id')
            ->toArray();

        return $obrigatorios->reject(function ($doc) use ($inseridosIds) {
            return in_array($doc->id, $inseridosIds);
        });
    }

    /**
     * Retorna a quantidade de documentos obrigatórios que faltam para esta matrícula.
     */
    public function getMissingMandatoryDocumentsCount(): int
    {
        return $this->getMissingMandatoryDocuments()->count();
    }
}
