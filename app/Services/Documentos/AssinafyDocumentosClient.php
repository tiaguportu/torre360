<?php

declare(strict_types=1);

namespace App\Services\Documentos;

use App\Services\AssinafyService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Chamadas HTTP ao Assinafy para documentos avulsos (Onda 69). Usa os mesmos endereços e o mesmo fluxo do envio de
 * contratos (`AssinafyService`), que continua intocado: enviar o PDF, achar ou cadastrar cada assinante, esperar o
 * processamento, pedir as assinaturas, consultar e baixar o certificado.
 *
 * Cada método lança `\RuntimeException` com a mensagem da API quando a chamada falha; quem chama decide como mostrar.
 */
class AssinafyDocumentosClient
{
    private ?string $chave;

    private ?string $conta;

    private string $url;

    public function __construct()
    {
        $this->chave = config('services.assinafy.key');
        $this->conta = config('services.assinafy.account_id');

        $url = rtrim((string) (config('services.assinafy.url') ?: 'https://sandbox.assinafy.com.br/v1'), '/');

        // O endereço .pages.dev é só o site; a API fica no sandbox (mesma correção do envio de contratos).
        if (str_contains($url, 'assinafy-app.pages.dev')) {
            $url = 'https://sandbox.assinafy.com.br/v1';
        }

        $this->url = str_contains($url, '/v1') ? $url : $url.'/v1';
    }

    public function configurado(): bool
    {
        return filled($this->chave) && filled($this->conta);
    }

    public function urlPadrao(): string
    {
        return $this->url;
    }

