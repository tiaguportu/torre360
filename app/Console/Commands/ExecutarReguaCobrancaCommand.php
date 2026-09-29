<?php

namespace App\Console\Commands;

use App\Models\Fatura;
use App\Services\ReguaCobrancaService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExecutarReguaCobrancaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cobranca:executar-regua 
                            {--dry-run : Simula a execução sem disparar notificações reais}
                            {--data= : Data de referência no formato YYYY-MM-DD (padrão: hoje)}
                            {--fatura= : ID de uma fatura específica para disparar lembrete imediato}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Executa a régua de cobrança inteligente para faturas pendentes, no vencimento ou em atraso';

    /**
     * Execute the console command.
     */
    public function handle(ReguaCobrancaService $service): int
    {
        $this->info('Iniciando processamento da Régua de Cobrança Inteligente...');

        $dryRun = (bool) $this->option('dry-run');
        $dataStr = $this->option('data');
        $faturaId = $this->option('fatura');

        $dataReferencia = $dataStr ? Carbon::parse($dataStr) : now();

        if ($dryRun) {
            $this->warn('*** MODO DE SIMULAÇÃO (DRY-RUN) ATIVO - NENHUMA MENSAGEM REAL SERÁ ENVIADA ***');
        }

        // Se uma fatura específica foi solicitada
        if ($faturaId) {
            $fatura = Fatura::find($faturaId);

            if (! $fatura) {
                $this->error("Fatura #{$faturaId} não encontrada.");

                return self::FAILURE;
            }

            $this->info("Disparando lembrete manual para Fatura #{$fatura->id} (Aluno: {$fatura->contrato?->matricula?->pessoa?->nome})...");

            try {
                $resultado = $service->dispararLembreteManual($fatura);
                $this->info("Sucesso! {$resultado['total_enviados']} responsável(is) notificado(s).");

                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->error('Erro ao disparar lembrete: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        // Execução global da régua diária
        $resultado = $service->processarReguaDiaria($dataReferencia, $dryRun);

        $this->table(
            ['Data de Execução', 'Simulação?', 'Regras Ativas', 'Faturas Avaliadas', 'Notificações Geradas'],
            [
                [
                    $resultado['data_execucao'],
                    $resultado['dry_run'] ? 'Sim' : 'Não',
                    $resultado['total_regras'],
                    $resultado['total_faturas_analisadas'],
                    $resultado['total_notificacoes_enviadas'],
                ],
            ]
        );

        if (! empty($resultado['detalhes'])) {
            $linhas = [];
            foreach ($resultado['detalhes'] as $d) {
                $linhas[] = [
                    $d['regra_nome'],
                    $d['dias_offset'].' dia(s)',
                    $d['data_alvo_vencimento'],
                    $d['notificacoes_enviadas'],
                ];
            }

            $this->table(['Régua', 'Offset', 'Data Alvo', 'Notificações'], $linhas);
        }

        $this->info('Processamento da régua de cobrança concluído com sucesso!');

        return self::SUCCESS;
    }
}
