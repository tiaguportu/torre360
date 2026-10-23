<?php

declare(strict_types=1);

namespace App\Services\Relatorios\Fontes;

use App\Contracts\FonteDeRelatorio;
use App\Enums\StatusDocumentoAssinatura;
use App\Enums\TipoColunaRelatorio;
use App\Models\DocumentoAssinatura;
use App\Support\Relatorios\ColunaRelatorio;
use App\Support\Relatorios\FiltraPeriodo;
use App\Support\Relatorios\FiltroRelatorio;
use App\Support\Relatorios\IndexaItens;

/**
 * Documentos mandados para assinatura (Ondas 69 e 74), com o nível da assinatura. Os documentos de RH ficam de fora: a
 * permissão deles é outra (Admissão e Desligamento).
 */
final class DocumentosParaAssinatura implements FonteDeRelatorio
{
    use FiltraPeriodo;
    use IndexaItens;

    public function chave(): string
    {
        return 'documentos_para_assinatura';
    }

    public function titulo(): string
    {
        return 'Documentos para assinatura';
    }

    public function descricao(): string
    {
        return 'Documentos enviados para assinatura eletrônica ou assinados digitalmente, com situação e nível da assinatura (sem os documentos de RH).';
    }

    public function permissao(): string
    {
        return 'ViewAny:DocumentoAssinatura';
    }

    public function colunas(): array
    {
        return $this->indexar([
            new ColunaRelatorio('documento', 'Documento', TipoColunaRelatorio::Inteiro, padrao: false),
            new ColunaRelatorio('titulo', 'Título', sensivel: true),
            new ColunaRelatorio('referente_a', 'Referente a'),
            new ColunaRelatorio('status', 'Situação'),
            new ColunaRelatorio('nivel', 'Nível da assinatura'),
            new ColunaRelatorio('criado_em', 'Criado em', TipoColunaRelatorio::Data),
            new ColunaRelatorio('enviado_em', 'Enviado em', TipoColunaRelatorio::Data, padrao: false),
            new ColunaRelatorio('concluido_em', 'Assinado em', TipoColunaRelatorio::Data),
        ]);
    }

    public function filtros(): array
    {
        return $this->indexar([
            new FiltroRelatorio('criado', 'Criado em', 'data'),
            new FiltroRelatorio('status', 'Situação', 'opcao', collect(StatusDocumentoAssinatura::cases())->mapWithKeys(fn (StatusDocumentoAssinatura $s): array => [$s->value => $s->getLabel()])->all()),
        ]);
    }

    public function linhas(array $filtros, int $limite): array
    {
        $consulta = DocumentoAssinatura::query()
            ->semRh()
            ->orderByDesc('id');

        $this->aplicarPeriodo($consulta, 'created_at', $filtros, 'criado');

        if (filled($filtros['status'] ?? null) && StatusDocumentoAssinatura::tryFrom((string) $filtros['status'])) {
            $consulta->where('status', (string) $filtros['status']);
        }

        return $consulta->limit($limite)->get()->map(fn (DocumentoAssinatura $documento): array => [
            'documento' => $documento->id,
            'titulo' => $documento->titulo,
            'referente_a' => $documento->tipoDoRegistro(),
            'status' => $documento->status->getLabel(),
            'nivel' => $documento->nivel_assinatura?->getLabel(),
            'criado_em' => $documento->created_at,
            'enviado_em' => $documento->enviado_em,
            'concluido_em' => $documento->concluido_em,
        ])->all();
    }
}
