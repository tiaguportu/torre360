<?php

namespace App\Console\Commands;

use App\Services\ReguaFollowUpService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExecutarReguaFollowUpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:executar-regua-follow-up
                            {--dry-run : Simula a execução sem disparar e-mails ou notificações reais}
                            {--data= : Data de referência no formato YYYY-MM-DD (padrão: hoje)}
                            {--ignorar-horario : Processa todas as regras agora, sem esperar o horário de disparo de cada uma}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Executa a régua de automação de follow-up do CRM (triggers de novos leads, visitas à escola e estagnação)';

    /**
     * Execute the console command.
     */
    public function handle(ReguaFollowUpService $service): int
    {
        $this->info('Iniciando processamento da Régua de Follow-up do CRM...');

        $dryRun = (bool) $this->option('dry-run');
        $dataStr = $this->option('data');

        $dataReferencia = $dataStr ? Carbon::parse($dataStr) : now();

        // O agendador roda de hora em hora: cada regra sai na primeira execução a partir do seu horário de disparo.
        // Reprocessar uma data passada (`--data`) ou pedir `--ignorar-horario` ignora o horário.
        $agora = $dataStr || $this->option('ignorar-horario') ? null : now();

        if ($dryRun) {
            $this->warn('*** MODO DE SIMULAÇÃO (DRY-RUN) ATIVO - NENHUMA MENSAGEM REAL SERÁ DISPARADA ***');
        }

        $resultado = $service->processarReguaDiaria($dataReferencia, $dryRun, $agora);

        $this->table(
            ['Data de Execução', 'Simulação?', 'Regras Ativas', 'Aguardando Horário', 'Candidatos Avaliados', 'Notificações Geradas', 'Adiadas (limite diário)'],
            [
                [
                    $resultado['data_execucao'],
                    $resultado['dry_run'] ? 'Sim' : 'Não',
                    $resultado['total_regras'],
                    $resultado['total_regras_aguardando_horario'],
                    $resultado['total_candidatos_analisados'],
                    $resultado['total_notificacoes_enviadas'],
                    $resultado['total_adiadas_limite'],
                ],
            ]
        );

        if (! empty($resultado['detalhes'])) {
            $linhas = [];
            foreach ($resultado['detalhes'] as $d) {
                $linhas[] = [
                    $d['regra_nome'],
                    $d['gatilho'],
                    $d['dias_offset'].' dia(s)',
                    $d['canal'],
                    $d['candidatos_encontrados'],
                    $d['notificacoes_enviadas'],
                ];
            }

            $this->table(['Régua', 'Gatilho', 'Offset', 'Canal', 'Candidatos', 'Notificações'], $linhas);
        }

        $this->info('Processamento da Régua de Follow-up do CRM concluído com sucesso!');

        return self::SUCCESS;
    }
}
