<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pessoa extends Model
{
    protected $table = 'pessoa';

    protected $guarded = [];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pessoa_user', 'pessoa_id', 'user_id');
    }

    public function endereco(): BelongsTo
    {
        return $this->belongsTo(Endereco::class);
    }

    public function naturalidade(): BelongsTo
    {
        return $this->belongsTo(Cidade::class, 'naturalidade_id');
    }

    public function nacionalidade(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'nacionalidade_id');
    }

    public function sexo(): BelongsTo
    {
        return $this->belongsTo(Sexo::class);
    }

    public function corRaca(): BelongsTo
    {
        return $this->belongsTo(CorRaca::class);
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
            ->withPivot('tipo_vinculo_id', 'permissao_retirada', 'observacao')
            ->withTimestamps();
    }

    public function responsaveis(): BelongsToMany
    {
        return $this->belongsToMany(Pessoa::class, 'aluno_responsavel', 'aluno_id', 'responsavel_id')
            ->withPivot('tipo_vinculo_id', 'permissao_retirada', 'observacao')
            ->withTimestamps();
    }
}
