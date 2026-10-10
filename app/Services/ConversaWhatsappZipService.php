<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ZipArchive;

/**
 * Lê o .zip gerado por "Exportar conversa" do WhatsApp (o .txt da conversa mais as mídias) para a importação de
 * lead por IA.
 *
 * O arquivo vem de terceiros e é aberto sem confiar em nada dele: nenhum nome de entrada vira caminho em disco
 * (as mídias são gravadas com nomes gerados, o que elimina zip-slip), cada leitura tem teto de bytes independente
 * do tamanho declarado no cabeçalho (zip bomb) e o tipo da mídia é decidido pelo conteúdo, não pela extensão.
 * Só o texto, os áudios e as imagens interessam: vídeos, documentos e figurinhas são ignorados.
 *
 * As mídias entram na ordem em que são citadas na conversa, dentro dos limites de quantidade e de bytes da
 * requisição ao Gemini (20 MB no total, com o base64 inchando o conteúdo em ~33%).
 */
class ConversaWhatsappZipService
{
    /** Pasta (no disco `local`) das mídias extraídas; quem chama apaga depois de usar. */
    public const DIRETORIO_TEMPORARIO = 'temp-lead-zip-midias';

    public const MAX_AUDIOS = 10;

    public const MAX_IMAGENS = 10;

    /** Mesmo teto de áudio da análise de conversa: a requisição inteira ao Gemini não passa de 20 MB. */
    public const BYTES_MAXIMOS_MIDIAS = CrmIaVendasService::BYTES_MAXIMOS_AUDIOS;

    /** Acima disso a conversa é lida só pelo começo e pelo fim, para caber no prompt. */
    public const CARACTERES_MAXIMOS_TEXTO = 120000;

    private const BYTES_MAXIMOS_POR_MIDIA = 10 * 1024 * 1024;

    private const BYTES_MAXIMOS_TEXTO = 5 * 1024 * 1024;

    /** O WhatsApp exporta no máximo ~10 mil mídias; muito além disso o arquivo não é uma exportação. */
    private const MAX_ENTRADAS = 20000;

    private const EXTENSOES_AUDIO = ['opus', 'ogg', 'oga', 'mp3', 'm4a', 'aac', 'wav', 'flac', 'aif', 'aiff'];

