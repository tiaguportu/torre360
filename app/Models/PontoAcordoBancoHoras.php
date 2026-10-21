<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoAcordoBancoHoras;
use App\Exceptions\PontoException;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Acordo de compensação de jornada (banco de horas) do funcionário. Sem acordo vigente, a hora excedente é hora extra
 * a pagar e não entra no banco. O prazo de compensação nunca passa do máximo do tipo do acordo (CLT, art. 59).
 */
class PontoAcordoBancoHoras extends Model
{
    use HasFactory;

    protected $table = 'ponto_acordos_banco_horas';

    protected $fillable = [
        'funcionario_id',
        'tipo',
        'vigencia_inicio',
        'vigencia_fim',
        'prazo_compensacao_meses',
        'documento_referencia',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoAcordoBancoHoras::class,
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
            'prazo_compensacao_meses' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PontoAcordoBancoHoras $acordo): void {
            $maximo = $acordo->tipo->prazoMaximoMeses();

            if ($acordo->tipo === TipoAcordoBancoHoras::MesmoMes) {
                $acordo->prazo_compensacao_meses = 0;
            }

            if ((int) $acordo->prazo_compensacao_meses > $maximo) {
                throw new PontoException("Este tipo de acordo permite compensar em até {$maximo} meses (CLT, art. 59).");
            }

            if ($acordo->tipo !== TipoAcordoBancoHoras::MesmoMes && (int) $acordo->prazo_compensacao_meses < 1) {
                throw new PontoException('Informe o prazo de compensação, em meses.');
            }

            if (blank($acordo->documento_referencia)) {
                throw new PontoException('Informe a referência do documento do acordo (o acordo individual precisa ser escrito).');
            }
        });
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function vigenteEm(CarbonInterface $data): bool
    {
        $dia = $data->toDateString();

        return $this->vigencia_inicio->toDateString() <= $dia
            && ($this->vigencia_fim === null || $this->vigencia_fim->toDateString() >= $dia);
    }

    /**
     * Último dia em que um crédito gerado em `$data` pode ser compensado.
     */
    public function vencimentoDoCredito(CarbonInterface $data): string
    {
        $base = $data->copy()->startOfDay();

        if ($this->tipo === TipoAcordoBancoHoras::MesmoMes) {
            return $base->endOfMonth()->toDateString();
        }

        return $base->addMonthsNoOverflow((int) $this->prazo_compensacao_meses)->toDateString();
    }
}
