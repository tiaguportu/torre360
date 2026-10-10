<?php

namespace App\Console\Commands;

use App\Models\Interessado;
use Illuminate\Console\Command;

/**
 * Retenção do rascunho de pré-matrícula (LGPD, art. 15 e 16: dado pessoal é eliminado quando deixa de ser necessário).
 *
 * A família preenche CPF, endereço e dados dos alunos no convite; ao virar matrícula o rascunho é apagado
 * (`InteressadoMatriculaService`), mas leads que desistiram ou sumiram o mantinham para sempre. Aqui some o que
 * está parado há mais de `crm.lgpd.retencao_rascunho_dias` dias e não virou matrícula. O lead e a linha do tempo
 * continuam; só o rascunho (e o aceite, que fica na pessoa) é que muda.
 */
class ExpurgarRascunhosPreMatriculaCommand extends Command
{
    protected $signature = 'crm:expurgar-rascunhos-pre-matricula
                            {--dry-run : Mostra quantos rascunhos seriam apagados, sem apagar}';

    protected $description = 'Apaga rascunhos de pré-matrícula (CPF, endereço, dados da família) parados além do prazo de retenção da LGPD';

    public function handle(): int
    {
        $dias = max(1, (int) config('crm.lgpd.retencao_rascunho_dias', 90));
        $limite = now()->subDays($dias);

        $consulta = Interessado::query()
            ->whereNotNull('dados_pre_matricula')
            ->whereNull('data_conversao')
            // Rascunhos sem data (anteriores à coluna e não migrados) contam pela última atualização do lead.
            ->where(fn ($q) => $q
                ->where('dados_pre_matricula_em', '<', $limite)
                ->orWhere(fn ($sem) => $sem->whereNull('dados_pre_matricula_em')->where('updated_at', '<', $limite)))
            // Candidato em andamento (a equipe ou a família falaram nesse período) mantém o rascunho: só some o abandonado.
            // Registros automáticos (e-mails da régua) não contam como atividade.
            ->whereDoesntHave('interacoes', fn ($q) => $q->where('data_contato', '>=', $limite));

        $total = (clone $consulta)->count();

        if ($total === 0) {
            $this->info("Nenhum rascunho de pré-matrícula parado há mais de {$dias} dias.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("{$total} rascunho(s) de pré-matrícula seriam apagados (parados há mais de {$dias} dias).");

            return self::SUCCESS;
        }

        // `update()` em lote não passa pelo cast: grava NULL direto, sem tocar em `updated_at` do lead (não é atividade da equipe).
        $consulta->toBase()->update(['dados_pre_matricula' => null, 'dados_pre_matricula_em' => null]);

        $this->info("{$total} rascunho(s) de pré-matrícula apagado(s) (parados há mais de {$dias} dias).");

        return self::SUCCESS;
    }
}
