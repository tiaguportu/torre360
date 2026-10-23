<?php

declare(strict_types=1);

namespace App\Services\Documentos;

use App\Enums\StatusSolicitacaoDocumento;
use App\Models\DocumentoAssinatura;
use App\Models\HistoricoEscolar;
use App\Models\SolicitacaoDocumento;
use App\Models\Unidade;
use App\Models\User;
use App\Services\HistoricoEscolarService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Declarações e histórico escolar com assinatura digital (Onda 74). Os dois documentos já existem como PDF emitido pela
 * escola; aqui eles seguem por um de dois caminhos:
 *
 *  1. **Assinatura pelo Assinafy** (a direção assina por e-mail): o PDF emitido vira um documento da Onda 69 e, ao fim, a
 *     cópia certificada passa a ser a cópia assinada do documento;
 *  2. **PDF assinado fora** (certificado ICP-Brasil A1/A3, gov.br): a secretaria anexa o arquivo assinado, o sistema lê o que o
 *     PDF traz (assinaturas, marca ICP-Brasil) e o guarda como a cópia assinada.
 *
 * O sistema **não valida** a cadeia de certificação nem a validade do certificado; essa conferência é do Verificador de
 * Conformidade do ITI (validar.iti.gov.br). A tela e a página pública dizem isso.
 */
class DocumentosOficiaisService
{
    /** Tamanho máximo do PDF assinado anexado (um histórico com certificado e carimbo passa de alguns MB). */
    public const TAMANHO_MAXIMO_BYTES = 15 * 1024 * 1024;

    public function __construct(
        private readonly AssinaturaDocumentoService $assinaturas,
        private readonly SignatariosSugeridosService $sugestoes,
        private readonly HistoricoEscolarService $historicos,
    ) {}

    public function ehOficial(Model $registro): bool
    {
        return $registro instanceof SolicitacaoDocumento || $registro instanceof HistoricoEscolar;
    }

    /**
     * Por que este documento ainda não pode ser assinado (null = pode).
     */
    public function impedimento(SolicitacaoDocumento|HistoricoEscolar $oficial): ?string
    {
        if ($oficial->temCopiaAssinada()) {
            return 'Este documento já tem uma cópia assinada.';
        }

        if ($oficial instanceof SolicitacaoDocumento) {
            if ($oficial->status !== StatusSolicitacaoDocumento::Disponivel) {
                return 'A declaração precisa estar disponível (PDF emitido) para ser assinada.';
            }

            if (blank($oficial->arquivo_path) || ! Storage::disk('local')->exists($oficial->arquivo_path)) {
                return 'O PDF da declaração não foi encontrado. Emita o PDF novamente antes de assinar.';
            }
        }

        return null;
    }

    public function titulo(SolicitacaoDocumento|HistoricoEscolar $oficial): string
    {
        if ($oficial instanceof SolicitacaoDocumento) {
            $oficial->loadMissing(['templateDocumento', 'matricula.pessoa']);

            return ($oficial->templateDocumento?->nome ?? 'Declaração').' — '.($oficial->matricula?->pessoa?->nome ?? 'aluno').' — '.$oficial->protocolo;
        }

        $oficial->loadMissing('pessoa');

        return 'Histórico escolar — '.($oficial->pessoa?->nome ?? 'aluno').' — '.$oficial->codigo_autenticidade;
    }

    public function nomeDoAluno(SolicitacaoDocumento|HistoricoEscolar $oficial): string
    {
        if ($oficial instanceof SolicitacaoDocumento) {
            $oficial->loadMissing('matricula.pessoa');

            return (string) ($oficial->matricula?->pessoa?->nome ?? 'o aluno');
        }

        $oficial->loadMissing('pessoa');

        return (string) ($oficial->pessoa?->nome ?? 'o aluno');
    }

    public function unidade(SolicitacaoDocumento|HistoricoEscolar $oficial): ?Unidade
    {
        if ($oficial instanceof SolicitacaoDocumento) {
            $oficial->loadMissing('matricula.turma.serie.curso.unidade');

            return $oficial->matricula?->turma?->serie?->curso?->unidade;
        }

        $oficial->loadMissing(['unidade', 'pessoa']);

        return $oficial->unidade ?? $oficial->pessoa?->matriculas()->with('turma.serie.curso.unidade')->latest('id')->first()?->turma?->serie?->curso?->unidade;
    }

    /**
     * @return array{assinantes: list<array{nome: string, email: string, papel: string}>, sem_email: list<string>}
     */
    public function signatariosSugeridos(SolicitacaoDocumento|HistoricoEscolar $oficial): array
    {
        return $this->sugestoes->daDirecao($this->unidade($oficial));
    }

    /**
     * O PDF que a escola emitiu: o arquivo guardado da declaração ou o histórico gerado agora.
     *
     * @throws \DomainException
     */
    public function pdfEmitido(SolicitacaoDocumento|HistoricoEscolar $oficial): string
    {
        if ($oficial instanceof SolicitacaoDocumento) {
            if (blank($oficial->arquivo_path) || ! Storage::disk('local')->exists($oficial->arquivo_path)) {
                throw new \DomainException('O PDF da declaração não foi encontrado. Emita o PDF novamente antes de assinar.');
            }

            return Storage::disk('local')->get($oficial->arquivo_path);
        }

        return $this->historicos->gerarPdf($oficial)->output();
    }

    /**
     * Manda o PDF emitido para a assinatura eletrônica da direção (a Onda 69 envia e acompanha).
     *
     * @param  list<array{nome: string, email: string, papel?: ?string}>  $assinantes
     *
     * @throws \DomainException
     */
    public function enviarParaAssinatura(SolicitacaoDocumento|HistoricoEscolar $oficial, array $assinantes, ?User $por = null): DocumentoAssinatura
    {
        if ($motivo = $this->impedimento($oficial)) {
            throw new \DomainException($motivo);
        }

        return $this->assinaturas->criarDePdf($oficial, $this->pdfEmitido($oficial), $this->titulo($oficial), $assinantes, $por);
    }

    /**
     * Anexa o PDF assinado fora do sistema como a cópia assinada do documento.
     *
     * @throws \DomainException
     */
    public function anexarAssinado(SolicitacaoDocumento|HistoricoEscolar $oficial, string $pdfAssinado, ?User $por = null): DocumentoAssinatura
    {
        if ($motivo = $this->impedimento($oficial)) {
            throw new \DomainException($motivo);
        }

        if ($pdfAssinado === '' || strlen($pdfAssinado) > self::TAMANHO_MAXIMO_BYTES) {
            throw new \DomainException('O PDF assinado deve ter até '.(self::TAMANHO_MAXIMO_BYTES / 1024 / 1024).' MB.');
        }

        // Só a declaração tem o PDF emitido guardado para comparar com o assinado; o histórico é gerado na hora.
        $original = $oficial instanceof SolicitacaoDocumento ? $this->pdfEmitido($oficial) : null;

        return $this->assinaturas->registrarAssinadoFora($oficial, $pdfAssinado, $this->titulo($oficial), $original, $por);
    }
}
