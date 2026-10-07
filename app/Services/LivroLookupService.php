<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LivroLookupService
{
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
            return [
                'sucesso' => false,
                'mensagem' => 'O ISBN informado é inválido. Um ISBN deve conter 10 ou 13 dígitos.',
                'dados' => [
                    'isbn' => $cleanIsbn,
                    'titulo' => null,
                    'autor' => null,
                    'editora' => null,
                    'categoria' => null,
                    'capa' => null,
                    'capa_url' => null,
                ],
            ];
        }

        $titulo = null;
        $autor = null;
        $editora = null;
        $categoria = null;
        $capaUrl = null;

        // 1. Tenta buscar no Open Library
        $openLibraryDados = $this->consultarOpenLibrary($cleanIsbn);
        if ($openLibraryDados) {
            $titulo = $openLibraryDados['titulo'] ?? null;
            $autor = $openLibraryDados['autor'] ?? null;
            $editora = $openLibraryDados['editora'] ?? null;
            $categoria = $openLibraryDados['categoria'] ?? null;
            $capaUrl = $openLibraryDados['capa_url'] ?? null;
        }

        // 2. Tenta complementar ou buscar via BrasilAPI
        $brasilApiDados = $this->consultarBrasilApi($cleanIsbn);
        if ($brasilApiDados) {
            $titulo = $titulo ?: ($brasilApiDados['titulo'] ?? null);
            $autor = $autor ?: ($brasilApiDados['autor'] ?? null);
            $editora = $editora ?: ($brasilApiDados['editora'] ?? null);
            $categoria = $categoria ?: ($brasilApiDados['categoria'] ?? null);
            $capaUrl = $capaUrl ?: ($brasilApiDados['capa_url'] ?? null);
        }

        // 3. Tenta Google Books para preencher ou enriquecer dados faltantes (autor, categoria, capa)
        if (! $titulo || ! $autor || ! $capaUrl) {
            $googleDados = $this->consultarGoogleBooks($cleanIsbn);
            if ($googleDados) {
                $titulo = $titulo ?: ($googleDados['titulo'] ?? null);
                $autor = $autor ?: ($googleDados['autor'] ?? null);
                $editora = $editora ?: ($googleDados['editora'] ?? null);
                $categoria = $categoria ?: ($googleDados['categoria'] ?? null);
                $capaUrl = $capaUrl ?: ($googleDados['capa_url'] ?? null);
            }
        }

        // 4. Se ainda faltar autor, título ou editora, consulta a página de detalhes da obra na Amazon
        if (! $titulo || ! $autor || ! $editora) {
            $amazonDados = $this->consultarAmazon($cleanIsbn);
            if ($amazonDados) {
                $titulo = $titulo ?: ($amazonDados['titulo'] ?? null);
                $autor = $autor ?: ($amazonDados['autor'] ?? null);
                $editora = $editora ?: ($amazonDados['editora'] ?? null);
            }
        }

        // 5. Se ainda não tem capaUrl, verifica se existe capa direta no OpenLibrary Covers
        if (! $capaUrl) {
            $capaUrl = $this->verificarCapaOpenLibrary($cleanIsbn);
        }

        // 6. Se ainda não tem capaUrl, verifica na Amazon Covers (com conversão para ISBN-10)
        if (! $capaUrl) {
            $capaUrl = $this->verificarCapaAmazon($cleanIsbn);
        }

        if (! $titulo && ! $autor) {
            return [
                'sucesso' => false,
                'mensagem' => 'Nenhum dado encontrado para o ISBN informado.',
                'dados' => [
                    'isbn' => $cleanIsbn,
                    'titulo' => null,
                    'autor' => null,
                    'editora' => null,
                    'categoria' => null,
                    'capa' => null,
                    'capa_url' => null,
                ],
            ];
        }

        // Se encontrou capaUrl, faz o download para o storage local
        $capaPath = null;
        if ($capaUrl) {
            $capaPath = $this->baixarESalvarCapa($capaUrl, $cleanIsbn);
        }

        return [
            'sucesso' => true,
            'mensagem' => 'Dados do livro localizados com sucesso!',
            'dados' => [
                'isbn' => $cleanIsbn,
                'titulo' => $titulo,
                'autor' => $autor,
                'editora' => $editora,
                'categoria' => $categoria,
                'capa' => $capaPath,
                'capa_url' => $capaUrl,
            ],
        ];
    }

    /**
     * Consulta a API do Open Library (jscmd=data).
     */
    protected function consultarOpenLibrary(string $isbn): ?array
    {
        try {
            $response = Http::timeout(6)
                ->withHeaders(['User-Agent' => 'Torre360/1.0 (biblioteca@torre360.local)'])
                ->get("https://openlibrary.org/api/books?bibkeys=ISBN:{$isbn}&format=json&jscmd=data");

            if (! $response->successful()) {
                return null;
            }

            $json = $response->json();
            $chave = "ISBN:{$isbn}";

            if (empty($json[$chave])) {
                return null;
            }

            $book = $json[$chave];

            $titulo = $book['title'] ?? null;
            if (! empty($book['subtitle'])) {
                $titulo .= ': '.$book['subtitle'];
            }

            $autor = null;
            if (! empty($book['authors'])) {
                $autor = collect($book['authors'])->pluck('name')->filter()->implode(', ');
            }

            $editora = null;
            if (! empty($book['publishers'])) {
                $editora = collect($book['publishers'])->pluck('name')->filter()->first();
            }

            $categoria = null;
            if (! empty($book['subjects'])) {
                $categoria = collect($book['subjects'])->pluck('name')->filter()->first();
            }

            $capaUrl = $book['cover']['large'] ?? $book['cover']['medium'] ?? $book['cover']['small'] ?? null;

            return [
                'titulo' => $titulo,
                'autor' => $autor,
                'editora' => $editora,
                'categoria' => $categoria,
                'capa_url' => $capaUrl,
            ];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Consulta a API da BrasilAPI.
     */
    protected function consultarBrasilApi(string $isbn): ?array
    {
        try {
            $response = Http::timeout(6)
                ->withHeaders(['User-Agent' => 'Torre360/1.0'])
                ->get("https://brasilapi.com.br/api/isbn/v1/{$isbn}");

            if (! $response->successful()) {
                return null;
            }

            $book = $response->json();
            if (empty($book) || empty($book['title'])) {
                return null;
            }

            $titulo = $book['title'];
            if (! empty($book['subtitle'])) {
                $titulo .= ': '.$book['subtitle'];
            }

            $autor = null;
            if (! empty($book['authors']) && is_array($book['authors'])) {
                $autor = collect($book['authors'])->filter()->implode(', ');
            }

            $editora = $book['publisher'] ?? null;

            $categoria = null;
            if (! empty($book['subjects']) && is_array($book['subjects'])) {
                $categoria = collect($book['subjects'])->filter()->first();
            }

            $capaUrl = $book['cover_url'] ?? null;

            return [
                'titulo' => $titulo,
                'autor' => $autor,
                'editora' => $editora,
                'categoria' => $categoria,
                'capa_url' => $capaUrl,
            ];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Consulta a API pública do Google Books.
     */
    protected function consultarGoogleBooks(string $isbn): ?array
    {
        try {
            $params = [
                'q' => "isbn:{$isbn}",
            ];

            $apiKey = config('services.google_books.key') ?? env('GOOGLE_BOOKS_API_KEY');
            if ($apiKey) {
                $params['key'] = $apiKey;
            }

            $response = Http::timeout(6)
                ->withHeaders(['User-Agent' => 'Torre360/1.0'])
                ->get('https://www.googleapis.com/books/v1/volumes', $params);

            if (! $response->successful()) {
                return null;
            }

            $json = $response->json();
            if (empty($json['items'][0]['volumeInfo'])) {
                // Tenta com ISBN-10 se disponível
                $isbn10 = strlen($isbn) === 13 ? $this->converterIsbn13ParaIsbn10($isbn) : null;
                if ($isbn10) {
                    $params['q'] = "isbn:{$isbn10}";
                    $response = Http::timeout(6)
                        ->withHeaders(['User-Agent' => 'Torre360/1.0'])
                        ->get('https://www.googleapis.com/books/v1/volumes', $params);
                    $json = $response->json();
                }
            }

            if (empty($json['items'][0]['volumeInfo'])) {
                return null;
            }

            $volume = $json['items'][0]['volumeInfo'];

            $titulo = $volume['title'] ?? null;
            if (! empty($volume['subtitle'])) {
                $titulo .= ': '.$volume['subtitle'];
            }

            $autor = ! empty($volume['authors']) ? implode(', ', $volume['authors']) : null;
            $editora = $volume['publisher'] ?? null;
            $categoria = ! empty($volume['categories']) ? $volume['categories'][0] : null;

            $capaUrl = $volume['imageLinks']['thumbnail']
                ?? $volume['imageLinks']['smallThumbnail']
                ?? null;

            if ($capaUrl && str_starts_with($capaUrl, 'http://')) {
                $capaUrl = 'https://'.substr($capaUrl, 7);
            }

            return [
                'titulo' => $titulo,
                'autor' => $autor,
                'editora' => $editora,
                'categoria' => $categoria,
                'capa_url' => $capaUrl,
            ];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Verifica se o Open Library Covers possui capa para o ISBN.
     */
    protected function verificarCapaOpenLibrary(string $isbn): ?string
    {
        try {
            $url = "https://covers.openlibrary.org/b/isbn/{$isbn}-L.jpg?default=false";
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'Torre360/1.0'])
                ->head($url);

            if ($response->status() === 200) {
                return $url;
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Faz download da imagem e salva no disco public em livros/capas/.
     */
    public function baixarESalvarCapa(string $url, string $isbn): ?string
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'Torre360/1.0'])
                ->get($url);

            if (! $response->successful() || empty($response->body()) || strlen($response->body()) < 10) {
                return null;
            }

            $contentType = $response->header('Content-Type') ?? '';
            $ext = 'jpg';
            if (str_contains($contentType, 'png')) {
                $ext = 'png';
            } elseif (str_contains($contentType, 'webp')) {
                $ext = 'webp';
            }

            $dir = 'livros/capas';
            $filename = "{$dir}/{$isbn}_".Str::random(8).".{$ext}";

            Storage::disk('public')->put($filename, $response->body());

            return $filename;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Verifica se existe capa na Amazon via ISBN-10 (ou convertendo de ISBN-13).
     */
    protected function verificarCapaAmazon(string $isbn): ?string
    {
        try {
            $isbn10 = strlen($isbn) === 10 ? $isbn : $this->converterIsbn13ParaIsbn10($isbn);
            if (! $isbn10) {
                return null;
            }

            $url = "https://images-na.ssl-images-amazon.com/images/P/{$isbn10}.01.L.jpg";
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'Torre360/1.0'])
                ->get($url);

            // A Amazon retorna um pixel transparente de ~43 bytes quando o livro não tem imagem.
            // Capas reais possuem mais de 1.000 bytes.
            if ($response->successful() && strlen($response->body()) > 1000) {
                return $url;
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
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

            $response = Http::timeout(6)
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
