<?php

namespace App\Models;

use App\Enums\StatusTurma;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turma extends Model
{
    use HasFactory;

    protected $table = 'turma';

    /**
     * Espelha o default da coluna `status` para que uma Turma recém-instanciada já nasça Ativa.
     */
    protected $attributes = [
        'status' => 'ativa',
    ];

    protected $fillable = [
        'status', 'serie_id', 'turno_id', 'codigo', 'tipo_mediacao_didatico_pedagogica', 'tipo_turma',
        'local_funcionamento_diferenciado', 'forma_organizacao', 'modalidade_ensino',
        'tipo_lingua_ministrada', 'codigo_lingua_indigena', 'turma_educacao_bilingue_surdos',
        'flag_aee_ensino_libras', 'flag_aee_ensino_soroba', 'flag_aee_ensino_informatica_acessivel',
        'flag_aee_ensino_caa', 'flag_aee_tecnologia_assistiva', 'flag_aee_processos_cognitivos',
        'flag_aee_enriquecimento_curricular', 'flag_aee_portugues_segunda_lingua',
        'flag_aee_orientacao_mobilidade', 'turma_educacao_especial', 'professor_conselheiro_id',
        'vagas_maximas', 'carga_horaria_total', 'nome', 'periodo_letivo_id', 'cor',
        'tipo_avaliacao', 'etapa_ensino_agregada_id', 'etapa_ensino_id',
        'mensalidade_base', 'custo_docente_mensal', 'custo_operacional_rateado', 'meta_margem_lucro',
    ];

    /**
     * Turmas em andamento. Telas operacionais (cronograma, avaliações, planos de aula, grade)
     * só devem oferecer estas.
     *
     * `$manterTurmaIds`: turmas que devem continuar visíveis mesmo fora do status (a turma atual
     * do registro que está sendo editado). Os selects por relacionamento do Filament aplicam o
     * filtro também ao rótulo do valor já selecionado; sem isto, um registro antigo (turma
     * concluída) apareceria com o campo em branco.
     *
     * @param  int|string|array<int|string|null>|null  $manterTurmaIds
     */
    public function scopeAtivas(Builder $query, int|string|array|null $manterTurmaIds = null): Builder
    {
        return $this->filtrarPorStatus($query, [StatusTurma::Ativa->value], $manterTurmaIds);
    }

    /**
     * Turmas que ainda podem receber matrículas: Planejada (próximo período) e Ativa.
     * Concluídas e canceladas ficam apenas para consulta e histórico.
     *
     * @param  int|string|array<int|string|null>|null  $manterTurmaIds  ver {@see scopeAtivas()}
     */
    public function scopeAbertasParaMatricula(Builder $query, int|string|array|null $manterTurmaIds = null): Builder
    {
        return $this->filtrarPorStatus($query, StatusTurma::valoresAbertosParaMatricula(), $manterTurmaIds);
    }

    /**
     * Turmas vigentes (Planejada e Ativa) para telas operacionais — cronograma, avaliações, planos
     * de aula, substituições —: a secretaria prepara o próximo ano antes dele começar, mas não deve
     * lançar nada novo em turma Concluída ou Cancelada.
     *
     * @param  int|string|array<int|string|null>|null  $manterTurmaIds  ver {@see scopeAtivas()}
     */
    public function scopeVigentes(Builder $query, int|string|array|null $manterTurmaIds = null): Builder
    {
        return $this->filtrarPorStatus($query, StatusTurma::valoresAbertosParaMatricula(), $manterTurmaIds);
    }

    /**
     * @param  list<string>  $statuses
     * @param  int|string|array<int|string|null>|null  $manterTurmaIds
     */
    private function filtrarPorStatus(Builder $query, array $statuses, int|string|array|null $manterTurmaIds): Builder
    {
        $manter = array_filter((array) $manterTurmaIds);

        return $query->where(function (Builder $q) use ($statuses, $manter) {
            $q->whereIn($this->qualifyColumn('status'), $statuses);

            if ($manter !== []) {
                $q->orWhereIn($this->qualifyColumn('id'), $manter);
            }
        });
    }

    public function scopeDoPeriodo(Builder $query, int|string|null $periodoLetivoId): Builder
    {
        return $query->where('periodo_letivo_id', $periodoLetivoId);
    }

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

    public function gradeHorarios(): HasMany
    {
        return $this->hasMany(GradeHorario::class);
    }

    public function planosAula(): HasMany
    {
        return $this->hasMany(PlanoAula::class);
    }

    /**
     * Alunos com matrícula ativa hoje (usado para validar capacidade de sala).
     */
    public function matriculasAtivasCount(): int
    {
        return $this->matriculas()
            ->where(function ($q) {
                $q->whereNull('data_ativacao')->orWhereDate('data_ativacao', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('data_desativacao')->orWhereDate('data_desativacao', '>', now());
            })
            ->count();
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
            'status' => StatusTurma::class,
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
            'mensalidade_base' => 'decimal:2',
            'custo_docente_mensal' => 'decimal:2',
            'custo_operacional_rateado' => 'decimal:2',
            'meta_margem_lucro' => 'decimal:2',
        ];
    }
}
