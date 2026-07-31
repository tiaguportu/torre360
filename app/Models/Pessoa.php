<?php

namespace App\Models;

use App\Enums\CorRaca;
use App\Enums\Nacionalidade;
use App\Enums\Sexo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;

class Pessoa extends Model
{
    use HasFactory, Notifiable;

    protected $table = 'pessoa';

    protected $guarded = [];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pessoa_user', 'pessoa_id', 'user_id');
    }

    public function enderecos(): BelongsToMany
    {
        return $this->belongsToMany(Endereco::class, 'endereco_pessoa', 'pessoa_id', 'endereco_id');
    }

    public function naturalidade(): BelongsTo
    {
        return $this->belongsTo(Cidade::class, 'naturalidade_id');
    }

    public function nacionalidade(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'nacionalidade_id');
    }

    protected function casts(): array
    {
        return [
            'sexo' => Sexo::class,
            'cor_raca' => CorRaca::class,
            'tipo_nacionalidade' => Nacionalidade::class,
        ];
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class, 'pessoa_id');
    }

    public function responsaveisFinanceiros(): HasMany
    {
        return $this->hasMany(ResponsavelFinanceiro::class, 'pessoa_id');
    }

    public function coordenacoes(): HasMany
    {
        return $this->hasMany(Coordenador::class, 'pessoa_id');
    }

    public function alunos(): BelongsToMany
    {
        return $this->belongsToMany(Pessoa::class, 'aluno_responsavel', 'responsavel_id', 'aluno_id')
            ->using(AlunoResponsavel::class)
            ->withPivot('tipo_vinculo_id', 'permissao_retirada', 'observacao')
            ->withTimestamps();
    }

    public function responsaveis(): BelongsToMany
    {
        return $this->belongsToMany(Pessoa::class, 'aluno_responsavel', 'aluno_id', 'responsavel_id')
            ->using(AlunoResponsavel::class)
            ->withPivot('tipo_vinculo_id', 'permissao_retirada', 'observacao')
            ->withTimestamps();
    }

    public function interessado(): HasOne
    {
        return $this->hasOne(Interessado::class, 'pessoa_id');
    }

    public function unidadesRepresentadas(): BelongsToMany
    {
        return $this->belongsToMany(Unidade::class, 'representante_unidade', 'pessoa_id', 'unidade_id')->withTimestamps();
    }

    public function preceptoriasComoProfesor(): HasMany
    {
        return $this->hasMany(Preceptoria::class, 'professor_id');
    }

    public function necessidadesEducacaoEspecial(): HasMany
    {
        return $this->hasMany(NecessidadeEducacaoEspecial::class, 'pessoa_id');
    }

    public function transtornosAprendizagem(): HasMany
    {
        return $this->hasMany(TranstornoAprendizagem::class, 'pessoa_id');
    }

    public function recursosAcessibilidade(): HasMany
    {
        return $this->hasMany(RecursoAcessibilidade::class, 'pessoa_id');
    }

    /**
     * Retorna uma lista de motivos (vínculos) que impedem a exclusão da pessoa.
     */
    public function getInviabilityReasons(): array
    {
        $reasons = [];

        if ($this->matriculas()->exists()) {
            $reasons[] = 'Possui matrículas vinculadas';
        }

        if ($this->interessado()->exists()) {
            $reasons[] = 'Possui cadastro no CRM (Interessado)';
        }

        if ($this->responsaveisFinanceiros()->exists()) {
            $reasons[] = 'É responsável financeiro em algum contrato';
        }

        if ($this->preceptoriasComoProfesor()->exists()) {
            $reasons[] = 'Possui preceptorias agendadas como professor';
        }

        if ($this->alunos()->exists()) {
            $reasons[] = 'Possui alunos vinculados (é responsável)';
        }

        if ($this->responsaveis()->exists()) {
            $reasons[] = 'Possui responsáveis vinculados (é aluno)';
        }

        if ($this->unidadesRepresentadas()->exists()) {
            $reasons[] = 'É representante legal de uma unidade';
        }

        if ($this->users()->exists()) {
            $reasons[] = 'Possui usuário de acesso ao sistema vinculado';
        }

        return $reasons;
    }
}
