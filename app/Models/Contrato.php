<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Contrato extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['matricula_id', 'valor_total', 'data_aceite'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $table = 'contrato';

    protected $guarded = [];

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    public function faturas(): HasMany
    {
        return $this->hasMany(Fatura::class);
    }

    public function responsaveisFinanceiros(): HasMany
    {
        return $this->hasMany(ResponsavelFinanceiro::class);
    }

    public function templateContrato(): BelongsTo
    {
        return $this->belongsTo(TemplateContrato::class);
    }

    protected function casts(): array
    {
        return [
            'assinafy_request_log' => 'array',
            'data_aceite' => 'datetime',
        ];
    }

    /**
     * Retorna a lista de signatários (Pai, Mãe, Responsável Financeiro e Representante Legal) para assinatura do contrato.
     */
    public function getSignatarios(): Collection
    {
        $signatarios = collect();

        // 1. Responsáveis Financeiros
        foreach ($this->responsaveisFinanceiros as $resp) {
            $pessoa = $resp->pessoa;
            if (! $pessoa) {
                continue;
            }

            foreach ($pessoa->users as $user) {
                if ($user->email) {
                    $signatarios->push([
                        'nome' => $user->name ?? $pessoa->nome,
                        'email' => strtolower(trim($user->email)),
                    ]);
                }
            }
        }

        // 2. Pai e Mãe dos alunos vinculados
        $vinculosInteresse = TipoVinculo::whereIn('nome', ['Pai', 'Mãe'])->pluck('id')->toArray();

        foreach ($this->matriculas as $mat) {
            $aluno = $mat->pessoa;
            if (! $aluno) {
                continue;
            }

            foreach ($aluno->responsaveis as $resp) {
                if (in_array($resp->pivot->tipo_vinculo_id, $vinculosInteresse)) {
                    foreach ($resp->users as $user) {
                        if ($user->email) {
                            $signatarios->push([
                                'nome' => $user->name ?? $resp->nome,
                                'email' => strtolower(trim($user->email)),
                            ]);
                        }
                    }
                }
            }
        }

        // 3. Representantes Legais das unidades dos alunos vinculados
        foreach ($this->matriculas as $mat) {
            $unidade = $mat->turma?->serie?->curso?->unidade;
            if ($unidade) {
                foreach ($unidade->representantesLegais as $rep) {
                    foreach ($rep->users as $user) {
                        if ($user->email) {
                            $signatarios->push([
                                'nome' => $user->name ?? $rep->nome,
                                'email' => strtolower(trim($user->email)),
                            ]);
                        }
                    }
                }
            }
        }

        // Fallback: se não houver nenhum signatário via usuário, usa e-mail do primeiro aluno
        if ($signatarios->isEmpty()) {
            $aluno = $this->matriculas->first()?->pessoa;
            if ($aluno) {
                $signatarios->push([
                    'nome' => $aluno->nome,
                    'email' => strtolower(trim($aluno->email ?? '')),
                ]);
            }
        }

        return $signatarios->unique('email')->values();
    }
}
