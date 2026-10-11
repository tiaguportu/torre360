<?php

namespace App\Services;

use App\Enums\SituacaoFinal;
use App\Enums\StatusRetencaoTarefa;
use App\Filament\Resources\RetencaoTarefas\RetencaoTarefaResource;
use App\Models\Matricula;
use App\Models\Rematricula;
use App\Models\RetencaoTarefa;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Calcula o Risco de Evasão (0-100) de uma matrícula ativa, combinando frequência
 * recente, desempenho acadêmico e inadimplência financeira. Espelha o mesmo padrão
 * do LeadScoreService (CRM), mas para alunos já matriculados em vez de leads.
 * Ver docs/risco_evasao_roadmap.md para a explicação de cada fator e pesos.
 */
class RiscoEvasaoService
{
    /**
     * Quantidade de alertas gerados na execução corrente.
     */
    private static int $totalAlertasExecucao = 0;

    /**
     * IDs das notificações individuais enviadas nesta execução.
     *
     * @var list<string>
     */
    private static array $notificacoesIndividuaisIds = [];

    /**
     * IDs das notificações de resumo enviadas por destinatário.
     *
     * @var array<int, string>
     */
    private static array $resumoNotificacaoIds = [];

    /**
     * Reseta o controle de volume de alertas da execução corrente.
     */
    public static function resetarExecucao(): void
    {
        self::$totalAlertasExecucao = 0;
        self::$notificacoesIndividuaisIds = [];
        self::$resumoNotificacaoIds = [];
    }

    /**
     * Quantos dias olhar para trás ao calcular o percentual de faltas recentes.
     */
    private const JANELA_FREQUENCIA_DIAS = 30;

    /**
     * Calcula o score (0-100) da matrícula a partir do estado atual no banco.
     */
    public static function calcular(Matricula $matricula): int
    {
        return collect(self::detalhar($matricula))->sum('pontos');
    }

    /**
     * Retorna o detalhamento do score por fator, para exibição na ficha da matrícula.
     *
     * @return array<int, array{fator: string, pontos: int, maximo: int}>
     */
    public static function detalhar(Matricula $matricula): array
    {
        $pesos = config('risco_evasao.pesos');

        return [
            ['fator' => 'Frequência', 'pontos' => self::pontosFrequencia($matricula), 'maximo' => $pesos['frequencia']],
            ['fator' => 'Desempenho', 'pontos' => self::pontosDesempenho($matricula), 'maximo' => $pesos['desempenho']],
            ['fator' => 'Inadimplência', 'pontos' => self::pontosInadimplencia($matricula), 'maximo' => $pesos['inadimplencia']],
        ];
    }

    /**
     * Recalcula e persiste o score da matrícula. Faz um update direto na tabela
     * (sem passar pelos eventos do Eloquent), mesmo princípio do LeadScoreService.
     */
    public static function recalcular(Matricula $matricula): int
    {
        $matricula->refresh();

        $score = self::calcular($matricula);

        DB::table('matricula')
            ->where('id', $matricula->id)
            ->update([
                'risco_evasao_score' => $score,
                'risco_evasao_atualizado_em' => now(),
            ]);

        $matricula->risco_evasao_score = $score;

        self::processarPlaybook($matricula, $score);

        return $score;
    }

