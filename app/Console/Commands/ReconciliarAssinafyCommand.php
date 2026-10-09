<?php

namespace App\Console\Commands;

use App\Models\Contrato;
use App\Services\AssinafyService;
use Illuminate\Console\Command;

class ReconciliarAssinafyCommand extends Command
{
    protected $signature = 'assinafy:reconciliar
        {--contrato= : ID de um contrato específico (ignora os filtros de status)}
        {--limite=100 : Máximo de contratos consultados por execução}
        {--incluir-assinados : Reprocessa também contratos já assinados/certificados para corrigir a data_aceite (use uma vez para reparar dados antigos)}';

    protected $description = 'Consulta o Assinafy e atualiza o status de assinatura (ready, certificating, certificated...) dos contratos ainda em andamento';

    /**
     * Status em que não há mais nada a acompanhar no Assinafy.
     *
     * @var array<int, string>
     */
    private const ENCERRADOS = ['rejected', 'refused', 'canceled', 'expired', 'erro_envio'];

    /**
     * Estados finais da assinatura (certificado gerado ou valores legados).
     *
     * @var array<int, string>
     */
    private const FINAIS = ['certificated', 'signed', 'completed'];

    public function handle(AssinafyService $service): int
    {
        $query = Contrato::query()
            ->whereNotNull('assinafy_id')
            ->where('assinafy_id', '!=', '');

        if ($contratoId = $this->option('contrato')) {
            $query->whereKey($contratoId);
        } else {
            $ignorar = $this->option('incluir-assinados')
                ? self::ENCERRADOS
                : [...self::ENCERRADOS, ...self::FINAIS];

            $query->whereNotIn('assinafy_status', $ignorar);
        }

        $contratos = $query->orderBy('updated_at')->limit(max(1, (int) $this->option('limite')))->get();

        if ($contratos->isEmpty()) {
            $this->info('Nenhum contrato para reconciliar.');

            return self::SUCCESS;
        }

        $linhas = [];
        $falhas = 0;

        foreach ($contratos as $contrato) {
            $antes = (string) $contrato->assinafy_status;
            $aceiteAntes = $contrato->data_aceite?->toDateTimeString();
            $resultado = $service->consultarEAtualizarStatusSignatarios($contrato);

            if (! $resultado['success']) {
                $falhas++;
                $linhas[] = [$contrato->id, $antes, '—', 'falha: '.($resultado['message'] ?? 'erro desconhecido')];

                continue;
            }

            $atualizado = $contrato->fresh();
            $depois = (string) $atualizado->assinafy_status;
            $mudouStatus = $antes !== $depois;
            $mudouAceite = $aceiteAntes !== $atualizado->data_aceite?->toDateTimeString();

            $linhas[] = [$contrato->id, $antes, $depois, match (true) {
                $mudouStatus && $mudouAceite => 'status e data de aceite atualizados',
                $mudouStatus => 'atualizado',
                $mudouAceite => 'data de aceite corrigida',
                default => 'sem mudança',
            }];
        }

        $this->table(['Contrato', 'Status anterior', 'Status atual', 'Resultado'], $linhas);
        $this->info($contratos->count().' contrato(s) consultado(s); '.$falhas.' falha(s).');

        return self::SUCCESS;
    }
}
