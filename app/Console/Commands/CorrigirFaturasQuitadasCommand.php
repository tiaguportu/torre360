<?php

namespace App\Console\Commands;

use App\Enums\StatusFatura;
use App\Models\Fatura;
use App\Services\BaixaFaturaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CorrigirFaturasQuitadasCommand extends Command
{
    protected $signature = 'faturas:corrigir-quitadas
        {--aplicar : Marca as faturas como Pagas; sem esta opção só lista e nada é alterado}
        {--force : Com --aplicar, não pede confirmação}';

    protected $description = 'Acha as faturas "Parcial" que já foram pagas por inteiro (o saldo era uma fração de centavo por causa do desconto percentual sem arredondar) e as marca como Pagas. Sem --aplicar apenas lista';

    public function handle(BaixaFaturaService $baixas): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $banco = DB::connection()->getDatabaseName();

        $this->line('Banco de dados: '.DB::connection()->getDriverName().' / '.$banco);

        if ($aplicar && ! $this->option('force') && ! $this->confirm("Marcar as faturas quitadas como Pagas no banco {$banco}?", false)) {
            $this->warn('Cancelado: nada foi alterado.');

            return self::FAILURE;
        }

        $this->info($aplicar
            ? 'APLICANDO: as faturas abaixo passam a Pagas.'
            : 'SIMULAÇÃO: nada será gravado. Use --aplicar para gravar.');

        $analisadas = 0;
        $quitadas = [];

        Fatura::query()
            ->where('status', StatusFatura::Parcial->value)
            ->with(['itens', 'transacoes', 'contrato.matricula.pessoa'])
            ->chunkById(200, function ($lote) use ($baixas, $aplicar, &$analisadas, &$quitadas): void {
                foreach ($lote as $fatura) {
                    $analisadas++;

                    // Parcial sem nenhum recebimento não é uma fatura paga: fica para o financeiro revisar.
                    if ($fatura->valor_restante > 0 || $fatura->transacoes->where('tipo', 'entrada')->isEmpty()) {
                        continue;
                    }

                    $quitadas[] = [
                        $fatura->id,
                        $fatura->contrato?->matricula?->pessoa?->nome ?? '-',
                        $fatura->vencimento?->format('d/m/Y') ?? '-',
                        number_format($fatura->valor, 2, ',', '.'),
                        number_format($fatura->valor_pago, 2, ',', '.'),
                    ];

                    if ($aplicar) {
                        $baixas->atualizarStatusFatura($fatura);
                    }
                }
            });

        $this->newLine();

        if ($quitadas === []) {
            $this->info("Faturas Parcial analisadas: {$analisadas}. Nenhuma está quitada.");

            return self::SUCCESS;
        }

        $this->table(['Fatura', 'Aluno', 'Vencimento', 'Valor (R$)', 'Pago (R$)'], $quitadas);
        $this->info("Faturas Parcial analisadas: {$analisadas}. Quitadas: ".count($quitadas).'.');

        if (! $aplicar) {
            $this->comment('Nada foi gravado. Para gravar: php artisan faturas:corrigir-quitadas --aplicar');
        }

        return self::SUCCESS;
    }
}
