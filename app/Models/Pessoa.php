<?php

namespace App\Models;

use App\Casts\CorRacaCast;
use App\Enums\Nacionalidade;
use App\Enums\Sexo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    protected function cpf(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value ? (preg_replace('/\D/', '', $value) ?: null) : null,
        );
    }

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
            'cor_raca' => CorRacaCast::class,
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

    public function fichaMedica(): HasOne
    {
        return $this->hasOne(FichaMedica::class, 'pessoa_id');
    }

    public function atendimentosEnfermagem(): HasMany
    {
        return $this->hasMany(AtendimentoEnfermagem::class, 'pessoa_id');
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

    /**
     * Verifica se a nacionalidade da pessoa é brasileira.
     */
    public function isBrasileiro(): bool
    {
        if (! $this->nacionalidade_id) {
            return false;
        }

        if ($this->relationLoaded('nacionalidade')) {
            return strtolower($this->nacionalidade?->nome ?? '') === 'brasil' || strtoupper($this->nacionalidade?->sigla ?? '') === 'bra';
        }

        return Pais::where('id', $this->nacionalidade_id)
            ->where(function ($q) {
                $q->whereRaw('LOWER(nome) = ?', ['brasil'])
                    ->orWhereRaw('UPPER(sigla) = ?', ['bra']);
            })
            ->exists();
    }

    /**
     * Verifica se o cadastro da pessoa possui pendências de dados básicos ou falta de endereço para qualificação civil.
     */
    public function hasIncompleteCadastro(): bool
    {
        if (blank($this->nome) ||
            blank($this->data_nascimento) ||
            blank($this->cpf) ||
            blank($this->sexo) ||
            blank($this->cor_raca) ||
            blank($this->nacionalidade_id)
        ) {
            return true;
        }

        if ($this->isBrasileiro() && blank($this->naturalidade_id)) {
            return true;
        }

        $temEndereco = $this->relationLoaded('enderecos')
            ? $this->enderecos->isNotEmpty()
            : $this->enderecos()->exists();

        if (! $temEndereco) {
            return true;
        }

        return false;
    }

    /**
     * Retorna a lista de campos ou dados faltantes no cadastro da pessoa.
     *
     * @return array<string>
     */
    public function getMissingCadastroFields(): array
    {
        $faltantes = [];

        if (blank($this->nome)) {
            $faltantes[] = 'Nome';
        }
        if (blank($this->data_nascimento)) {
            $faltantes[] = 'Data de Nascimento';
        }
        if (blank($this->cpf)) {
            $faltantes[] = 'CPF';
        }
        if (blank($this->sexo)) {
            $faltantes[] = 'Sexo';
        }
        if (blank($this->cor_raca)) {
            $faltantes[] = 'Cor/Raça';
        }
        if (blank($this->nacionalidade_id)) {
            $faltantes[] = 'Nacionalidade';
        }
        if ($this->isBrasileiro() && blank($this->naturalidade_id)) {
            $faltantes[] = 'Naturalidade';
        }

        $temEndereco = $this->relationLoaded('enderecos')
            ? $this->enderecos->isNotEmpty()
            : $this->enderecos()->exists();

        if (! $temEndereco) {
            $faltantes[] = 'Endereço';
        }

        return $faltantes;
    }

    /**
     * Scope para filtrar pessoas com cadastro incompleto.
     */
    public function scopeIncompleto(Builder $query): Builder
    {
        return $query->where(function ($sub) {
            $sub->whereNull('nome')->orWhere('nome', '')
                ->orWhereNull('data_nascimento')
                ->orWhereNull('cpf')->orWhere('cpf', '')
                ->orWhereNull('sexo')
                ->orWhereNull('cor_raca')
                ->orWhereNull('nacionalidade_id')
                ->orWhere(function ($q) {
                    $q->whereHas('nacionalidade', function ($paisQuery) {
                        $paisQuery->whereRaw('LOWER(nome) = ?', ['brasil'])
                            ->orWhereRaw('UPPER(sigla) = ?', ['bra']);
                    })
                        ->whereNull('naturalidade_id');
                })
                ->orWhereDoesntHave('enderecos');
        });
    }

    /**
     * Scope para filtrar pessoas com cadastro completo.
     */
    public function scopeCompleto(Builder $query): Builder
    {
        return $query->whereNotNull('nome')->where('nome', '!=', '')
            ->whereNotNull('data_nascimento')
            ->whereNotNull('cpf')->where('cpf', '!=', '')
            ->whereNotNull('sexo')
            ->whereNotNull('cor_raca')
            ->whereNotNull('nacionalidade_id')
            ->where(function ($sub) {
                $sub->whereDoesntHave('nacionalidade', function ($paisQuery) {
                    $paisQuery->whereRaw('LOWER(nome) = ?', ['brasil'])
                        ->orWhereRaw('UPPER(sigla) = ?', ['bra']);
                })
                    ->orWhereNotNull('naturalidade_id');
            })
            ->whereHas('enderecos');
    }
}
