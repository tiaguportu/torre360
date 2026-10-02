<?php

namespace App\Support;

use App\Enums\TipoPendenciaMatricula;
use App\Models\DocumentoInserido;
use App\Models\Pessoa;
use App\Models\TipoDocumento;
use Illuminate\Support\Collection;

/**
 * Resumo das pendências de uma matrícula (responsáveis, cadastro e documentos),
 * calculado uma única vez por instância para ser reaproveitado por colunas,
 * ações e modais da listagem.
 */
final readonly class PendenciasMatricula
{
    /**
     * @param  Collection<int, array{tipo: string, pessoa: Pessoa, campos: array<string>}>  $cadastrosIncompletos
     * @param  Collection<int, TipoDocumento>  $documentosFaltantes
     * @param  Collection<int, DocumentoInserido>  $documentosRejeitados
     */
    public function __construct(
        public bool $semResponsavel,
        public Collection $cadastrosIncompletos,
        public Collection $documentosFaltantes,
        public Collection $documentosRejeitados,
        public bool $contratoNaoGerado = false,
        public bool $contratoNaoAssinado = false,
    ) {}

    public function total(): int
    {
        return (int) $this->semResponsavel
            + $this->cadastrosIncompletos->count()
            + $this->documentosFaltantes->count()
            + $this->documentosRejeitados->count()
            + (int) $this->contratoNaoGerado
            + (int) $this->contratoNaoAssinado;
    }

    public function temPendencias(): bool
    {
        return $this->total() > 0;
    }

    public function temPendenciaDocumental(): bool
    {
        return $this->documentosFaltantes->isNotEmpty() || $this->documentosRejeitados->isNotEmpty();
    }

    /**
     * @return list<TipoPendenciaMatricula>
     */
    public function tipos(): array
    {
        return array_values(array_filter([
            $this->semResponsavel ? TipoPendenciaMatricula::SEM_RESPONSAVEL : null,
            $this->cadastrosIncompletos->isNotEmpty() ? TipoPendenciaMatricula::CADASTRO_INCOMPLETO : null,
            $this->documentosFaltantes->isNotEmpty() ? TipoPendenciaMatricula::DOCUMENTOS_FALTANDO : null,
            $this->documentosRejeitados->isNotEmpty() ? TipoPendenciaMatricula::DOCUMENTOS_REJEITADOS : null,
            $this->contratoNaoGerado ? TipoPendenciaMatricula::CONTRATO_NAO_GERADO : null,
            $this->contratoNaoAssinado ? TipoPendenciaMatricula::CONTRATO_NAO_ASSINADO : null,
        ]));
    }

    /**
     * Rótulo curto do badge de cada tipo, com contagem quando faz sentido.
     */
    public function rotulo(TipoPendenciaMatricula $tipo): string
    {
        return match ($tipo) {
            TipoPendenciaMatricula::SEM_RESPONSAVEL,
            TipoPendenciaMatricula::CONTRATO_NAO_GERADO,
            TipoPendenciaMatricula::CONTRATO_NAO_ASSINADO => $tipo->getLabel(),
            TipoPendenciaMatricula::CADASTRO_INCOMPLETO => $this->cadastrosIncompletos->count() > 1
                ? $tipo->getLabel().' ('.$this->cadastrosIncompletos->count().')'
                : $tipo->getLabel(),
            TipoPendenciaMatricula::DOCUMENTOS_FALTANDO => $this->documentosFaltantes->count().' '
                .($this->documentosFaltantes->count() === 1 ? 'documento faltando' : 'documentos faltando'),
            TipoPendenciaMatricula::DOCUMENTOS_REJEITADOS => $this->documentosRejeitados->count().' '
                .($this->documentosRejeitados->count() === 1 ? 'documento rejeitado' : 'documentos rejeitados'),
        };
    }

    /**
     * Texto de apoio (tooltip) detalhando cada pendência.
     */
    public function detalhes(): string
    {
        $linhas = [];

        if ($this->semResponsavel) {
            $linhas[] = 'Sem Pai, Mãe ou Responsável associado ao aluno.';
        }

        foreach ($this->cadastrosIncompletos as $item) {
            $linhas[] = "Cadastro incompleto ({$item['tipo']}): ".($item['pessoa']->nome ?: 'Sem nome').' — falta '.implode(', ', $item['campos']).'.';
        }

        if ($this->documentosFaltantes->isNotEmpty()) {
            $linhas[] = 'Documentos faltando: '.$this->documentosFaltantes->pluck('nome')->implode(', ').'.';
        }

        if ($this->documentosRejeitados->isNotEmpty()) {
            $linhas[] = 'Documentos rejeitados: '.$this->documentosRejeitados->map(fn ($doc) => $doc->tipoDocumento?->nome)->filter()->implode(', ').'.';
        }

        if ($this->contratoNaoGerado) {
            $linhas[] = 'Contrato ainda não gerado para esta matrícula.';
        }

        if ($this->contratoNaoAssinado) {
            $linhas[] = 'Contrato gerado, mas ainda não assinado.';
        }

        return implode("\n", $linhas);
    }
}
