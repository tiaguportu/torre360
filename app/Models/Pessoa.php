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

    /** Coluna `telefone` sem `( ) - + e espaço`, para comparar números gravados com máscara. */
    private const TELEFONE_SEM_MASCARA = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', ''), '+', '')";

    protected $table = 'pessoa';

    protected $fillable = ['endereco_id', 'naturalidade_id', 'nacionalidade_id', 'nome', 'cpf', 'codigo_indicacao', 'foto', 'telefone', 'email', 'user_id', 'data_nascimento', 'estado_civil', 'profissao', 'identidade', 'sexo', 'cor_raca', 'tipo_nacionalidade', 'aceita_comunicacao'];

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
            'aceita_comunicacao' => 'boolean',
            'consentimento_em' => 'datetime',
            'descadastrado_em' => 'datetime',
        ];
    }

    /**
     * Guarda a prova do consentimento dado pela pessoa (LGPD, art. 8º): quando, qual versão do texto
     * (`crm.lgpd.versao_consentimento`), por qual caminho e de qual IP. Fica fora do `$fillable` de propósito:
     * só o código que colheu o aceite grava, nunca um formulário do painel.
     */
    public function registrarConsentimento(string $origem, ?string $ip = null): void
    {
        $this->forceFill([
            'consentimento_em' => now(),
            'consentimento_versao' => (string) config('crm.lgpd.versao_consentimento'),
            'consentimento_origem' => $origem,
            'consentimento_ip' => $ip,
        ])->save();
    }

    /**
     * Pedido de descadastro (link dos e-mails da régua): a pessoa deixa de receber e-mails comerciais. O aceite
     * anterior permanece registrado; o descadastro tem data própria.
     */
    public function descadastrarDeComunicacoes(): void
    {
        $this->forceFill([
            'aceita_comunicacao' => false,
            'descadastrado_em' => $this->descadastrado_em ?? now(),
        ])->save();
    }

    /**
     * Pessoas que não pediram para não receber comunicações (LGPD).
     */
    public function scopeAceitamComunicacao(Builder $query): Builder
    {
        return $query->where('aceita_comunicacao', true);
    }

    /**
     * Busca por nome, e-mail, telefone ou CPF. O telefone é gravado com máscara ("(11) 99999-0000"), então
     * quem digita só os números (ou "11 99999") também precisa achar: a comparação ignora `( ) - + e espaço`.
     * Dígitos curtos (menos de 3) não entram na busca por telefone/CPF para não casar com quase tudo.
     */
    public function scopeBusca(Builder $query, string $termo): Builder
    {
        $termo = trim($termo);
        $digitos = preg_replace('/\D/', '', $termo) ?? '';

        return $query->where(function (Builder $q) use ($termo, $digitos): void {
            $q->where('nome', 'like', "%{$termo}%")
                ->orWhere('email', 'like', "%{$termo}%");

            if (strlen($digitos) >= 3) {
                $q->orWhereRaw(self::TELEFONE_SEM_MASCARA.' LIKE ?', ["%{$digitos}%"])
                    ->orWhere('cpf', 'like', "%{$digitos}%");
            }
        });
    }

    /**
     * Pessoas com este telefone, com ou sem máscara e com ou sem o código do país (55). Compara os 11 últimos
     * dígitos (celular com DDD) ou, se o número tiver menos, os 10 últimos. Menos de 10 dígitos não identifica
     * ninguém: não casa com nada.
     */
    public function scopeComTelefone(Builder $query, ?string $telefone): Builder
    {
        $digitos = preg_replace('/\D/', '', (string) $telefone) ?? '';

        if (strlen($digitos) < 10) {
            return $query->whereRaw('1 = 0');
        }

        $final = substr($digitos, -min(11, strlen($digitos)));

        return $query->whereRaw(self::TELEFONE_SEM_MASCARA.' LIKE ?', ["%{$final}"]);
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class, 'pessoa_id');
    }

    public function historicosEscolares(): HasMany
    {
        return $this->hasMany(HistoricoEscolar::class, 'pessoa_id');
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

    public function indicacoesFeitas(): HasMany
    {
        return $this->hasMany(IndicacaoInteressado::class, 'indicador_pessoa_id');
    }

    public function obterOuCriarCodigoIndicacao(): string
    {
        if (filled($this->codigo_indicacao)) {
            return $this->codigo_indicacao;
        }

        $codigo = IndicacaoInteressado::gerarCodigoParaPessoa($this);
        $this->update(['codigo_indicacao' => $codigo]);

        return $codigo;
    }

    public function linkIndicacao(): string
    {
        $codigo = $this->obterOuCriarCodigoIndicacao();

        return url('/quero-matricular?indicacao='.$codigo);
    }
}
