<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Busca de dados de um livro pelo ISBN em bases públicas, com download da capa.
 *
 * Princípios (a busca roda dentro de uma requisição do painel, num PHP com `max_execution_time` de 30 s):
 *
 *  - as fontes públicas (Open Library, BrasilAPI, Google Books) são consultadas **em paralelo**;
 *  - o resultado é guardado em **cache por ISBN** (7 dias; "não encontrado" só 1 h; falha de rede nunca é cacheada
 *    como "não existe");
 *  - há um **orçamento de tempo** (~20 s): fases opcionais são puladas quando ele se esgota;
 *  - a capa só é aceita se for **uma imagem de verdade** (tipo real detectado pelo conteúdo, tamanho máximo,
 *    dimensões mínimas) e vem de um endereço público;
 *  - a capa baixada vai para uma área **pendente** com nome fixo por ISBN: cliques repetidos reaproveitam o mesmo
 *    arquivo, e só ao salvar o livro ela é promovida a definitiva (ver {@see LivroCapaService});
 *  - a Amazon (raspagem de HTML e CDN de capas, não oficiais e contra os termos de uso) **só é consultada se
 *    `LIVROS_AMAZON_HABILITADO=true`**.
 */
class LivroLookupService
{
    /** Pasta (no disco `public`) das capas baixadas que ainda não foram salvas junto a um livro. */
    public const DIRETORIO_PENDENTES = 'livros/capas/pendentes';

    public const MAX_BYTES_CAPA = 3 * 1024 * 1024;

    public const LADO_MINIMO_CAPA = 60;

    public const LADO_MAXIMO_CAPA = 8000;

    private const CHAVE_CACHE = 'livros:isbn:v1:';

    private const TTL_ENCONTRADO = 604800;      // 7 dias

    private const TTL_INCOMPLETO = 600;         // 10 min: houve falha em alguma fonte, o resultado pode estar incompleto

    private const TTL_NAO_ENCONTRADO = 3600;    // 1 h

    private const TTL_SEM_CAPA = 900;           // 15 min: evita tentar de novo a cada clique quando nenhuma fonte tem capa

    private const ORCAMENTO_SEGUNDOS = 20;

    private const TIMEOUT_FONTE = 5;

    private const TIMEOUT_DOWNLOAD_MAX = 8;

    private const AGENTE = 'Torre360/1.0';

    /** @var array<string, string> tipo real da imagem => extensão gravada */
    private const EXTENSOES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Busca dados do livro a partir do ISBN e tenta fazer o download da capa.
     *
     * @return array{
     *     sucesso: bool,
     *     mensagem: string,
     *     dados: array{
     *         isbn: string,
     *         titulo: ?string,
     *         autor: ?string,
     *         editora: ?string,
     *         categoria: ?string,
     *         capa: ?string,
     *         capa_url: ?string
     *     }
     * }
     */
    public function buscarPorIsbn(string $isbn): array
    {
        $cleanIsbn = preg_replace('/[^0-9X]/i', '', trim($isbn));

        if (empty($cleanIsbn) || ! in_array(strlen($cleanIsbn), [10, 13], true)) {
            return $this->falha($cleanIsbn, 'O ISBN informado é inválido. Um ISBN deve conter 10 ou 13 dígitos.');
        }

        $cleanIsbn = strtoupper($cleanIsbn);
        $inicio = microtime(true);

        $consulta = $this->metadados($cleanIsbn, $inicio);

        if ($consulta['dados'] === null) {
            return $this->falha(
                $cleanIsbn,
                $consulta['falhas'] > 0
                    ? 'Não foi possível consultar as bases de livros agora (serviço indisponível). Tente novamente em instantes.'
                    : 'Nenhum dado encontrado para o ISBN informado.'
            );
        }

        $dados = $consulta['dados'];
        $capaPath = null;
        $capaUrl = $dados['capa_url'] ?? null;

        if ($this->restante($inicio) >= 3) {
            [$capaPath, $capaUrl] = $this->obterCapa($cleanIsbn, $capaUrl, $inicio);
        }

        return [
            'sucesso' => true,
            'mensagem' => 'Dados do livro localizados com sucesso!',
            'dados' => [
                'isbn' => $cleanIsbn,
                'titulo' => $dados['titulo'] ?? null,
                'autor' => $dados['autor'] ?? null,
                'editora' => $dados['editora'] ?? null,
                'categoria' => $dados['categoria'] ?? null,
                'capa' => $capaPath,
                'capa_url' => $capaUrl,
            ],
        ];
    }

