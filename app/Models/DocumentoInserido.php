<?php

namespace App\Models;

use App\Enums\SituacaoDocumento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DocumentoInserido extends Model
{
    use LogsActivity;

    protected $table = 'documento_inserido';

    protected $fillable = [
        'tipo_documento_id',
        'matricula_id',
        'interessado_id',
        'interessado_dependente_id',
        'status',
        'observacoes',
        'arquivo_path',
        'nome_arquivo_original',
        'hash_arquivo',
        'dados_ia',
        'analisado_ia_em',
    ];

    protected function casts(): array
    {
        return [
            'status' => SituacaoDocumento::class,
            'dados_ia' => 'array',
            'analisado_ia_em' => 'datetime',
        ];
    }

    /**
     * Indica se o documento já passou pela análise por IA.
     */
    public function temAnaliseIa(): bool
    {
        return ! empty($this->dados_ia) && $this->analisado_ia_em !== null;
    }

    /**
     * Retorna se a IA considerou a imagem do documento nítida e legível.
     */
    public function isLegivelIa(): bool
    {
        return (bool) ($this->dados_ia['qualidade']['legivel'] ?? ($this->dados_ia['legivel'] ?? false));
    }

    /**
     * Retorna se o documento identificado confere com o tipo esperado.
     */
    public function confereTipoIa(): bool
    {
        return (bool) ($this->dados_ia['confere_com_solicitado'] ?? ($this->dados_ia['tipo_confere'] ?? false));
    }

    /**
     * Retorna um resumo textual do parecer da IA.
     */
    public function resumoIa(): string
    {
        if (! $this->temAnaliseIa()) {
            return 'Aguardando processamento por IA';
        }

        $tipo = $this->dados_ia['documento_identificado'] ?? ($this->dados_ia['tipo_detectado'] ?? 'Não identificado');
        $legivel = $this->isLegivelIa() ? 'Legível' : 'Baixa nitidez / Cortado';
        $score = $this->dados_ia['score_confianca'] ?? null;
        $scoreText = $score ? " ({$score}%)" : '';

        return "{$tipo} • {$legivel}{$scoreText}";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['tipo_documento_id', 'matricula_id', 'status', 'observacoes', 'nome_arquivo_original'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('documento_inserido')
            ->setDescriptionForEvent(function (string $eventName) {
                $tipoNome = $this->tipoDocumento?->nome ?? 'Tipo não identificado';
                $alunoNome = $this->matricula?->pessoa?->nome ?? 'Matrícula não identificada';

                return match ($eventName) {
                    'created' => "O documento '{$tipoNome}' foi enviado para a matrícula de {$alunoNome}.",
                    'updated' => "O documento '{$tipoNome}' da matrícula de {$alunoNome} foi atualizado.",
                    'deleted' => "O documento '{$tipoNome}' da matrícula de {$alunoNome} foi excluído.",
                    default => "Documento '{$tipoNome}': evento {$eventName}",
                };
            });
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento_id');
    }

    public function transitionTo(SituacaoDocumento $target): void
    {
        if ($this->status === $target) {
            return;
        }

        if (! $this->status->canTransitionTo($target)) {
            throw new \RuntimeException("Transição de {$this->status->value} para {$target->value} não é permitida.");
        }

        $this->status = $target;
        $this->save();
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'matricula_id');
    }

    public function interessado(): BelongsTo
    {
        return $this->belongsTo(Interessado::class, 'interessado_id');
    }

    public function dependente(): BelongsTo
    {
        return $this->belongsTo(InteressadoDependente::class, 'interessado_dependente_id');
    }

    /**
     * Documento pessoal (RG, CPF, certidão, comprovantes): equipe administrativa ou quem tem a permissão específica;
     * demais, só a própria família. Ser "da equipe" (ex.: professor) não basta.
     */
    public function isAccessibleBy(User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isEquipeAdministrativa() || $user->can('View:DocumentoInserido')) {
            return true;
        }

        if ($this->matricula) {
            return $this->matricula->isAccessibleByFamilia($user);
        }

        if ($this->interessado) {
            return $this->interessado->usuario_id === $user->id || $user->can('View:Interessado');
        }

        return false;
    }
}
