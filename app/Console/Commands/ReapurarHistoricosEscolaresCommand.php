<?php

namespace App\Console\Commands;

use App\Models\HistoricoEscolar;
use App\Services\HistoricoEscolarService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReapurarHistoricosEscolaresCommand extends Command
{
    protected $signature = 'historico:reapurar-resultados
        {--historico=* : Reapura só estes históricos (id); sem a opção, todos os que têm ano interno}
        {--aplicar : Grava a nova apuração; sem esta opção só simula e nada é alterado}
        {--force : Com --aplicar, não pede confirmação}';

    protected $description = 'Reapura o resultado dos anos internos dos históricos escolares (nota de corte do período, exame final, dependência e reprovação por faltas). Sem --aplicar apenas simula';

    public function handle(HistoricoEscolarService $service): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $banco = DB::connection()->getDatabaseName();

        $this->line('Banco de dados: '.DB::connection()->getDriverName().' / '.$banco);

        if ($aplicar && ! $this->option('force') && ! $this->confirm("Gravar a nova apuração no banco {$banco}?", false)) {
            $this->warn('Cancelado: nada foi alterado.');

            return self::FAILURE;
        }

        $this->info($aplicar
            ? 'APLICANDO a nova apuração.'
            : 'SIMULAÇÃO: nada será gravado. Use --aplicar para gravar.');

        $ids = array_values(array_filter(array_map('intval', (array) $this->option('historico'))));

        $linhasMudanca = [];
        $ignorados = [];
        $falhas = [];
        $historicos = 0;
        $anos = 0;
        $porResultado = [];

        HistoricoEscolar::query()
            ->with('pessoa')
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->whereHas('anos', fn ($q) => $q->where('tipo', 'interno')->whereNotNull('matricula_id'))
            ->chunkById(100, function ($lote) use ($service, $aplicar, &$linhasMudanca, &$ignorados, &$falhas, &$historicos, &$anos, &$porResultado): void {
                foreach ($lote as $historico) {
                    $historicos++;

                    // Cada histórico é atômico. Na simulação a apuração roda de verdade e é desfeita, então o que
                    // aparece na tela é exatamente o que --aplicar gravaria.
                    DB::beginTransaction();

                    try {
                        $resultado = $service->reapurarAnosInternos($historico);
                        $aplicar ? DB::commit() : DB::rollBack();
                    } catch (\Throwable $e) {
                        DB::rollBack();
                        $falhas[] = "Histórico #{$historico->id}: {$e->getMessage()}";

                        continue;
                    }

                    foreach ($resultado as $linha) {
                        $ano = $linha['ano'];

                        if ($linha['ignorado'] !== null) {
                            $ignorados[] = "Histórico #{$historico->id} ({$historico->pessoa?->nome}), ano {$ano->ano_letivo}: {$linha['ignorado']}";

                            continue;
                        }

                        $anos++;
                        $porResultado[$linha['depois']] = ($porResultado[$linha['depois']] ?? 0) + 1;

                        $mudou = $linha['antes'] !== $linha['depois']
                            || $linha['disciplinas_alteradas'] > 0
                            || abs(($linha['frequencia_antes'] ?? 0) - ($linha['frequencia_depois'] ?? 0)) >= 0.005;

                        if ($mudou) {
                            $linhasMudanca[] = [
                                $historico->id,
                                $historico->pessoa?->nome ?? '-',
                                $ano->ano_letivo,
                                $ano->serie_nome,
                                number_format((float) $linha['frequencia_antes'], 2, ',', '.').'% → '.number_format((float) $linha['frequencia_depois'], 2, ',', '.').'%',
                                ($linha['antes'] ?? '-').' → '.$linha['depois'],
                                $linha['disciplinas_alteradas'],
                            ];
                        }
                    }
                }
            });

        if ($historicos === 0) {
            $this->info('Nenhum histórico com ano interno encontrado.');

            return self::SUCCESS;
        }

        if ($linhasMudanca !== []) {
            $this->table(['Histórico', 'Aluno', 'Ano', 'Série', 'Frequência', 'Resultado do ano', 'Disciplinas alteradas'], $linhasMudanca);
        }

        $this->newLine();
        $this->info("Históricos analisados: {$historicos}. Anos internos reapurados: {$anos}. Anos com mudança: ".count($linhasMudanca).'.');

        ksort($porResultado);
        foreach ($porResultado as $resultado => $quantidade) {
            $this->line("  {$resultado}: {$quantidade}");
        }

        if ($ignorados !== []) {
            $this->newLine();
            $this->warn(count($ignorados).' ano(s) ignorado(s) para revisão manual:');
            foreach ($ignorados as $texto) {
                $this->line("  - {$texto}");
            }
        }

        if ($falhas !== []) {
            $this->newLine();
            $this->error(count($falhas).' histórico(s) com falha (nada foi gravado para eles):');
            foreach ($falhas as $texto) {
                $this->line("  - {$texto}");
            }
        }

        if (! $aplicar && $linhasMudanca !== []) {
            $this->newLine();
            $this->comment('Nada foi gravado. Para gravar: php artisan historico:reapurar-resultados --aplicar');
        }

        return $falhas === [] ? self::SUCCESS : self::FAILURE;
    }
}