    // ------------------------------------------------------------------ metadados (cache + fontes em paralelo)

    /**
     * @return array{dados: ?array<string, ?string>, falhas: int}
     */
    private function metadados(string $isbn, float $inicio): array
    {
        $chave = self::CHAVE_CACHE.$isbn;
        $emCache = Cache::get($chave);

        if (is_array($emCache) && array_key_exists('dados', $emCache)) {
            return ['dados' => $emCache['dados'], 'falhas' => 0];
        }

        $consulta = $this->consultarFontes($isbn, $inicio);

        if ($consulta['dados'] !== null) {
            Cache::put($chave, ['dados' => $consulta['dados']], $consulta['falhas'] === 0 ? self::TTL_ENCONTRADO : self::TTL_INCOMPLETO);
        } elseif ($consulta['falhas'] === 0) {
            // Todas as fontes responderam e nenhuma conhece o ISBN. Se alguma falhou, não se afirma que não existe.
            Cache::put($chave, ['dados' => null], self::TTL_NAO_ENCONTRADO);
        }

        return $consulta;
    }

    /**
     * @return array{dados: ?array<string, ?string>, falhas: int}
     */
    private function consultarFontes(string $isbn, float $inicio): array
    {
        $falhas = 0;

        try {
            $respostas = Http::pool(fn (Pool $pool): array => [
                $pool->as('openlibrary')
                    ->timeout(self::TIMEOUT_FONTE)->connectTimeout(3)
                    ->withHeaders(['User-Agent' => 'Torre360/1.0 (biblioteca@torre360.local)'])
                    ->get("https://openlibrary.org/api/books?bibkeys=ISBN:{$isbn}&format=json&jscmd=data"),
                $pool->as('brasilapi')
                    ->timeout(self::TIMEOUT_FONTE)->connectTimeout(3)
                    ->withHeaders(['User-Agent' => self::AGENTE])
                    ->get("https://brasilapi.com.br/api/isbn/v1/{$isbn}"),
                $pool->as('google')
                    ->timeout(self::TIMEOUT_FONTE)->connectTimeout(3)
                    ->withHeaders(['User-Agent' => self::AGENTE])
                    ->get('https://www.googleapis.com/books/v1/volumes', $this->parametrosGoogle($isbn)),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return ['dados' => null, 'falhas' => 3];
        }

        $openLibrary = $this->lerFonte($respostas['openlibrary'] ?? null, fn (array $json) => $this->interpretarOpenLibrary($json, $isbn), $falhas);
        $brasilApi = $this->lerFonte($respostas['brasilapi'] ?? null, fn (array $json) => $this->interpretarBrasilApi($json), $falhas);

        $jsonGoogle = $this->jsonDaFonte($respostas['google'] ?? null, $falhas);
        $google = $jsonGoogle !== null ? $this->interpretarGoogleBooks($jsonGoogle) : null;

        $dados = $this->mesclar($openLibrary, $brasilApi, $google);

        // Google Books às vezes só conhece a edição pelo ISBN-10.
        if ($jsonGoogle !== null && $google === null && $this->faltaTituloOuAutor($dados) && $this->restante($inicio) >= 10) {
            $isbn10 = strlen($isbn) === 13 ? $this->converterIsbn13ParaIsbn10($isbn) : null;

            if ($isbn10) {
                $segundo = $this->lerFonte($this->requisitarGoogle($isbn10), fn (array $json) => $this->interpretarGoogleBooks($json), $falhas);
                $dados = $this->mesclar($dados, $segundo);
            }
        }

        // Raspagem da Amazon: não oficial e contra os termos de uso — só com LIVROS_AMAZON_HABILITADO=true.
        if ($this->amazonHabilitada() && (! ($dados['titulo'] ?? null) || ! ($dados['autor'] ?? null) || ! ($dados['editora'] ?? null)) && $this->restante($inicio) >= 10) {
            $dados = $this->mesclar($dados, $this->consultarAmazon($isbn));
        }

        if (! ($dados['titulo'] ?? null) && ! ($dados['autor'] ?? null)) {
            return ['dados' => null, 'falhas' => $falhas];
        }

        return ['dados' => $dados, 'falhas' => $falhas];
    }

    /**
     * @param  array<string, mixed>|null  ...$fontes  em ordem de prioridade; o primeiro valor preenchido vence
     * @return array<string, ?string>
     */
    private function mesclar(?array ...$fontes): array
    {
        $resultado = ['titulo' => null, 'autor' => null, 'editora' => null, 'categoria' => null, 'capa_url' => null];

        foreach ($fontes as $fonte) {
            foreach (array_keys($resultado) as $campo) {
                $resultado[$campo] = $resultado[$campo] ?: ($fonte[$campo] ?? null);
            }
        }

        return $resultado;
    }

    /**
     * @param  array<string, ?string>  $dados
     */
    private function faltaTituloOuAutor(array $dados): bool
    {
        return ! ($dados['titulo'] ?? null) || ! ($dados['autor'] ?? null);
    }

    /**
     * Decodifica a resposta de uma fonte. Contabiliza falha (rede, 5xx, limite de requisições) para que "não
     * encontrado" nunca seja confundido com "fonte fora do ar".
     *
     * @return array<string, mixed>|null
     */
    private function jsonDaFonte(mixed $resposta, int &$falhas): ?array
    {
        if (! $resposta instanceof Response) {
            $falhas++;

            return null;
        }

        if ($resposta->serverError() || $resposta->status() === 429) {
            $falhas++;

            return null;
        }

        if (! $resposta->successful()) {
            return null;
        }

        $json = $resposta->json();

        return is_array($json) ? $json : null;
    }

    /**
     * @param  callable(array<string, mixed>): ?array<string, ?string>  $interpretar
     * @return array<string, ?string>|null
     */
    private function lerFonte(mixed $resposta, callable $interpretar, int &$falhas): ?array
    {
        $json = $this->jsonDaFonte($resposta, $falhas);

        if ($json === null) {
            return null;
        }

        try {
            return $interpretar($json);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    // ------------------------------------------------------------------ interpretação das fontes

    /**
     * Open Library (jscmd=data).
     *
     * @param  array<string, mixed>  $json
     * @return array<string, ?string>|null
     */
    private function interpretarOpenLibrary(array $json, string $isbn): ?array
    {
        $book = $json["ISBN:{$isbn}"] ?? null;

        if (empty($book)) {
            return null;
        }

        $titulo = $book['title'] ?? null;
        if (! empty($book['subtitle'])) {
            $titulo .= ': '.$book['subtitle'];
        }

        return [
            'titulo' => $titulo,
            'autor' => ! empty($book['authors']) ? collect($book['authors'])->pluck('name')->filter()->implode(', ') : null,
            'editora' => ! empty($book['publishers']) ? collect($book['publishers'])->pluck('name')->filter()->first() : null,
            'categoria' => ! empty($book['subjects']) ? collect($book['subjects'])->pluck('name')->filter()->first() : null,
            'capa_url' => $book['cover']['large'] ?? $book['cover']['medium'] ?? $book['cover']['small'] ?? null,
        ];
    }

    /**
     * BrasilAPI.
     *
     * @param  array<string, mixed>  $book
     * @return array<string, ?string>|null
     */
    private function interpretarBrasilApi(array $book): ?array
    {
        if (empty($book['title'])) {
            return null;
        }

        $titulo = $book['title'];
        if (! empty($book['subtitle'])) {
            $titulo .= ': '.$book['subtitle'];
        }

        return [
            'titulo' => $titulo,
            'autor' => ! empty($book['authors']) && is_array($book['authors']) ? collect($book['authors'])->filter()->implode(', ') : null,
            'editora' => $book['publisher'] ?? null,
            'categoria' => ! empty($book['subjects']) && is_array($book['subjects']) ? collect($book['subjects'])->filter()->first() : null,
            'capa_url' => $book['cover_url'] ?? null,
        ];
    }

    /**
     * Google Books.
     *
     * @param  array<string, mixed>  $json
     * @return array<string, ?string>|null
     */
    private function interpretarGoogleBooks(array $json): ?array
    {
        $volume = $json['items'][0]['volumeInfo'] ?? null;

        if (empty($volume)) {
            return null;
        }

        $titulo = $volume['title'] ?? null;
        if (! empty($volume['subtitle'])) {
            $titulo .= ': '.$volume['subtitle'];
        }

        $capaUrl = $volume['imageLinks']['thumbnail'] ?? $volume['imageLinks']['smallThumbnail'] ?? null;

        if ($capaUrl && str_starts_with($capaUrl, 'http://')) {
            $capaUrl = 'https://'.substr($capaUrl, 7);
        }

        return [
            'titulo' => $titulo,
            'autor' => ! empty($volume['authors']) ? implode(', ', $volume['authors']) : null,
            'editora' => $volume['publisher'] ?? null,
            'categoria' => ! empty($volume['categories']) ? $volume['categories'][0] : null,
            'capa_url' => $capaUrl,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parametrosGoogle(string $isbn): array
    {
        $params = ['q' => "isbn:{$isbn}"];

        // Só via config(): `env()` fora dos arquivos de config devolve null quando a configuração está em cache.
        $apiKey = config('services.google_books.key');
        if ($apiKey) {
            $params['key'] = $apiKey;
        }

        return $params;
    }

    private function requisitarGoogle(string $isbn): mixed
    {
        try {
            return Http::timeout(self::TIMEOUT_FONTE)->connectTimeout(3)
                ->withHeaders(['User-Agent' => self::AGENTE])
                ->get('https://www.googleapis.com/books/v1/volumes', $this->parametrosGoogle($isbn));
        } catch (\Throwable) {
            return null;
        }
    }

    // ------------------------------------------------------------------ capa

    /**
     * Resolve a capa: reaproveita a já baixada para este ISBN ou baixa a primeira candidata válida.
     *
     * @return array{0: ?string, 1: ?string} [caminho no disco public, URL de origem]
     */
    private function obterCapa(string $isbn, ?string $capaUrl, float $inicio): array
    {
        $pendente = $this->capaPendenteExistente($isbn);

        if ($pendente !== null) {
            return [$pendente, $capaUrl];
        }

        $chaveSemCapa = self::CHAVE_CACHE.'sem-capa:'.$isbn;

        if (Cache::has($chaveSemCapa)) {
            return [null, $capaUrl];
        }

        $candidatas = array_values(array_unique(array_filter([
            $capaUrl,
            // Open Library Covers: com `default=false` responde 404 quando não há capa (em vez de imagem genérica).
            "https://covers.openlibrary.org/b/isbn/{$isbn}-L.jpg?default=false",
            $this->amazonHabilitada() ? $this->urlCapaAmazon($isbn) : null,
        ])));

        $tentou = false;

        foreach ($candidatas as $url) {
            if ($this->restante($inicio) < 3) {
                break;
            }

            $tentou = true;
            $caminho = $this->baixarESalvarCapa($url, $isbn, (int) min(self::TIMEOUT_DOWNLOAD_MAX, floor($this->restante($inicio))));

            if ($caminho !== null) {
                return [$caminho, $url];
            }
        }

        if ($tentou) {
            Cache::put($chaveSemCapa, true, self::TTL_SEM_CAPA);
        }

        return [null, $capaUrl];
    }

    /**
     * Baixa a imagem, valida e grava na área de capas pendentes (nome fixo por ISBN).
     *
     * @return string|null caminho no disco `public`, ou null se a URL não for pública/segura ou o arquivo não for
     *                     uma imagem aceitável
     */
    public function baixarESalvarCapa(string $url, string $isbn, int $timeout = self::TIMEOUT_DOWNLOAD_MAX): ?string
    {
        $existente = $this->capaPendenteExistente($isbn);
        if ($existente !== null) {
            return $existente;
        }

        if (! $this->urlPublica($url)) {
            return null;
        }

        try {
            $response = Http::timeout(max(2, $timeout))
                ->connectTimeout(3)
                ->withHeaders(['User-Agent' => self::AGENTE])
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 3,
                        'protocols' => ['http', 'https'],
                        'referer' => false,
                        // Um redirecionamento não pode levar a um endereço interno.
                        'on_redirect' => function ($request, $resposta, $uri): void {
                            if (! $this->urlPublica((string) $uri)) {
                                throw new \RuntimeException('Redirecionamento para destino não permitido.');
                            }
                        },
                    ],
                    // Aborta antes de baixar o corpo quando o servidor já declara um arquivo grande demais.
                    'on_headers' => function ($resposta): void {
                        if ((int) $resposta->getHeaderLine('Content-Length') > self::MAX_BYTES_CAPA) {
                            throw new \RuntimeException('Imagem maior que o limite permitido.');
                        }
                    },
                ])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $corpo = $response->body();

            if ($corpo === '' || strlen($corpo) > self::MAX_BYTES_CAPA) {
                return null;
            }

            // O tipo vem do CONTEÚDO, não do cabeçalho Content-Type (que o servidor pode mentir ou errar), e
            // placeholders de 1x1 pixel que alguns serviços devolvem no lugar de "sem capa" são recusados.
            $info = @getimagesizefromstring($corpo);

            if (! is_array($info)) {
                return null;
            }

            $extensao = self::EXTENSOES[$info['mime'] ?? ''] ?? null;
            [$largura, $altura] = [(int) $info[0], (int) $info[1]];

            if ($extensao === null
                || $largura < self::LADO_MINIMO_CAPA || $altura < self::LADO_MINIMO_CAPA
                || $largura > self::LADO_MAXIMO_CAPA || $altura > self::LADO_MAXIMO_CAPA) {
                return null;
            }

            $caminho = self::DIRETORIO_PENDENTES.'/'.$isbn.'.'.$extensao;
            Storage::disk('public')->put($caminho, $corpo);

            return $caminho;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Capa já baixada para este ISBN (qualquer extensão aceita). Se estiver perto de ser limpa, renova a data para
     * que quem está com o formulário aberto não perca o arquivo antes de salvar.
     */
    private function capaPendenteExistente(string $isbn): ?string
    {
        $disco = Storage::disk('public');

        foreach (self::EXTENSOES as $extensao) {
            $caminho = self::DIRETORIO_PENDENTES.'/'.$isbn.'.'.$extensao;

            if ($disco->exists($caminho)) {
                if ($disco->lastModified($caminho) < now()->subHours(24)->getTimestamp()) {
                    $disco->put($caminho, $disco->get($caminho));
                }

                return $caminho;
            }
        }

        return null;
    }

    /**
     * Aceita só http(s) para um host público: IP literal ou nome que resolva apenas para endereços públicos. A URL da
     * capa vem de dados de terceiros e o servidor não pode ser usado para alcançar a rede interna.
     */
    private function urlPublica(string $url): bool
    {
        $partes = parse_url($url);

        if (! is_array($partes) || ! in_array(strtolower($partes['scheme'] ?? ''), ['http', 'https'], true) || empty($partes['host'])) {
            return false;
        }

        if (isset($partes['port']) && ! in_array((int) $partes['port'], [80, 443], true)) {
            return false;
        }

        $host = trim($partes['host'], '[]');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $enderecos = [$host];
        } elseif (config('services.livros.validar_dns', true)) {
            $enderecos = gethostbynamel($host) ?: [];
        } else {
            return ! in_array(strtolower($host), ['localhost'], true) && ! str_ends_with(strtolower($host), '.local');
        }

        if ($enderecos === []) {
            return false;
        }

        foreach ($enderecos as $endereco) {
            if (filter_var($endereco, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    // ------------------------------------------------------------------ Amazon (opt-in)

    /**
     * A raspagem de HTML e o CDN de capas da Amazon são não oficiais, frágeis e contrários aos termos de uso do site:
     * ficam desligados a menos que a escola decida assumir isso (`LIVROS_AMAZON_HABILITADO=true`).
     */
    protected function amazonHabilitada(): bool
    {
        return (bool) config('services.livros.amazon_habilitado', false);
    }

    private function urlCapaAmazon(string $isbn): ?string
    {
        $isbn10 = strlen($isbn) === 10 ? $isbn : $this->converterIsbn13ParaIsbn10($isbn);

        return $isbn10 ? "https://images-na.ssl-images-amazon.com/images/P/{$isbn10}.01.L.jpg" : null;
    }

    /**
     * Consulta a página de detalhes da obra na Amazon para extrair autores, título e editora.
     *
     * @return array{
     *     titulo: ?string,
     *     autor: ?string,
     *     editora: ?string
     * }|null
     */
    protected function consultarAmazon(string $isbn): ?array
    {
        try {
            $isbn10 = strlen($isbn) === 10 ? $isbn : $this->converterIsbn13ParaIsbn10($isbn);
            if (! $isbn10) {
                return null;
            }

            $response = Http::timeout(self::TIMEOUT_FONTE)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept-Language' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
                ])
                ->get("https://www.amazon.com.br/dp/{$isbn10}");

            if (! $response->successful()) {
                return null;
            }

            $html = $response->body();

            // 1. Título
            $titulo = null;
            if (preg_match('/<span id="productTitle"[^>]*>(.*?)<\/span>/si', $html, $m)) {
                $titulo = trim(strip_tags(html_entity_decode($m[1])));
            }

            // 2. Autores e Ilustradores
            preg_match_all('/<span class="author[^"]*"[^>]*>(.*?)<\/span>\s*(?=<\/div>|<span class="author|<span class="more)/si', $html, $matches);
            $autores = [];
            foreach ($matches[0] as $block) {
                if (preg_match('/<a[^>]*>(.*?)<\/a>/si', $block, $nm)) {
                    $nome = trim(strip_tags(html_entity_decode($nm[1])));
                    if (empty($nome) || in_array($nome, ['Capa comum', 'Kindle', 'Livro digital'], true)) {
                        continue;
                    }

                    $textoBloco = mb_strtolower(trim(strip_tags(html_entity_decode($block))));
                    if (str_contains($textoBloco, 'tradutor') || str_contains($textoBloco, 'prefácio')) {
                        continue;
                    }

                    $autores[] = $nome;
                }
            }

            $autor = ! empty($autores) ? implode(', ', array_unique($autores)) : null;

            // Fallback para autores se o span author não encontrou
            if (! $autor && preg_match('/<title>(.*?): Amazon\.com\.br/si', $html, $m)) {
                $parts = explode(':', $m[1]);
                if (count($parts) > 1) {
                    $rawAuthors = trim($parts[1]);
                    if (! empty($rawAuthors)) {
                        $autor = $rawAuthors;
                    }
                }
            }

            // 3. Editora
            $editora = null;
            if (preg_match('/(?:Editora|Publisher)\s*<\/span>\s*<span[^>]*>\s*:\s*<\/span>\s*<span[^>]*>(.*?)<\/span>/si', $html, $em)) {
                $rawEd = trim(strip_tags(html_entity_decode($em[1])));
                if (! empty($rawEd)) {
                    $editora = $rawEd;
                }
            }

            return [
                'titulo' => $titulo,
                'autor' => $autor,
                'editora' => $editora,
            ];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    // ------------------------------------------------------------------ utilidades

    /**
     * @return array{sucesso: false, mensagem: string, dados: array<string, null|string>}
     */
    private function falha(string $isbn, string $mensagem): array
    {
        return [
            'sucesso' => false,
            'mensagem' => $mensagem,
            'dados' => [
                'isbn' => $isbn,
                'titulo' => null,
                'autor' => null,
                'editora' => null,
                'categoria' => null,
                'capa' => null,
                'capa_url' => null,
            ],
        ];
    }

    private function restante(float $inicio): float
    {
        return self::ORCAMENTO_SEGUNDOS - (microtime(true) - $inicio);
    }

    /**
     * Converte um ISBN-13 iniciado em 978 para seu ISBN-10 correspondente.
     */
    public function converterIsbn13ParaIsbn10(string $isbn13): ?string
    {
        if (strlen($isbn13) !== 13 || ! str_starts_with($isbn13, '978')) {
            return null;
        }

        $noveDigitos = substr($isbn13, 3, 9);
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += ((int) $noveDigitos[$i]) * (10 - $i);
        }

        $resto = $soma % 11;
        $dvCalculado = 11 - $resto;

        $dv = match ($dvCalculado) {
            10 => 'X',
            11 => '0',
            default => (string) $dvCalculado,
        };

        return $noveDigitos.$dv;
    }
}
