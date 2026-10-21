<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModoPonto;
use App\Exceptions\PontoException;
use App\Services\Ponto\ConformidadePontoService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Configuração do ponto de uma unidade (um estabelecimento): modo de operação, requisitos legais declarados, tolerâncias
 * e o sequenciador de registros (NSR) com a ponta da cadeia de hash.
 */
class PontoConfiguracao extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'ponto_configuracoes';

    /**
     * Mesmos padrões da migration, para que o model já nasça coerente (o modo, por exemplo, é lido antes de o banco
     * preencher o padrão na gravação).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'modo' => 'controle_interno',
        'ativo' => true,
        'exige_hora_legal' => true,
        'tolerancia_diaria_minutos' => 10,
        'ultimo_nsr' => 0,
    ];

    protected $fillable = [
        'unidade_id',
        'modo',
        'ativo',
        'registro_inpi',
        'atestado_tecnico_path',
        'atestado_tecnico_data',
        'atestado_assinatura_conferida',
        'acordo_coletivo_referencia',
        'acordo_coletivo_vigencia_fim',
        'exige_hora_legal',
        'tolerancia_diaria_minutos',
        'ultimo_nsr',
        'hash_ultimo_registro',
    ];

    /** Campos cuja alteração muda a conformidade declarada (o sequenciador não entra: ele muda a cada marcação). */
    private const CAMPOS_DE_CONFORMIDADE = [
        'modo', 'registro_inpi', 'atestado_tecnico_path', 'atestado_tecnico_data',
        'atestado_assinatura_conferida', 'acordo_coletivo_referencia', 'acordo_coletivo_vigencia_fim',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'modo' => ModoPonto::class,
            'ativo' => 'boolean',
            'atestado_tecnico_data' => 'date',
            'atestado_assinatura_conferida' => 'boolean',
            'acordo_coletivo_vigencia_fim' => 'date',
            'exige_hora_legal' => 'boolean',
            'ultimo_nsr' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Ninguém declara um modo oficial (REP-A/REP-P) sem cumprir os requisitos: vale para o formulário, o console e
        // qualquer outro caminho. Marcar o ponto nunca passa por aqui, porque o sequenciador não é campo de conformidade.
        static::saving(function (PontoConfiguracao $config): void {
            if (! $config->modo->ehOficial() || ! $config->isDirty(self::CAMPOS_DE_CONFORMIDADE)) {
                return;
            }

            $pendencias = app(ConformidadePontoService::class)->pendencias($config);

            if ($pendencias !== []) {
                throw new PontoException(
                    'O modo "'.$config->modo->getLabel().'" ainda não pode ser ativado: '
                    .collect($pendencias)->pluck('titulo')->implode('; ').'.',
                );
            }
        });
    }

    /**
     * Só o que muda a configuração é registrado; o sequenciador (NSR e hash) não, para não encher o log a cada marcação.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['modo', 'ativo', 'registro_inpi', 'atestado_tecnico_data', 'atestado_assinatura_conferida', 'acordo_coletivo_referencia', 'acordo_coletivo_vigencia_fim', 'exige_hora_legal', 'tolerancia_diaria_minutos'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('ponto')
            ->setDescriptionForEvent(fn (string $evento): string => "Configuração do ponto: {$evento}");
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function registros(): HasMany
    {
        return $this->hasMany(PontoRegistro::class, 'unidade_id', 'unidade_id');
    }
}