    /**
     * Executa o Playbook de Retenção Escolar (Lote D9):
     * Quando o risco de evasão atinge ou supera o corte configurável, dispara alerta à coordenação,
     * gera tarefa de acompanhamento e conecta com o fluxo de rematrícula, sem repetir alertas no intervalo configurado.
     */
    public static function processarPlaybook(Matricula $matricula, ?int $score = null): ?RetencaoTarefa
    {
        $score = $score ?? (int) ($matricula->risco_evasao_score ?? self::calcular($matricula));

        $config = config('risco_evasao.playbook', []);
        $corte = (int) ($config['corte_risco'] ?? 60);
        $intervaloDias = (int) ($config['intervalo_dias_alerta'] ?? 15);
        $notificarCoordenacao = (bool) ($config['notificar_coordenacao'] ?? true);
        $criarTarefa = (bool) ($config['criar_tarefa'] ?? true);

        // Se o risco estiver abaixo do corte configurado, não aciona o playbook
        if ($score < $corte) {
            return null;
        }

        // Verifica se a matrícula já foi alertada recentemente dentro da janela de cooldown
        if ($matricula->risco_evasao_alerta_em !== null) {
            $ultimoAlerta = Carbon::parse($matricula->risco_evasao_alerta_em);
            if ($ultimoAlerta->isAfter(now()->subDays($intervaloDias))) {
                return null;
            }
        }

        // Localiza se o aluno possui intenção ou registro de rematrícula ativo
        $rematricula = Rematricula::query()
            ->where('matricula_origem_id', $matricula->id)
            ->latest('id')
            ->first();

        $fatores = self::detalhar($matricula);
        $aluno = $matricula->pessoa;
        $nomeAluno = $aluno?->nome ?? 'Aluno(a)';

        $tarefa = null;
        if ($criarTarefa) {
            $descricao = "Identificado score de risco de evasão de {$score}% para {$nomeAluno}.\n"
                ."Fatores detalhados:\n"
                ."- Frequência: {$fatores[0]['pontos']} pts (máx: {$fatores[0]['maximo']})\n"
                ."- Desempenho: {$fatores[1]['pontos']} pts (máx: {$fatores[1]['maximo']})\n"
                ."- Inadimplência: {$fatores[2]['pontos']} pts (máx: {$fatores[2]['maximo']})\n";

            if ($rematricula) {
                $statusRematricula = $rematricula->status?->getLabel() ?? (string) $rematricula->status;
                $descricao .= "\nFluxo de Rematrícula: Campanha \"{$rematricula->periodoRematricula?->nome}\" no status \"{$statusRematricula}\".";
            } else {
                $descricao .= "\nFluxo de Rematrícula: Nenhuma intenção de rematrícula registrada até o momento.";
            }

            $tarefa = RetencaoTarefa::create([
                'matricula_id' => $matricula->id,
                'aluno_pessoa_id' => $matricula->pessoa_id,
                'rematricula_id' => $rematricula?->id,
                'score_risco' => $score,
                'status' => StatusRetencaoTarefa::Pendente,
                'prioridade' => $score >= 80 ? 'critica' : 'alta',
                'titulo' => "Acompanhamento de Retenção: {$nomeAluno} (Risco {$score}%)",
                'fatores_resumo' => $fatores,
                'descricao' => $descricao,
                'data_limite' => now()->addDays(5)->toDateString(),
                'alertado_coordenacao_em' => now(),
            ]);
        }

        // Atualiza a marcação temporal de alerta para impedir repetições durante o intervalo configurado
        DB::table('matricula')
            ->where('id', $matricula->id)
            ->update(['risco_evasao_alerta_em' => now()]);

        $matricula->risco_evasao_alerta_em = now();

        if ($notificarCoordenacao) {
            self::notificarCoordenacao($matricula, $score, $tarefa);
        }

        return $tarefa;
    }

