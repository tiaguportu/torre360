<?php

namespace App\Console\Commands;

use App\Enums\StatusFatura;
use App\Models\Contrato;
use App\Services\GeracaoFaturasContratoService;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class AuditarFaturasContratosCommand extends Command
{
    protected $signature = 'contratos:auditar-faturas
        {--contrato= : ID de um contrato específico para auditar}
        {--divergentes-apenas : Exibe apenas contratos que possuam divergência de faturamento}
        {--corrigir : Regenera faturas automaticamente para contratos divergentes não assinados}
        {--limite=100 : Máximo de contratos auditados por execução}';

    protected $description = 'Audita a conformidade entre o valor_total dos contratos e o saldo das faturas ativas emitidas, alertando e registrando divergências no ActivityLog.';

    public function handle(): int
    {
        $query = Contrato::query()
            ->with(['matricula.pessoa', 'faturas' => fn ($q) => $q->where('status', '!=', 'cancelado')->with('itens')])
            ->whereHas('faturas', fn ($q) => $q->where('status', '!=', 'cancelado'));

        if ($contratoId = $this->option('contrato')) {
            $query->whereKey($contratoId);
        }

        $contratos = $query->orderBy('id', 'desc')->limit(max(1, (int) $this->option('limite')))->get();

        if ($contratos->isEmpty()) {
            $this->info('Nenhum contrato com faturas ativas encontrado para auditoria.');

            return self::SUCCESS;
        }

        $linhas = [];
        $totalDivergentes = 0;
        $totalAlinhados = 0;
        $corrigidos = 0;

        foreach ($contratos as $contrato) {
            $totalFaturas = $contrato->totalFaturasEmitidas();
            $valorContrato = (float) $contrato->valor_total;
            $diferenca = round($valorContrato - $totalFaturas, 2);
            $temDivergencia = abs($diferenca) > 0.01;

            if ($temDivergencia) {
                $totalDivergentes++;

                $jaLogado = Activity::query()
                    ->where('log_name', 'contrato')
                    ->where('subject_type', Contrato::class)
                    ->where('subject_id', $contrato->id)
                    ->where('properties->tipo', 'divergencia_faturamento')
                    ->where('properties->saldo_faturas_emitidas', $totalFaturas)
                    ->where('properties->valor_contrato_novo', $valorContrato)
                    ->exists();

                if (! $jaLogado) {
                    activity('contrato')
                        ->performedOn($contrato)
                        ->withProperties([
                            'tipo' => 'divergencia_faturamento',
                            'origem' => 'auditoria_cli',
                            'valor_contrato_anterior' => $valorContrato,
                            'valor_contrato_novo' => $valorContrato,
                            'saldo_faturas_emitidas' => $totalFaturas,
                            'diferenca' => $diferenca,
                            'sugestao' => 'Regenerar Faturas',
                            'quantidade_faturas' => $contrato->faturas->where('status', '!=', StatusFatura::Cancelado)->count(),
                        ])
                        ->log(sprintf(
                            'Auditoria identificou divergência: O valor do contrato (#%d) é R$ %s, divergindo do saldo das faturas emitidas (R$ %s, diferença de R$ %s). Sugere-se ao operador rodar "Regenerar Faturas".',
                            $contrato->id,
                            number_format($valorContrato, 2, ',', '.'),
                            number_format($totalFaturas, 2, ',', '.'),
                            number_format(abs($diferenca), 2, ',', '.')
                        ));
                }

                $statusAcao = 'DIVERGENTE';

                if ($this->option('corrigir')) {
                    if ($contrato->estaAssinado()) {
                        $statusAcao = 'DIVERGENTE (ignorado: contrato já assinado)';
                    } else {
                        try {
                            $qtdParcelas = max(1, $contrato->faturas->where('status', '!=', StatusFatura::Cancelado)->count());
                            app(GeracaoFaturasContratoService::class)->gerar($contrato, $qtdParcelas, 0);
                            $corrigidos++;
                            $statusAcao = "CORRIGIDO ({$qtdParcelas} parcelas regeneradas)";
                        } catch (\Throwable $e) {
                            $statusAcao = 'ERRO AO CORRIGIR: '.$e->getMessage();
                        }
                    }
                }
            } else {
                $totalAlinhados++;
                $statusAcao = 'ALINHADO';
            }

            if (! $this->option('divergentes-apenas') || $temDivergencia) {
                $nomeAluno = $contrato->matricula?->pessoa?->nome ?? 'Aluno N/A';
                $sinal = $diferenca > 0 ? '+' : '';

                $linhas[] = [
                    $contrato->id,
                    mb_strimwidth($nomeAluno, 0, 25, '...'),
                    'R$ '.number_format($valorContrato, 2, ',', '.'),
                    'R$ '.number_format($totalFaturas, 2, ',', '.'),
                    $temDivergencia ? "{$sinal}R$ ".number_format($diferenca, 2, ',', '.') : 'R$ 0,00',
                    $statusAcao,
                ];
            }
        }

        $this->table(
            ['Contrato #', 'Aluno', 'Valor Contrato', 'Total Faturas', 'Diferença', 'Situação'],
            $linhas
        );

        $this->newLine();
        $this->info(sprintf(
            'Auditoria finalizada: %d contrato(s) avaliado(s) | %d alinhado(s) | %d divergente(s)%s.',
            $contratos->count(),
            $totalAlinhados,
            $totalDivergentes,
            $this->option('corrigir') ? " | {$corrigidos} corrigido(s)" : ''
        ));

        if ($totalDivergentes > 0 && ! $this->option('corrigir')) {
            $this->warn('Dica: Use a flag --corrigir para regenerar automaticamente as faturas dos contratos divergentes ainda não assinados, ou acione "Regenerar Faturas" na edição de cada contrato.');
        }

        return self::SUCCESS;
    }
}
