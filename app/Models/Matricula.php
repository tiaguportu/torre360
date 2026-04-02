<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function contrato(): HasOne
    {
        return $this->hasOne(Contrato::class);
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
        $curso = $this->turma?->serie?->curso;

        if (! $curso) {
            return false;
        }

        $totalObrigatorios = $curso->documentos()->count();

        if ($totalObrigatorios === 0) {
            return false;
        }

        $totalInseridos = $this->documentoInseridos()
            ->whereIn('documento_obrigatorio_id', $curso->documentos()->pluck('id'))
            ->count('documento_obrigatorio_id');

        return $totalInseridos < $totalObrigatorios;
    }
}