    /**
     * Envia alerta interno à Coordenação e Gestão Escolar sobre aluno com risco alto de evasão.
     */
    public static function notificarCoordenacao(Matricula $matricula, int $score, ?RetencaoTarefa $tarefa = null): void
    {
        $destinatarios = User::query()
            ->ativos()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['coordenador', 'admin', 'super_admin']))
            ->get();

        if ($destinatarios->isEmpty()) {
            Log::warning('Nenhum usuário da coordenação ou gestão escolar ativo foi encontrado para receber o alerta de evasão.');

            return;
        }

        self::$totalAlertasExecucao++;
        $maxIndividuais = (int) config('risco_evasao.playbook.max_notificacoes_individuais', 5);

        // Se ultrapassou o limite máximo de notificações individuais, consolida em 1 resumo por destinatário
        if (self::$totalAlertasExecucao > $maxIndividuais) {
            $destinatariosIds = $destinatarios->pluck('id')->all();

            // Remove alertas individuais anteriores desta execução para os destinatários
            DatabaseNotification::query()
                ->whereIn('notifiable_id', $destinatariosIds)
                ->where(function ($q) {
                    $q->where('data', 'like', '%Alerta de Risco de Evas%')->orWhere('data', 'like', '%retencao%');
                })
                ->delete();

            $url = '/admin/retencao-tarefas';
            try {
                $url = RetencaoTarefaResource::getUrl('index');
            } catch (\Throwable) {
                // fallback
            }

            $total = self::$totalAlertasExecucao;

            foreach ($destinatarios as $destinatario) {
                $resumoNotif = Notification::make()
                    ->title("⚠️ Alerta de Risco de Evasão: {$total} alunos em risco")
                    ->body("Foram identificados {$total} alunos com score alto de risco de evasão nesta execução. Acesse o Playbook de Retenção para gerenciar as tarefas.")
                    ->icon('heroicon-o-shield-exclamation')
                    ->danger()
                    ->actions([
                        Action::make('visualizar_tarefas')
                            ->label('Ver Playbook de Retenção')
                            ->url($url),
                    ]);

                $resumoNotif->sendToDatabase($destinatario);
            }

            return;
        }

        $alunoNome = $matricula->pessoa?->nome ?? 'Aluno';
        $turmaNome = $matricula->turma?->nome ?? 'Turma';

        foreach ($destinatarios as $destinatario) {
            $notification = Notification::make()
                ->title("⚠️ Alerta de Risco de Evasão: {$alunoNome} ({$score}%)")
                ->body("A matrícula de {$alunoNome} ({$turmaNome}) atingiu score de risco de evasão de {$score}%. Foi gerada uma ação de retenção no Playbook.")
                ->icon('heroicon-o-shield-exclamation')
                ->danger();

            if ($tarefa) {
                $notification->actions([
                    Action::make('visualizar_tarefa')
                        ->label('Ver Acompanhamento')
                        ->url("/admin/retencao-tarefas/{$tarefa->id}/edit"),
                ]);
            }

            $notification->sendToDatabase($destinatario);
        }
    }

    /**
    public static function cor(?int $score): string
    {
        $faixas = config('risco_evasao.faixas_cor');

        return match (true) {
            $score === null => 'gray',
            $score >= $faixas['alto'] => 'danger',
            $score >= $faixas['moderado'] => 'warning',
            default => 'success',
        };
    }

    /**
     * % de faltas nos últimos 30 dias, sobre as aulas com frequência lançada no
     * período (sem aulas registradas, não há o que avaliar: 0 pontos).
     */
    private static function pontosFrequencia(Matricula $matricula): int
    {
        $desde = now()->subDays(self::JANELA_FREQUENCIA_DIAS)->toDateString();

        $registros = $matricula->frequenciaEscolars()
            ->whereHas('cronogramaAula', fn ($q) => $q->where('data', '>=', $desde))
            ->get();

        $total = $registros->count();

        if ($total === 0) {
            return 0;
        }

        $percentualFaltas = ($registros->where('situacao', 'ausente')->count() / $total) * 100;

        foreach (config('risco_evasao.frequencia') as $faixa) {
            if ($percentualFaltas >= $faixa['minimo']) {
                return $faixa['pontos'];
            }
        }

        return 0;
    }

    /**
     * Pior situação final entre as disciplinas do período letivo mais recente que já
     * teve fechamento de ciclo registrado para esta matrícula.
     */
    private static function pontosDesempenho(Matricula $matricula): int
    {
        $situacoes = $matricula->situacoesFinais;

        if ($situacoes->isEmpty()) {
            return 0;
        }

        $ultimoPeriodoId = $situacoes->max('periodo_letivo_id');
        $doUltimoPeriodo = $situacoes->where('periodo_letivo_id', $ultimoPeriodoId);

        $tabela = config('risco_evasao.desempenho');

        if ($doUltimoPeriodo->contains('situacao', SituacaoFinal::REPROVADO)) {
            return $tabela['reprovado'];
        }

        if ($doUltimoPeriodo->contains('situacao', SituacaoFinal::RECUPERACAO)) {
            return $tabela['recuperacao'];
        }

        return $tabela['aprovado'];
    }

    /**
     * Reaproveita Matricula::hasDebitosVencidos() (Onda 7) em vez de reimplementar a
     * lógica de atraso financeiro.
     */
    private static function pontosInadimplencia(Matricula $matricula): int
    {
        return $matricula->hasDebitosVencidos() ? config('risco_evasao.pesos.inadimplencia') : 0;
    }
}