    private const EXTENSOES_IMAGEM = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];

    private const MIMES_IMAGEM = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];

    /**
     * @param  int  $bytesReservados  Bytes de outros anexos que seguem na mesma requisição (ex.: o print), descontados do teto das mídias.
     * @return array{
     *     texto: string,
     *     midias: list<array{caminho: string, mime: string, nome: string, tipo: 'audio'|'imagem'}>,
     *     totais: array{audio: int, imagem: int},
     *     avisos: list<string>,
     *     diretorio: string,
     * } `texto` já traz o cabeçalho da conversa; `diretorio` é relativo ao disco `local` e deve ser apagado por quem chamou.
     *
     * @throws InvalidArgumentException Quando o arquivo não abre, não é uma exportação do WhatsApp ou é grande demais.
     */
    public function ler(string $caminhoZip, int $bytesReservados = 0): array
    {
        $zip = new ZipArchive;

        if (! is_file($caminhoZip) || $zip->open($caminhoZip, ZipArchive::RDONLY) !== true) {
            throw new InvalidArgumentException('Não foi possível abrir o arquivo .zip. Confira se ele não está corrompido e exporte a conversa novamente pelo WhatsApp.');
        }

        $diretorio = self::DIRETORIO_TEMPORARIO.'/'.Str::uuid();

        try {
            return $this->lerEntradas($zip, $diretorio, max(0, self::BYTES_MAXIMOS_MIDIAS - $bytesReservados));
        } catch (\Throwable $e) {
            Storage::disk('local')->deleteDirectory($diretorio);

            throw $e;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array{texto: string, midias: list<array{caminho: string, mime: string, nome: string, tipo: 'audio'|'imagem'}>, totais: array{audio: int, imagem: int}, avisos: list<string>, diretorio: string}
     */
    private function lerEntradas(ZipArchive $zip, string $diretorio, int $orcamentoBytes): array
    {
        if ($zip->numFiles > self::MAX_ENTRADAS) {
            throw new InvalidArgumentException('O .zip tem arquivos demais para ser uma conversa exportada do WhatsApp.');
        }

        [$textos, $midias] = $this->classificar($zip);

        if ($textos === []) {
            throw new InvalidArgumentException('Não encontrei o texto da conversa (.txt) dentro do .zip. No WhatsApp, abra a conversa e use ⋮ → Mais → Exportar conversa, com ou sem mídia.');
        }

        $arquivoTexto = $this->escolherTexto($textos);

        if ($arquivoTexto['tamanho'] > self::BYTES_MAXIMOS_TEXTO) {
            throw new InvalidArgumentException('O texto da conversa é grande demais para importar. Exporte um período menor.');
        }

        $conteudo = $this->lerTexto($zip, $arquivoTexto['indice']);

        if ($conteudo === '') {
            throw new InvalidArgumentException('A conversa exportada está vazia.');
        }

        $avisos = [];
        $texto = $this->limitarTexto($conteudo, $avisos);

        [$anexadas, $avisosMidia] = $this->extrairMidias($zip, $this->ordenarPelaConversa($midias, $texto), $diretorio, $orcamentoBytes);

        $totais = ['audio' => 0, 'imagem' => 0];
        foreach ($anexadas as $midia) {
            $totais[$midia['tipo']]++;
        }

        return [
            'texto' => $this->cabecalho($arquivoTexto['nome'], $totais).$texto,
            'midias' => $anexadas,
            'totais' => $totais,
            'avisos' => array_merge($avisos, $avisosMidia),
            'diretorio' => $diretorio,
        ];
    }

    /**
     * Separa as entradas úteis: textos e mídias candidatas (áudio e imagem). Pastas, lixo de macOS (`__MACOSX`,
     * `._arquivo`, `.DS_Store`), figurinhas, vídeos e documentos ficam de fora. Só o nome base é usado.
     *
     * @return array{0: list<array{indice: int, nome: string, tamanho: int}>, 1: list<array{indice: int, nome: string, tamanho: int, tipo: 'audio'|'imagem', ext: string}>}
     */
    private function classificar(ZipArchive $zip): array
    {
        $textos = [];
        $midias = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);

            if ($stat === false) {
                continue;
            }

            $caminho = str_replace('\\', '/', (string) $stat['name']);

            if (str_ends_with($caminho, '/') || str_starts_with($caminho, '__MACOSX/') || str_contains($caminho, '/__MACOSX/')) {
                continue;
            }

            $nome = basename($caminho);

            if ($nome === '' || str_starts_with($nome, '.')) {
                continue;
            }

            $entrada = ['indice' => $i, 'nome' => $nome, 'tamanho' => (int) $stat['size']];
            $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));

            if ($ext === 'txt') {
                $textos[] = $entrada;
            } elseif (in_array($ext, self::EXTENSOES_AUDIO, true)) {
                $midias[] = $entrada + ['tipo' => 'audio', 'ext' => $ext];
            } elseif (in_array($ext, self::EXTENSOES_IMAGEM, true) && ! preg_match('/^STK-|-STICKER-/i', $nome)) {
                $midias[] = $entrada + ['tipo' => 'imagem', 'ext' => $ext];
            }
        }

        return [$textos, $midias];
    }

    /**
     * O arquivo da conversa: `_chat.txt` (iPhone) ou "Conversa do WhatsApp com …" (Android); na dúvida, o maior .txt.
     *
     * @param  non-empty-list<array{indice: int, nome: string, tamanho: int}>  $textos
     * @return array{indice: int, nome: string, tamanho: int}
     */
    private function escolherTexto(array $textos): array
    {
        $pontos = fn (array $t): int => match (true) {
            strtolower($t['nome']) === '_chat.txt' => 2,
            stripos($t['nome'], 'whatsapp') !== false => 1,
            default => 0,
        };

        usort($textos, fn (array $a, array $b): int => [$pontos($b), $b['tamanho']] <=> [$pontos($a), $a['tamanho']]);

        return $textos[0];
    }

    private function lerTexto(ZipArchive $zip, int $indice): string
    {
        $fluxo = $zip->getStreamIndex($indice);

        if ($fluxo === false) {
            throw new InvalidArgumentException('Não foi possível ler o texto da conversa dentro do .zip.');
        }

        // O teto vale para o que de fato é lido, não para o tamanho declarado no cabeçalho do .zip.
        $bruto = (string) stream_get_contents($fluxo, self::BYTES_MAXIMOS_TEXTO + 1);
        fclose($fluxo);

        if (strlen($bruto) > self::BYTES_MAXIMOS_TEXTO) {
            throw new InvalidArgumentException('O texto da conversa é grande demais para importar. Exporte um período menor.');
        }

        return $this->normalizarTexto($bruto);
    }

    /**
     * UTF-8 sem BOM, quebras de linha unificadas e sem as marcas invisíveis de direção (U+200E…) e espaços
     * estreitos que o WhatsApp põe antes de data/hora e de anexos.
     */
    private function normalizarTexto(string $bruto): string
    {
        if (str_starts_with($bruto, "\xEF\xBB\xBF")) {
            $bruto = substr($bruto, 3);
        }

        if (! mb_check_encoding($bruto, 'UTF-8')) {
            $bruto = mb_convert_encoding($bruto, 'UTF-8', 'Windows-1252');
        }

        $bruto = str_replace(["\r\n", "\r"], "\n", $bruto);
        $bruto = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{FEFF}]/u', '', $bruto) ?? $bruto;
        $bruto = preg_replace('/[\x{00A0}\x{202F}]/u', ' ', $bruto) ?? $bruto;
        $bruto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $bruto) ?? $bruto;

        return trim($bruto);
    }

    /**
     * Conversas gigantes viram começo + fim: a identificação do lead costuma estar no início e a situação atual
     * no fim.
     *
     * @param  list<string>  $avisos
     */
    private function limitarTexto(string $texto, array &$avisos): string
    {
        $total = mb_strlen($texto);

        if ($total <= self::CARACTERES_MAXIMOS_TEXTO) {
            return $texto;
        }

        $inicio = intdiv(self::CARACTERES_MAXIMOS_TEXTO, 7);
        $fim = self::CARACTERES_MAXIMOS_TEXTO - $inicio;

        $avisos[] = 'A conversa é muito longa ('.number_format($total, 0, ',', '.').' caracteres): a IA leu só o começo e o fim, e o trecho do meio ficou de fora.';

        return rtrim(mb_substr($texto, 0, $inicio))
            ."\n[... trecho intermediário da conversa omitido por tamanho ...]\n"
            .ltrim(mb_substr($texto, -$fim));
    }

    /**
     * Mídias na ordem em que o nome do arquivo aparece na conversa (Android: `PTT-…opus (arquivo anexado)`;
     * iPhone: `<anexo: …opus>`). As que não são citadas vão por último, por nome.
     *
     * @param  list<array{indice: int, nome: string, tamanho: int, tipo: 'audio'|'imagem', ext: string}>  $midias
     * @return list<array{indice: int, nome: string, tamanho: int, tipo: 'audio'|'imagem', ext: string}>
     */
    private function ordenarPelaConversa(array $midias, string $conversa): array
    {
        // Posição calculada uma vez por mídia (e não a cada comparação do sort).
        foreach ($midias as &$midia) {
            $achada = strpos($conversa, $midia['nome']);
            $midia['posicao'] = $achada === false ? PHP_INT_MAX : $achada;
        }
        unset($midia);

        usort($midias, fn (array $a, array $b): int => [$a['posicao'], $a['nome']] <=> [$b['posicao'], $b['nome']]);

        return $midias;
    }

    /**
     * Grava em disco, com nome gerado, as mídias que cabem nos limites e confirma o tipo pelo conteúdo.
     *
     * @param  list<array{indice: int, nome: string, tamanho: int, tipo: 'audio'|'imagem', ext: string}>  $candidatas
     * @return array{0: list<array{caminho: string, mime: string, nome: string, tipo: 'audio'|'imagem'}>, 1: list<string>}
     */
    private function extrairMidias(ZipArchive $zip, array $candidatas, string $diretorio, int $orcamentoBytes): array
    {
        if ($candidatas === []) {
            return [[], []];
        }

        $disco = Storage::disk('local');
        $disco->makeDirectory($diretorio);

        $maximos = ['audio' => self::MAX_AUDIOS, 'imagem' => self::MAX_IMAGENS];
        $aceitas = ['audio' => 0, 'imagem' => 0];
        $omitidas = ['audio' => 0, 'imagem' => 0];
        $semSuporte = ['audio' => 0, 'imagem' => 0];
        $usados = 0;
        $midias = [];

        foreach ($candidatas as $candidata) {
            $tipo = $candidata['tipo'];
            $tamanho = $candidata['tamanho'];

            if ($tamanho === 0) {
                $semSuporte[$tipo]++;

                continue;
            }

            $limite = min(self::BYTES_MAXIMOS_POR_MIDIA, $orcamentoBytes - $usados);

            if ($aceitas[$tipo] >= $maximos[$tipo] || $tamanho > $limite) {
                $omitidas[$tipo]++;

                continue;
            }

            $relativo = sprintf('%s/%s-%02d.%s', $diretorio, $tipo, $aceitas[$tipo] + 1, $candidata['ext']);
            $gravados = $this->gravar($zip, $candidata['indice'], $disco->path($relativo), $limite);

            if ($gravados === null) {
                $omitidas[$tipo]++;

                continue;
            }

            $caminho = $disco->path($relativo);
            $mime = $tipo === 'audio' ? CrmIaVendasService::mimeAudioGemini($caminho) : $this->mimeImagem($caminho);

            if ($mime === null) {
                $disco->delete($relativo);
                $semSuporte[$tipo]++;

                continue;
            }

            $usados += $gravados;
            $aceitas[$tipo]++;
            $midias[] = ['caminho' => $caminho, 'mime' => $mime, 'nome' => $candidata['nome'], 'tipo' => $tipo];
        }

        return [$midias, $this->avisosDeMidia($omitidas, $semSuporte)];
    }

    /**
     * Copia a entrada do .zip para o disco lendo no máximo `$limite` bytes. Devolve null (e não deixa arquivo)
     * se a entrada passa do limite ou não pode ser lida (corrompida, protegida por senha).
     */
    private function gravar(ZipArchive $zip, int $indice, string $destino, int $limite): ?int
    {
        $origem = $zip->getStreamIndex($indice);

        if ($origem === false) {
            return null;
        }

        $saida = fopen($destino, 'wb');

        if ($saida === false) {
            fclose($origem);

            return null;
        }

        $copiados = stream_copy_to_stream($origem, $saida, $limite + 1);
        fclose($origem);
        fclose($saida);

        if ($copiados === false || $copiados === 0 || $copiados > $limite) {
            @unlink($destino);

            return null;
        }

        return $copiados;
    }

    private function mimeImagem(string $caminho): ?string
    {
        $mime = strtolower(trim(explode(';', (string) mime_content_type($caminho))[0]));

        return in_array($mime, self::MIMES_IMAGEM, true) ? $mime : null;
    }

    /**
     * @param  array{audio: int, imagem: int}  $omitidas
     * @param  array{audio: int, imagem: int}  $semSuporte
     * @return list<string>
     */
    private function avisosDeMidia(array $omitidas, array $semSuporte): array
    {
        $avisos = [];

        // A lista vem depois dos dois-pontos para a frase não depender de gênero/número ("1 áudio e 2 imagens").
        if ($omitidas['audio'] + $omitidas['imagem'] > 0) {
            $avisos[] = 'Ficaram de fora da análise pelos limites de uma importação (até '
                .self::MAX_AUDIOS.' áudios, '.self::MAX_IMAGENS.' imagens e '.(int) (self::BYTES_MAXIMOS_MIDIAS / 1024 / 1024).' MB de mídia): '
                .self::descrever($omitidas).'.';
        }

        if ($semSuporte['audio'] + $semSuporte['imagem'] > 0) {
            $avisos[] = 'Ignorados por estarem vazios ou em formato sem suporte: '.self::descrever($semSuporte).'.';
        }

        return $avisos;
    }

    /**
     * "3 áudios e 2 imagens"; vazio quando não há nenhuma.
     *
     * @param  array{audio: int, imagem: int}  $quantidades
     */
    public static function descrever(array $quantidades): string
    {
        $partes = [];

        if ($quantidades['audio'] > 0) {
            $partes[] = $quantidades['audio'].($quantidades['audio'] === 1 ? ' áudio' : ' áudios');
        }

        if ($quantidades['imagem'] > 0) {
            $partes[] = $quantidades['imagem'].($quantidades['imagem'] === 1 ? ' imagem' : ' imagens');
        }

        return implode(' e ', $partes);
    }

    /**
     * Nome do arquivo e mídias anexadas, para o modelo ligar o texto às mídias. O nome vem do .zip (terceiros):
     * só letras, números e pontuação simples entram no prompt.
     *
     * @param  array{audio: int, imagem: int}  $totais
     */
    private function cabecalho(string $nomeArquivo, array $totais): string
    {
        $linhas = ['CONVERSA EXPORTADA DO WHATSAPP (arquivo .zip)', 'Arquivo de texto: "'.self::nomeSeguro($nomeArquivo).'"'];

        if ($totais['audio'] + $totais['imagem'] > 0) {
            $linhas[] = 'Mídias anexadas à análise: '.self::descrever($totais).', na ordem em que aparecem na conversa, cada uma identificada pelo nome do arquivo citado no texto.';
        } else {
            $linhas[] = 'Mídias anexadas à análise: nenhuma (só o texto da conversa).';
        }

        return implode("\n", $linhas)."\n\n";
    }

    public static function nomeSeguro(string $nome): string
    {
        $limpo = preg_replace('/[^\p{L}\p{N} ._+()\-]/u', '', $nome) ?? '';

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $limpo) ?? ''), 0, 120);
    }
}