    /**
     * Endereços a tentar ao consultar: o salvo no documento, o configurado e os dois ambientes do Assinafy.
     *
     * @return list<string>
     */
    public function urls(?string $salvo = null): array
    {
        $urls = [];

        if (filled($salvo)) {
            $urls[] = rtrim((string) $salvo, '/');
        }

        foreach ([$this->url, ...app(AssinafyService::class)->getApiUrlsToTry()] as $url) {
            if (! in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    private function http(): PendingRequest
    {
        return Http::withHeaders(['X-Api-Key' => (string) $this->chave, 'Accept' => 'application/json'])->timeout(30);
    }

    /**
     * Envia o PDF e devolve o id do documento.
     */
    public function enviarPdf(string $conteudo, string $nome): string
    {
        $resposta = $this->tentar(
            fn (): Response => Http::withHeaders(['X-Api-Key' => (string) $this->chave])
                ->timeout(60)
                ->attach('file', $conteudo, $nome)
                ->post("{$this->url}/accounts/{$this->conta}/documents"),
            'Erro no upload do documento',
        );

        $this->exigirSucesso($resposta, 'Erro no upload do documento');

        $id = $resposta->json('id') ?? $resposta->json('data.id');

        if (blank($id)) {
            throw new \RuntimeException('O Assinafy não devolveu o código do documento.');
        }

        return (string) $id;
    }

    /**
     * O id do assinante com esse e-mail: o que já existe ou um novo.
     */
    public function garantirAssinante(string $nome, string $email): string
    {
        $busca = $this->tentar(
            fn (): Response => $this->http()->get("{$this->url}/accounts/{$this->conta}/signers", ['search' => $email]),
            "Erro ao procurar o assinante {$nome}",
        );

        if ($busca->successful()) {
            foreach ($busca->json('data') ?? [] as $assinante) {
                if (isset($assinante['email']) && mb_strtolower(trim($assinante['email'])) === mb_strtolower(trim($email))) {
                    return (string) $assinante['id'];
                }
            }
        }

        $criado = $this->tentar(
            fn (): Response => $this->http()->post("{$this->url}/accounts/{$this->conta}/signers", ['full_name' => $nome, 'email' => $email]),
            "Erro ao cadastrar o assinante {$nome}",
        );

        $this->exigirSucesso($criado, "Erro ao cadastrar o assinante {$nome}");

        return (string) ($criado->json('id') ?? $criado->json('data.id'));
    }

    /**
     * Espera o Assinafy terminar de processar o PDF (o pedido de assinatura só vale depois disso).
     */
    public function aguardarProcessamento(string $documentoId, int $tentativas = 5): void
    {
        for ($i = 0; $i < $tentativas; $i++) {
            $resposta = $this->tentar(
                fn (): Response => $this->http()->get("{$this->url}/documents/{$documentoId}"),
                'Erro ao conferir o processamento do documento',
            );

            if ($resposta->successful()) {
                $status = ($resposta->json('data') ?? $resposta->json())['status'] ?? null;

                if ($status !== 'metadata_processing') {
                    return;
                }
            }

            if ($i < $tentativas - 1) {
                sleep(2);
            }
        }
    }

    /**
     * Pede a assinatura de todos e devolve os endereços de assinatura: lista de [signer_id, url].
     *
     * @param  list<string>  $assinanteIds
     * @return list<array{signer_id: ?string, url: ?string}>
     */
    public function solicitarAssinaturas(string $documentoId, array $assinanteIds): array
    {
        $resposta = $this->tentar(
            fn (): Response => $this->http()->post("{$this->url}/documents/{$documentoId}/assignments", [
                'signers' => array_map(fn (string $id): array => ['id' => $id], $assinanteIds),
                'method' => 'virtual',
            ]),
            'Erro ao solicitar as assinaturas',
        );

        $this->exigirSucesso($resposta, 'Erro ao solicitar as assinaturas');

        $enderecos = $resposta->json('signing_urls') ?? $resposta->json('data.signing_urls') ?? [];

        return array_values(array_map(fn (array $e): array => ['signer_id' => $e['signer_id'] ?? null, 'url' => $e['url'] ?? null], $enderecos));
    }

    /**
     * O documento no Assinafy, tentando os ambientes em ordem.
     *
     * @return array{ok: bool, dados: array<string, mixed>, url: ?string, erro: ?string}
     */
    public function consultar(string $documentoId, ?string $urlSalva = null): array
    {
        $ultimoErro = null;

        foreach ($this->urls($urlSalva) as $url) {
            try {
                $resposta = $this->http()->get("{$url}/documents/{$documentoId}");
            } catch (ConnectionException) {
                // Sem rede ou tempo esgotado neste ambiente: tenta o próximo e, no fim, devolve o erro.
                $ultimoErro = 'sem conexão com o Assinafy ou tempo esgotado';

                continue;
            }

            if ($resposta->successful()) {
                return ['ok' => true, 'dados' => $resposta->json('data') ?? $resposta->json() ?? [], 'url' => $url, 'erro' => null];
            }

            $ultimoErro = $resposta->json('message') ?? $resposta->body();
        }

        return ['ok' => false, 'dados' => [], 'url' => null, 'erro' => $ultimoErro ?? 'Documento não encontrado no Assinafy.'];
    }

    /**
     * O PDF com o certificado de assinaturas, ou null enquanto ainda não está pronto.
     */
    public function baixarCertificado(string $documentoId, ?string $urlSalva = null): ?string
    {
        foreach ($this->urls($urlSalva) as $url) {
            try {
                $resposta = $this->http()->get("{$url}/documents/{$documentoId}/download/certificated");
            } catch (ConnectionException) {
                // O download é repetido pela rotina periódica: sem conexão agora, fica para a próxima rodada.
                continue;
            }

            if ($resposta->successful() && $resposta->body() !== '') {
                return $resposta->body();
            }
        }

        return null;
    }

    /**
     * Tira o documento do Assinafy (cancelamento ou expiração), em melhor esforço: quem assina só enxerga o link se o
     * documento continuar lá. Devolve se o Assinafy confirmou.
     */
    public function excluir(string $documentoId, ?string $urlSalva = null): bool
    {
        try {
            foreach ($this->urls($urlSalva) as $url) {
                if ($this->http()->delete("{$url}/documents/{$documentoId}")->successful()) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Falha ao excluir documento no Assinafy.', ['assinafy_id' => $documentoId, 'erro' => $e->getMessage()]);
        }

        return false;
    }

    /**
     * Sem rede ou com o tempo esgotado a biblioteca lança `ConnectionException`, que não é `RuntimeException`: vira a mesma
     * exceção das respostas de erro, para quem chama tratar tudo do mesmo jeito (o documento fica com erro e pode ser enviado
     * de novo).
     *
     * @param  callable(): Response  $chamada
     */
    private function tentar(callable $chamada, string $mensagem): Response
    {
        try {
            return $chamada();
        } catch (ConnectionException $e) {
            throw new \RuntimeException($mensagem.': sem conexão com o Assinafy ou tempo esgotado.', 0, $e);
        }
    }

    private function exigirSucesso(Response $resposta, string $mensagem): void
    {
        if (! $resposta->successful()) {
            throw new \RuntimeException($mensagem.': '.($resposta->json('message') ?? $resposta->body()));
        }
    }
}
