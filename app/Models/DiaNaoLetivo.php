<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiaNaoLetivo extends Model
{
    protected $table = 'dia_nao_letivo';

    protected $guarded = [];

    public function periodoLetivo(): BelongsTo
    {
        return $this->belongsTo(PeriodoLetivo::class);
    }

    public static function getFeriadoNacional(Carbon $date): ?string
    {
        $year = $date->year;
        $dayMonth = $date->format('d/m');

        // Feriados fixos
        $fixed = [
            '01/01' => 'Confraternização Universal',
            '21/04' => 'Tiradentes',
            '01/05' => 'Dia do Trabalhador',
            '07/09' => 'Independência do Brasil',
            '12/10' => 'Nossa Senhora Aparecida',
            '02/11' => 'Finados',
            '15/11' => 'Proclamação da República',
            '20/11' => 'Dia da Consciência Negra',
            '25/12' => 'Natal',
        ];

        if (array_key_exists($dayMonth, $fixed)) {
            return $fixed[$dayMonth];
        }

        // Feriados móveis baseados na páscoa
        $easter = \Illuminate\Support\Carbon::parse(date('Y-m-d', easter_date($year)));

        // Sexta-feira Santa (2 dias antes)
        if ($date->isSameDay($easter->copy()->subDays(2))) {
            return 'Sexta-Feira Santa';
        }

        return null;
    }
}
