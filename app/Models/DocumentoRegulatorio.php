<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DocumentoRegulatorio extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'documentos_regulatorios';

    public const TIPOS = [
        'alvara_funcionamento' => 'Alvará de Funcionamento',
        'avcb_clcb' => 'AVCB / CLCB (Bombeiros)',
        'licenca_sanitaria' => 'Licença Sanitária',
        'credenciamento_mec_cee' => 'Autorização / Credenciamento (MEC/CEE)',
        'laudo_spda' => 'Laudo Elétrico / SPDA (Pára-raios)',
        'higienizacao_reservatorio' => 'Higienização de Reservatório d\'Água',
        'dedetizacao' => 'Dedetização / Controle de Pragas',
        'outro' => 'Outro Documento Regulatório',
    ];

    public const STATUS = [
        'vigente' => 'Vigente',
        'alerta_vencimento' => 'Próximo ao Vencimento',
        'vencido' => 'Vencido',
        'em_renovacao' => 'Em Renovação',
        'revogado' => 'Revogado / Cancelado',
        'indeterminado' => 'Prazo Indeterminado',
    ];

    protected $fillable = [
        'unidade_id',
        'tipo_documento',
        'nome',
        'numero_registro',
        'orgao_emissor',
        'data_emissao',
        'data_validade',
        'dias_antecedencia_alerta',
        'responsavel_renovacao',
        'arquivo_path',
        'status',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'data_emissao' => 'date',
            'data_validade' => 'date',
            'dias_antecedencia_alerta' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'unidade_id',
                'tipo_documento',
                'nome',
                'numero_registro',
                'orgao_emissor',
                'data_emissao',
                'data_validade',
                'dias_antecedencia_alerta',
                'responsavel_renovacao',
                'status',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    /**
     * Calcula o status técnico baseado na data de validade.
     */
    public function calcularStatus(): string
    {
        if (in_array($this->status, ['revogado', 'em_renovacao'], true)) {
            return $this->status;
        }

        if (! $this->data_validade) {
            return 'indeterminado';
        }

        $hoje = Carbon::today();
        $validade = Carbon::parse($this->data_validade)->startOfDay();

        if ($validade->lt($hoje)) {
            return 'vencido';
        }

        $diasRestantes = $hoje->diffInDays($validade, false);
        $limiteAlerta = $this->dias_antecedencia_alerta ?: 30;

        if ($diasRestantes <= $limiteAlerta) {
            return 'alerta_vencimento';
        }

        return 'vigente';
    }

    /**
     * Retorna a quantidade de dias para o vencimento. Negativo se vencido.
     */
    public function getDiasParaVencerAttribute(): ?int
    {
        if (! $this->data_validade) {
            return null;
        }

        $hoje = Carbon::today();
        $validade = Carbon::parse($this->data_validade)->startOfDay();

        return (int) $hoje->diffInDays($validade, false);
    }
}
