<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SituacaoDocumento;
use App\Models\DocumentoInserido;
use App\Models\Matricula;
use App\Models\TipoDocumento;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Importa documentos já enviados em uma matrícula para outra matrícula da MESMA pessoa (aluno).
 *
 * Cada documento escolhido vira um novo `DocumentoInserido` na matrícula de destino, com uma CÓPIA do arquivo
 * físico (e não o mesmo caminho): a exclusão de um documento apaga o arquivo e o visualizador autoriza o acesso
 * pelo `arquivo_path`, então compartilhar o caminho entre duas matrículas quebraria uma delas.
 */
class ImportacaoDocumentosMatriculaService
{
    private const DISCO = 'local';

    private const DIRETORIO = 'documentos_alunos';

    /**
     * Todos os documentos da matrícula de origem, para o usuário escolher, indexados pelo id do documento.
     * Cada item traz o documento (com o tipo carregado) e o motivo pelo qual NÃO pode ser importado
     * (`null` quando pode). Documento rejeitado na origem continua importável: a escolha é do usuário.
     *
     * @return Collection<int, array{documento: DocumentoInserido, motivo_bloqueio: ?string}>
     */
    public function documentosImportaveis(Matricula $origem, Matricula $destino): Collection
    {
        $tiposNoDestino = DocumentoInserido::query()
            ->where('matricula_id', $destino->id)
            ->pluck('tipo_documento_id');

        return DocumentoInserido::query()
            ->with('tipoDocumento')
            ->where('matricula_id', $origem->id)
            ->get()
            ->sortBy(fn (DocumentoInserido $documento) => $documento->tipoDocumento?->nome ?? '')
            ->mapWithKeys(fn (DocumentoInserido $documento) => [
                $documento->id => [
                    'documento' => $documento,
                    'motivo_bloqueio' => $this->motivoDeBloqueio($documento, $tiposNoDestino),
                ],
            ]);
    }

    /**
     * Copia os documentos escolhidos da matrícula de origem para a de destino.
     *
     * Documentos cujo tipo já existe no destino, ou sem arquivo físico, são ignorados (nunca sobrescreve nada).
     * A operação é atômica: se algo falhar, nenhum registro é criado e os arquivos já copiados são removidos.
     *
     * @param  list<int|string>  $documentoIds  Ids de `DocumentoInserido` da matrícula de origem.
     * @return array{importados: list<string>, ignorados: list<array{tipo: string, motivo: string}>}
     *
     * @throws DomainException quando origem e destino são a mesma matrícula ou de pessoas diferentes.
     */
    public function importar(Matricula $origem, Matricula $destino, array $documentoIds): array
    {
        if ($origem->is($destino)) {
            throw new DomainException('A matrícula de origem deve ser diferente da matrícula de destino.');
        }

        if ((int) $origem->pessoa_id !== (int) $destino->pessoa_id) {
            throw new DomainException('Só é possível importar documentos de outra matrícula do mesmo aluno.');
        }

        $documentos = DocumentoInserido::query()
            ->with('tipoDocumento')
            ->where('matricula_id', $origem->id)
            ->whereIn('id', $documentoIds)
            ->get();

        $destino->loadMissing('turma.serie');
        $origem->loadMissing(['turma', 'periodoLetivo', 'pessoa']);

        $disco = Storage::disk(self::DISCO);
        $copiados = [];
        $importados = [];
        $ignorados = [];

        try {
            DB::transaction(function () use ($origem, $destino, $documentos, $disco, &$copiados, &$importados, &$ignorados): void {
                // Serializa importações simultâneas para o mesmo destino (não há índice único por tipo).
                Matricula::query()->whereKey($destino->id)->lockForUpdate()->first();

                foreach ($documentos as $documento) {
                    $tipo = $documento->tipoDocumento;
                    $nomeTipo = $tipo?->nome ?? 'Documento';

                    $jaTemNoDestino = DocumentoInserido::query()
                        ->where('matricula_id', $destino->id)
                        ->where('tipo_documento_id', $documento->tipo_documento_id)
                        ->exists();

                    if ($jaTemNoDestino) {
                        $ignorados[] = ['tipo' => $nomeTipo, 'motivo' => 'Já existe um documento deste tipo nesta matrícula.'];

                        continue;
                    }

                    if (blank($documento->arquivo_path) || ! $disco->exists($documento->arquivo_path)) {
                        $ignorados[] = ['tipo' => $nomeTipo, 'motivo' => 'Arquivo não encontrado no servidor.'];

                        continue;
                    }

                    $novoCaminho = $this->caminhoDaCopia($documento);

                    if (! $disco->copy($documento->arquivo_path, $novoCaminho)) {
                        throw new RuntimeException("Falha ao copiar o arquivo do documento '{$nomeTipo}'.");
                    }

                    $copiados[] = $novoCaminho;

                    DocumentoInserido::create([
                        'matricula_id' => $destino->id,
                        'tipo_documento_id' => $documento->tipo_documento_id,
                        'arquivo_path' => $novoCaminho,
                        'nome_arquivo_original' => $documento->nome_arquivo_original ?? basename($documento->arquivo_path),
                        'hash_arquivo' => $documento->hash_arquivo,
                        'status' => SituacaoDocumento::EM_ANALISE,
                        'observacoes' => $this->observacaoDaImportacao($origem, $documento),
                    ]);

                    $this->garantirTipoNaMatricula($destino, $documento->tipo_documento_id);

                    $importados[] = $nomeTipo;
                }

                if ($importados !== []) {
                    activity()
                        ->performedOn($destino)
                        ->withProperties([
                            'origem_matricula_id' => $origem->id,
                            'tipos_documento' => $importados,
                        ])
                        ->log('Importou documentos de outra matrícula');
                }
            });
        } catch (Throwable $e) {
            foreach ($copiados as $caminho) {
                $disco->delete($caminho);
            }

            throw $e;
        }

        return ['importados' => $importados, 'ignorados' => $ignorados];
    }

