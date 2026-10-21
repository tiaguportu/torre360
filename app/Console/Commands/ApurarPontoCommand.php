<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Funcionario;
use App\Models\PontoConfiguracao;
use App\Services\Ponto\BancoHorasService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Fecha o dia no banco de horas: apura cada funcionário ativo de unidade com ponto ativo e lança a diferença no razão.
 * Idempotente; por padrão apura ontem.
 */
class ApurarPontoCommand extends Command
{
    protected $signature = 'ponto:apurar-dia {--data= : Dia a apurar (Y-m-d). Padrão: ontem}';

    protected $description = 'Apura a jornada do dia e lança o saldo no banco de horas dos funcionários com acordo vigente';

    public function handle(BancoHorasService $banco): int
    {
        $dia = $this->option('data') ? Carbon::parse((string) $this->option('data'))->startOfDay() : today()->subDay();

        if ($dia->gte(today())) {
            $this->error('Só é possível apurar dias já encerrados.');

            return self::FAILURE;
        }

        $unidades = PontoConfiguracao::query()->where('ativo', true)->pluck('unidade_id');
        $lancados = 0;

        Funcionario::query()
            ->whereIn('unidade_id', $unidades)
            ->whereNull('data_desligamento')
            ->orderBy('id')
            ->each(function (Funcionario $funcionario) use ($banco, $dia, &$lancados): void {
                if ($banco->lancarApuracao($funcionario, $dia) !== null) {
                    $lancados++;
                }
            });

        $this->info("Apuração de {$dia->format('d/m/Y')} concluída: {$lancados} lançamento(s) no banco de horas.");

        return self::SUCCESS;
    }
}
