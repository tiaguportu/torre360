<?php

namespace App\Models;

use App\Enums\StatusAssinaturaContrato;
use App\Enums\StatusRematricula;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    /**
     * Status que indicam que TODAS as assinaturas foram coletadas. `ready`, `certificating` e
     * `certificated` são as etapas do Assinafy após a última assinatura (mantidas como status
     * distintos); `signed` e `completed` são valores legados.
     *
     * @var array<int, string>
     */
    public const STATUS_ASSINADO = ['ready', 'certificating', 'certificated', 'signed', 'completed'];

    protected $fillable = ['assinafy_id', 'assinafy_status', 'assinafy_request_log', 'valor_total', 'data_aceite', 'log_assinatura', 'template_contrato_id', 'matricula_id'];

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

    public function rematricula(): HasOne
    {
        return $this->hasOne(Rematricula::class);
    }

    /**
     * O contrato foi enviado para assinatura: a rematrícula dele, se ainda estiver só em "Dados
     * Confirmados" (primeiro envio falhou, ou o envio é feito depois pela tela de Contratos), passa a
     * aguardar a assinatura. Condicional para nunca desfazer uma confirmação que o webhook já fez.
     */
    public function marcarRematriculaAguardandoAssinatura(): void
    {
        $this->rematricula()
            ->where('status', StatusRematricula::DadosConfirmados->value)
            ->update(['status' => StatusRematricula::AguardandoAssinatura->value]);
    }

    /**
     * Verifica se o usuário pode ver este contrato: equipe interna sempre pode;
     * aluno/responsável só se o contrato for do próprio aluno ou de um dependente seu.
     */
    public function isAccessibleBy(User $user): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        $idsAcessiveis = $user->pessoasAcessiveis()->pluck('id');

        if ($this->matricula && $idsAcessiveis->contains($this->matricula->pessoa_id)) {
            return true;
        }

        return $this->responsaveisFinanceiros->pluck('pessoa_id')->intersect($idsAcessiveis)->isNotEmpty();
    }

    protected function casts(): array
    {
        return [
            'assinafy_request_log' => 'array',
            'data_aceite' => 'datetime',
        ];
    }

    /**
     * Verifica se todas as assinaturas já foram coletadas (qualquer outro status, como 'pendente' ou 'enviado', conta como não assinado).
     */
    public function estaAssinado(): bool
    {
        return in_array($this->assinafy_status, self::STATUS_ASSINADO, true);
    }

    /**
     * Status de assinatura como enum (nulo quando o valor gravado não é um dos conhecidos).
     */
    public function statusAssinatura(): ?StatusAssinaturaContrato
    {
        return filled($this->assinafy_status) ? StatusAssinaturaContrato::tryFrom($this->assinafy_status) : null;
    }

    /**
     * Scope para contratos com todas as assinaturas coletadas.
     */
    public function scopeAssinado(Builder $query): Builder
    {
        return $query->whereIn('assinafy_status', self::STATUS_ASSINADO);
    }

    /**
     * Scope para contratos ainda não assinados (status diferente de assinado/concluído).
     */
    public function scopeNaoAssinado(Builder $query): Builder
    {
        return $query->whereNotIn('assinafy_status', self::STATUS_ASSINADO);
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
        $vinculosInteresse = self::idsVinculosPaiMae();

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
     * IDs dos tipos de vínculo "Pai" e "Mãe", consultados uma única vez (e não a cada contrato listado).
     *
     * @return array<int, int>
     */
    private static function idsVinculosPaiMae(): array
    {
        return once(fn (): array => TipoVinculo::query()
            ->whereIn('nome', ['Pai', 'Mãe', 'pai', 'mãe'])
            ->pluck('id')
            ->all());
    }

    /**
     * Retorna a lista de signatários com seus respetivos status individuais de assinatura.
     */
    public function getStatusSignatarios(): Collection
    {
        $signatarios = $this->getSignatarios();
        $log = $this->assinafy_request_log ?? [];
        $logStatus = $log['signers_status'] ?? [];
        $contratoConcluido = $this->estaAssinado();

        // Extrai dados de assinatura gravados no histórico de logs se disponíveis
        $extraSigners = [];
        $searchPayloads = [
            $log['webhook_last']['object']['assignment']['summary']['signers'] ?? null,
            $log['webhook_last']['object']['assignment']['signers'] ?? null,
            $log['webhook_last']['object']['signers'] ?? null,
            $log['assignment']['signers'] ?? null,
            $log['assignment']['summary']['signers'] ?? null,
        ];

        foreach ($searchPayloads as $list) {
            if (is_array($list)) {
                foreach ($list as $s) {
                    if (is_array($s)) {
                        $email = strtolower(trim($s['email'] ?? ''));
                        $isCompleted = ($s['completed'] ?? false) === true
                            || ($s['signed'] ?? false) === true
                            || in_array(strtolower((string) ($s['status'] ?? '')), ['signed', 'completed']);

                        if ($email && $isCompleted) {
                            $extraSigners[$email] = 'signed';
                        }
                    }
                }
            }
        }

        // Checa também se há signatário diretamente no webhook_last
        $webhookSigner = $log['webhook_last']['object']['signer']['email']
            ?? $log['webhook_last']['signer']['email']
            ?? null;
        if ($webhookSigner) {
            $extraSigners[strtolower(trim($webhookSigner))] = 'signed';
        }

        return $signatarios->map(function ($sig) use ($logStatus, $extraSigners, $contratoConcluido) {
            $email = strtolower(trim($sig['email'] ?? ''));
            $infoAssinatura = $logStatus[$email] ?? null;

            $status = 'pending';
            $signedAt = null;

            if ($infoAssinatura) {
                $status = $infoAssinatura['status'] ?? 'pending';
                $signedAt = $infoAssinatura['signed_at'] ?? null;
            } elseif (isset($extraSigners[$email]) && $extraSigners[$email] === 'signed') {
                $status = 'signed';
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

    /**
     * Verifica se o contrato já possui registro de envio ou assinatura no Assinafy.
     */
    public function jaEnviadoAssinafy(): bool
    {
        return ! empty($this->assinafy_id) || ! empty($this->assinafy_request_log);
    }

    /**
     * Reseta as informações de envio e assinaturas do Assinafy no contrato,
     * permitindo que um novo envio para assinatura seja realizado.
     */
    public function resetAssinafyState(): void
    {
        if ($this->jaEnviadoAssinafy()) {
            $this->update([
                'assinafy_id' => null,
                'assinafy_status' => 'pending',
                'assinafy_request_log' => null,
                'data_aceite' => null,
            ]);
        }
    }
}
