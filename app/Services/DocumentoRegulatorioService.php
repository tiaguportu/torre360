<?php

namespace App\Services;

use App\Models\DocumentoRegulatorio;
use Illuminate\Support\Collection;

class DocumentoRegulatorioService
{
    /**
     * Sincroniza o status persistido no banco com base nas datas atuais de validade.
     */
    public function sincronizarStatusGeral(): int
    {
        $documentos = DocumentoRegulatorio::whereNotIn('status', ['revogado', 'em_renovacao'])->get();
        $atualizados = 0;

        foreach ($documentos as $doc) {
            $novoStatus = $doc->calcularStatus();
            if ($doc->status !== $novoStatus) {
                $doc->update(['status' => $novoStatus]);
                $atualizados++;
            }
        }

        return $atualizados;
    }

    /**
     * Retorna a lista de documentos vencidos ou em alerta de vencimento.
     */
    public function obterAlertasVencimento(?int $unidadeId = null): Collection
    {
        return DocumentoRegulatorio::with('unidade')
            ->when($unidadeId, fn ($q) => $q->where('unidade_id', $unidadeId))
            ->whereIn('status', ['vencido', 'alerta_vencimento'])
            ->orderBy('data_validade', 'asc')
            ->get();
    }

    /**
     * Retorna contadores agregados de status regulatório.
     */
    public function obterResumoPorStatus(?int $unidadeId = null): array
    {
        $query = DocumentoRegulatorio::query()
            ->when($unidadeId, fn ($q) => $q->where('unidade_id', $unidadeId));

        $total = (clone $query)->count();
        $vigentes = (clone $query)->where('status', 'vigente')->count();
        $emAlerta = (clone $query)->where('status', 'alerta_vencimento')->count();
        $vencidos = (clone $query)->where('status', 'vencido')->count();
        $emRenovacao = (clone $query)->where('status', 'em_renovacao')->count();

        return [
            'total' => $total,
            'vigentes' => $vigentes,
            'alerta' => $emAlerta,
            'vencidos' => $vencidos,
            'em_renovacao' => $emRenovacao,
            'taxa_conformidade' => $total > 0 ? round(($vigentes / $total) * 100, 1) : 100.0,
        ];
    }
}