    /**
     * @param  Collection<int, int>  $tiposNoDestino  Ids dos tipos que a matrícula de destino já possui.
     */
    private function motivoDeBloqueio(DocumentoInserido $documento, Collection $tiposNoDestino): ?string
    {
        if ($tiposNoDestino->contains($documento->tipo_documento_id)) {
            return 'Já existe um documento deste tipo nesta matrícula.';
        }

        if (blank($documento->arquivo_path) || ! Storage::disk(self::DISCO)->exists($documento->arquivo_path)) {
            return 'Arquivo não encontrado no servidor.';
        }

        return null;
    }

    /**
     * Mesmo padrão de nome dos uploads da tela de documentos: `Doc-{tipo}-{uniqid}-{hash}.{ext}`.
     */
    private function caminhoDaCopia(DocumentoInserido $documento): string
    {
        $slug = Str::slug($documento->tipoDocumento?->nome ?? 'documento');
        $hash = $documento->hash_arquivo ?: Str::random(16);
        $extensao = pathinfo((string) $documento->arquivo_path, PATHINFO_EXTENSION);

        return self::DIRETORIO."/Doc-{$slug}-".uniqid()."-{$hash}".($extensao !== '' ? ".{$extensao}" : '');
    }

    private function observacaoDaImportacao(Matricula $origem, DocumentoInserido $documento): string
    {
        $nota = "Importado da matrícula {$origem->label_exibicao}.";

        return filled($documento->observacoes) ? "{$nota}\n{$documento->observacoes}" : $nota;
    }

    /**
     * A tela de documentos só lista os tipos que a matrícula exige. Se o tipo importado não é um deles, vincula-o
     * à matrícula para que o documento apareça (e possa ser baixado ou excluído) na tela.
     */
    private function garantirTipoNaMatricula(Matricula $destino, int $tipoDocumentoId): void
    {
        $jaListado = TipoDocumento::query()
            ->aplicaveisA($destino)
            ->whereKey($tipoDocumentoId)
            ->exists();

        if (! $jaListado) {
            $destino->tiposDocumentos()->syncWithoutDetaching([$tipoDocumentoId]);
        }
    }
}
