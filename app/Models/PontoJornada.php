<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jornada contratada do funcionário: minutos previstos por dia da semana, dentro de uma vigência.
 */
class PontoJornada extends Model
{
    use HasFactory;

    protected $table = 'ponto_jornadas';

    /** Ordem de `dayOfWeekIso` (1 = segunda ... 7 = domingo). */
    public const DIAS = [1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab', 7 => 'dom'];

    protected $fillable = [
        'funcionario_id',
        'vigencia_inicio',
        'vigencia_fim',
        'minutos_seg',
        'minutos_ter',
        'minutos_qua',
        'minutos_qui',
        'minutos_sex',
        'minutos_sab',
        'minutos_dom',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
        ];
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function minutosPrevistosEm(CarbonInterface $data): int
    {
        $coluna = 'minutos_'.self::DIAS[$data->dayOfWeekIso];

        return (int) $this->{$coluna};
    }

    public function minutosSemanais(): int
    {
        return (int) collect(self::DIAS)->sum(fn (string $dia): int => (int) $this->{"minutos_{$dia}"});
    }

    public function vigenteEm(CarbonInterface $data): bool
    {
        $dia = $data->toDateString();

        return $this->vigencia_inicio->toDateString() <= $dia
            && ($this->vigencia_fim === null || $this->vigencia_fim->toDateString() >= $dia);
    }
}
