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

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
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

        $addSignatario = function (?Pessoa $pessoa) use (&$signatarios) {
            if (! $pessoa) {
                return;
            }

            // 1. E-mail cadastrado na ficha da Pessoa
            $emailPessoa = strtolower(trim($pessoa->email ?? ''));
            if (! empty($emailPessoa)) {
                $signatarios->push([
                    'nome' => $pessoa->nome,
                    'email' => $emailPessoa,
                ]);
            }

            // 2. E-mails de usuários (User) associados à Pessoa
            foreach ($pessoa->users as $user) {
                $emailUser = strtolower(trim($user->email ?? ''));
                if (! empty($emailUser)) {
                    $signatarios->push([
                        'nome' => $user->name ?? $pessoa->nome,
                        'email' => $emailUser,
                    ]);
                }
            }
        };

        // 1. Responsáveis Financeiros
        foreach ($this->responsaveisFinanceiros as $resp) {
            $addSignatario($resp->pessoa);
        }

        // 2. Pai e Mãe do aluno vinculado
        $vinculosInteresse = TipoVinculo::whereIn('nome', ['Pai', 'Mãe', 'pai', 'mãe'])->pluck('id')->toArray();

        $mat = $this->matricula;
        if ($mat) {
            $aluno = $mat->pessoa;
            if ($aluno) {
                foreach ($aluno->responsaveis as $resp) {
                    if (in_array($resp->pivot->tipo_vinculo_id, $vinculosInteresse)) {
                        $addSignatario($resp);
                    }
                }
            }

            // 3. Representantes Legais da unidade do aluno vinculado
            $unidade = $mat->turma?->serie?->curso?->unidade;
            if ($unidade) {
                foreach ($unidade->representantesLegais as $rep) {
                    $addSignatario($rep);
                }
            }
        }

        // Fallback: se não houver nenhum signatário, usa e-mail do aluno (se houver)
        if ($signatarios->isEmpty() && $mat) {
            $aluno = $mat->pessoa;
            if ($aluno) {
                $emailAluno = strtolower(trim($aluno->email ?? ''));
                if (! empty($emailAluno)) {
                    $signatarios->push([
                        'nome' => $aluno->nome,
                        'email' => $emailAluno,
                    ]);
                }
            }
        }

        return $signatarios
            ->filter(fn ($s) => ! empty($s['email']))
            ->unique('email')
            ->values();
    }

    /**
     * Retorna a lista de signatários com seus respetivos status individuais de assinatura.
     */
    public function getStatusSignatarios(): Collection
    {
        $signatarios = $this->getSignatarios();
        $logStatus = $this->assinafy_request_log['signers_status'] ?? [];
        $contratoConcluido = in_array($this->assinafy_status, ['signed', 'completed']);

        return $signatarios->map(function ($sig) use ($logStatus, $contratoConcluido) {
            $email = strtolower(trim($sig['email'] ?? ''));
            $infoAssinatura = $logStatus[$email] ?? null;

            $status = 'pending';
            $signedAt = null;

            if ($infoAssinatura) {
                $status = $infoAssinatura['status'] ?? 'pending';
                $signedAt = $infoAssinatura['signed_at'] ?? null;
            } elseif ($contratoConcluido) {
                $status = 'signed';
                $signedAt = $this->data_aceite?->format('Y-m-d H:i:s');
            }

            return [
                'nome' => $sig['nome'],
                'email' => $sig['email'],
                'status' => $status,
                'signed_at' => $signedAt,
            ];
        });
    }
}
