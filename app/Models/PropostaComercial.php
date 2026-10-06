<?php

namespace App\Models;

use App\Enums\NivelAlcadaComercial;
use App\Enums\StatusPropostaComercial;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PropostaComercial extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'proposta_comercials';

    protected $fillable = [
        'codigo',
        'interessado_id',
        'responsavel_nome',
        'responsavel_telefone',
        'responsavel_email',
        'aluno_nome',
        'unidade_id',
        'curso_id',
        'serie_id',
        'turma_id',
        'turno_id',
        'quantidade_alunos',
        'valor_tabela_mensal',
        'tipo_desconto',
        'desconto_solicitado',
        'valor_desconto_mensal',
        'valor_liquido_mensal',
        'quantidade_parcelas',
        'valor_total_anual',
        'motivo_desconto',
        'status',
        'nivel_alcada_necessario',
        'solicitado_por_user_id',
        'aprovado_por_user_id',
        'aprovado_em',
        'motivo_recusa',
        'validade',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusPropostaComercial::class,
            'nivel_alcada_necessario' => NivelAlcadaComercial::class,
            'valor_tabela_mensal' => 'decimal:2',
            'desconto_solicitado' => 'decimal:2',
            'valor_desconto_mensal' => 'decimal:2',
            'valor_liquido_mensal' => 'decimal:2',
            'valor_total_anual' => 'decimal:2',
            'quantidade_alunos' => 'integer',
            'quantidade_parcelas' => 'integer',
            'aprovado_em' => 'datetime',
            'validade' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'codigo', 'status', 'desconto_solicitado', 'valor_liquido_mensal',
                'nivel_alcada_necessario', 'aprovado_por_user_id', 'aprovado_em',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public static function gerarCodigo(): string
    {
        $ano = now()->year;
        $ultimoId = (int) static::whereYear('created_at', $ano)->max('id') + 1;

        return sprintf('PROP-%d-%05d', $ano, $ultimoId);
    }

    public function isPendente(): bool
    {
        return $this->status === StatusPropostaComercial::AguardandoAprovacao;
    }

    public function podeSerAprovadaPor(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin') || $user->can('Aprovar:PropostaComercial')) {
            return true;
        }

        return match ($this->nivel_alcada_necessario) {
            NivelAlcadaComercial::Consultor => true,
            NivelAlcadaComercial::Coordenacao => $user->hasAnyRole(['admin', 'coordenador', 'secretaria']),
            NivelAlcadaComercial::Diretoria => $user->hasRole('admin'),
        };
    }

    public function interessado(): BelongsTo
    {
        return $this->belongsTo(Interessado::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por_user_id');
    }

    public function aprovador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovado_por_user_id');
    }
}
