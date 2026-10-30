<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ResumoFaltasDiarioService;
use Illuminate\Console\Command;

/**
 * Fim do dia: manda a cada responsável um único aviso com as faltas pendentes dos alunos dele, no lugar de um e-mail
 * por aula. Sem pendência não envia nada. `--dry-run` só mostra quem receberia o quê, sem reservar nem enviar.
 */
class EnviarResumoFaltasCommand extends Command
{
    protected $signature = 'frequencia:enviar-resumo-diario {--dry-run : Mostra o que seria enviado, sem enviar nem consumir as faltas pendentes}';

    protected $description = 'Envia aos responsáveis o resumo das faltas lançadas, em um único aviso por responsável';

    public function handle(ResumoFaltasDiarioService $servico): int
    {
        $simular = (bool) $this->option('dry-run');
        $resultado = $servico->enviar($simular);

        if ($resultado['faltas'] === 0 && $resultado['descartadas'] === 0) {
            $this->info('Nenhuma falta pendente de aviso.');

            return self::SUCCESS;
        }

        if ($simular) {
            foreach ($resultado['destinatarios'] as $destinatario) {
                $faltas = array_sum(array_map(fn (array $aluno) => count($aluno['faltas']), $destinatario['alunos']));
                $alunos = implode(', ', array_column($destinatario['alunos'], 'nome'));

                $this->line("{$destinatario['user']->email}: {$faltas} falta(s) de {$alunos}");
            }
        }

        $acao = $simular ? 'Simulação' : 'Resumo enviado';
        $this->info("{$acao}: {$resultado['faltas']} falta(s) de {$resultado['alunos']} aluno(s) para {$resultado['responsaveis']} responsável(is); {$resultado['descartadas']} sem responsável com usuário.");

        if ($resultado['falhas'] > 0) {
            $this->error("{$resultado['falhas']} responsável(is) não puderam ser avisados; as faltas voltaram para a fila e saem no próximo resumo.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
