<?php

namespace App\Models;

use App\Enums\CorRaca;
use App\Enums\Sexo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pessoa extends Model
{
    use HasFactory;

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
}
