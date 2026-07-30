<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turma extends Model
{
    use HasFactory;

    protected $table = 'turma';

    protected $guarded = [];

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    public function etapaEnsinoAgregada(): BelongsTo
    {
        return $this->belongsTo(EtapaEnsinoAgregada::class, 'etapa_ensino_agregada_id');
    }

    public function etapaEnsino(): BelongsTo
    {
        return $this->belongsTo(EtapaEnsino::class, 'etapa_ensino_id');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function professorConselheiro(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'professor_conselheiro_id');
    }

    public function etapaAvaliativa(): BelongsTo
    {
        return $this->belongsTo(EtapaAvaliativa::class);
    }

    public function periodoLetivo(): BelongsTo
    {
        return $this->belongsTo(PeriodoLetivo::class);
    }

    public function avaliacoes(): HasMany
    {
        return $this->hasMany(Avaliacao::class);
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    public function cronogramasAula(): HasMany
    {
        return $this->hasMany(CronogramaAula::class);
    }

    public function tiposDocumentos(): BelongsToMany
    {
        return $this->belongsToMany(TipoDocumento::class, 'tipo_documento_turma');
    }

    public function habilidades(): BelongsToMany
    {
        return $this->belongsToMany(Habilidade::class, 'turma_habilidade')->withPivot('professor_id')->withTimestamps();
    }

    public function disciplinas(): BelongsToMany
    {
        return $this->belongsToMany(Disciplina::class, 'turma_disciplina')->withPivot('professor_id')->withTimestamps();
    }

    public function horariosFuncionamento(): HasMany
    {
        return $this->hasMany(TurmaHorario::class);
    }

    protected function casts(): array
    {
        return [
            'tipo_avaliacao' => 'string',
            'turma_educacao_especial' => 'boolean',
            'tipo_mediacao_didatico_pedagogica' => 'integer',
            'tipo_turma' => 'integer',
            'local_funcionamento_diferenciado' => 'integer',
            'carga_horaria_total' => 'integer',
            'forma_organizacao' => 'integer',
            'modalidade_ensino' => 'integer',
            'tipo_lingua_ministrada' => 'integer',
            'turma_educacao_bilingue_surdos' => 'boolean',
            'flag_aee_ensino_libras' => 'boolean',
            'flag_aee_ensino_soroba' => 'boolean',
            'flag_aee_ensino_informatica_acessivel' => 'boolean',
            'flag_aee_ensino_caa' => 'boolean',
            'flag_aee_tecnologia_assistiva' => 'boolean',
            'flag_aee_processos_cognitivos' => 'boolean',
            'flag_aee_enriquecimento_curricular' => 'boolean',
            'flag_aee_portugues_segunda_lingua' => 'boolean',
            'flag_aee_orientacao_mobilidade' => 'boolean',
        ];
    }
}
