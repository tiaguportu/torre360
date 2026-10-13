<?php

declare(strict_types=1);

namespace App\Services\Omr;

use App\Enums\EstadoLeituraQuestao;
use App\Enums\ModoAnulacaoQuestao;
use App\Enums\StatusOmrCorrecao;
use App\Models\Avaliacao;
use App\Models\AvaliacaoGabarito;
use App\Models\GabaritoFolha;
use App\Models\Nota;
use App\Models\OmrCorrecao;
use App\Models\OmrCorrecaoHabilidade;
use App\Models\User;
use App\Services\NotaLancamentoService;
use App\Support\TiposArquivo;
use GdImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OmrService
{
    public function __construct(
        protected MarcadorDetector $marcadorDetector,
        protected QrCodeLeitor $qrCodeLeitor,
        protected OmrLeitor $omrLeitor,
        protected NotaLancamentoService $notaLancamentoService,
    ) {}

    /**
     * Processa a imagem da folha de respostas, detecta marcações e calcula a nota.
     */
    public function processarImagem(
        Avaliacao $avaliacao,
        string $caminhoArquivoOriginal,
        ?int $userId = null,
        ?int $matriculaIdManual = null,
    ): OmrCorrecao {
        $avaliacao->loadMissing(['gabarito.questoes.habilidade', 'turma.matriculas']);

        $gabarito = $avaliacao->gabarito;
        if (! $gabarito) {
            throw new \InvalidArgumentException('Esta avaliação não possui gabarito cadastrado.');
        }

        if ($avaliacao->nota_maxima === null || (float) $avaliacao->nota_maxima <= 0) {
            throw new \InvalidArgumentException('A avaliação precisa ter uma Nota Máxima definida e maior que zero.');
        }

        // Validação defensiva do arquivo (finfo e tipos permitidos)
        $this->validarArquivoImagem($caminhoArquivoOriginal);

        $inicioMs = (int) round(microtime(true) * 1000);
        $alertas = [];

        // Carrega, orienta por EXIF, redimensiona se necessário e reencoda em JPEG limpo
        $carregamento = $this->carregarEReencodarImagem($caminhoArquivoOriginal);
        $imagem = $carregamento['imagem'];
        $caminhoHigienizado = $carregamento['caminho'];
        $sha256 = $carregamento['sha256'];

        $layout = new GabaritoLayout(
            totalQuestoes: $gabarito->total_questoes,
            alternativas: $gabarito->alternativas,
            versao: $gabarito->layout_versao,
        );

        // 1. Detecção dos 4 marcadores de calibração
        $resultadoMarcadores = $this->marcadorDetector->detectar($imagem);
        $imagemProcessada = $resultadoMarcadores['imagem'];

        if (! $resultadoMarcadores['sucesso']) {
            imagedestroy($imagem);
            if ($imagemProcessada !== $imagem) {
                imagedestroy($imagemProcessada);
            }

            return OmrCorrecao::create([
                'avaliacao_id' => $avaliacao->id,
                'matricula_id' => $matriculaIdManual ?? $this->obterPrimeiraMatriculaId($avaliacao),
                'user_id' => $userId,
                'arquivo_path' => $caminhoHigienizado,
                'arquivo_sha256' => $sha256,
                'status' => StatusOmrCorrecao::Falha,
                'respostas' => [],
                'alertas' => [$resultadoMarcadores['mensagem'] ?? 'Marcadores não identificados.'],
                'motor' => 'gd',
                'layout_versao' => $layout->versao,
                'mensagem_erro' => $resultadoMarcadores['mensagem'],
            ]);
        }

        $marcadores = $resultadoMarcadores['marcadores'];
        $homografia = new HomografiaService(
            tl: $marcadores['TL'],
            tr: $marcadores['TR'],
            br: $marcadores['BR'],
            bl: $marcadores['BL'],
        );

        // 2. Leitura do QR Code opaco da folha
        $uuidFolha = $this->qrCodeLeitor->ler($imagemProcessada, $homografia, $layout);
        $folhaEncontrada = null;
        $matriculaResolvida = null;

        if ($uuidFolha) {
            $folhaEncontrada = GabaritoFolha::where('uuid', $uuidFolha)->first();

            if (! $folhaEncontrada) {
                $alertas[] = "QR Code lido ({$uuidFolha}), mas a folha não consta no banco de dados.";
            } elseif ($folhaEncontrada->avaliacao_id !== $avaliacao->id) {
                $alertas[] = "ATENÇÃO: Esta folha foi gerada para outra avaliação (ID #{$folhaEncontrada->avaliacao_id}).";
            } else {
                $matriculaResolvida = $folhaEncontrada->matricula_id;
                if ($folhaEncontrada->corrigida_em !== null) {
                    $alertas[] = 'Aviso: Esta folha já possui correção prévia registrada no sistema.';
                }
            }
        } else {
            $alertas[] = 'O QR Code da folha não pôde ser decodificado automaticamente.';
        }

        // Se o QR falhar, aplica o preenchimento manual do professor
        if (! $matriculaResolvida && $matriculaIdManual) {
            $matriculaResolvida = $matriculaIdManual;
        }

        if (! $matriculaResolvida) {
            $matriculaResolvida = $this->obterPrimeiraMatriculaId($avaliacao);
            $alertas[] = 'Matrícula não identificada automaticamente. Selecione o aluno correspondente para concluir.';
        }

        // 3. Leitura OMR das bolhas da grade
        $respostasBrutas = $this->omrLeitor->lerRespostas($imagemProcessada, $homografia, $layout);

        imagedestroy($imagem);
        if ($imagemProcessada !== $imagem) {
            imagedestroy($imagemProcessada);
        }

        // 4. Comparação com o gabarito oficial e cálculo da nota
        $calculo = $this->calcularNotaEAcertos($gabarito, (float) $avaliacao->nota_maxima, $respostasBrutas);

        // Alertas de questões ambíguas ou duplas
        foreach ($respostasBrutas as $num => $resp) {
            if ($resp['estado'] === EstadoLeituraQuestao::Dupla) {
                $alertas[] = "Questão {$num}: dupla marcação detectada.";
            } elseif ($resp['estado'] === EstadoLeituraQuestao::Ambigua) {
                $alertas[] = "Questão {$num}: marcação ambígua com preenchimento parcial.";
            }
        }

        $tempoMs = (int) round(microtime(true) * 1000) - $inicioMs;

        $correcao = OmrCorrecao::create([
            'avaliacao_id' => $avaliacao->id,
            'gabarito_folha_id' => $folhaEncontrada?->id,
            'matricula_id' => $matriculaResolvida,
            'user_id' => $userId,
            'arquivo_path' => $caminhoHigienizado,
            'arquivo_sha256' => $sha256,
            'status' => StatusOmrCorrecao::AguardandoConferencia,
            'respostas' => $respostasBrutas,
            'respostas_finais' => $calculo['respostas_finais'],
            'alertas' => ! empty($alertas) ? $alertas : null,
            'acertos' => $calculo['acertos'],
            'total_validas' => $calculo['total_validas'],
            'nota_calculada' => $calculo['nota_final'],
            'motor' => 'gd',
            'layout_versao' => $layout->versao,
            'tempo_ms' => $tempoMs,
        ]);

        // Grava acertos por habilidade BNCC
        foreach ($calculo['habilidades'] as $habId => $dadosHab) {
            OmrCorrecaoHabilidade::create([
                'omr_correcao_id' => $correcao->id,
                'habilidade_id' => $habId,
                'acertos' => $dadosHab['acertos'],
                'total' => $dadosHab['total'],
                'percentual' => $dadosHab['percentual'],
            ]);
        }

        return $correcao;
    }

    /**
     * Lança a nota da correção confirmada no diário oficial através de NotaLancamentoService.
     */
    public function lancarNota(
        OmrCorrecao $correcao,
        ?User $usuarioConfirmador = null,
        bool $forcarSobrescrita = false,
    ): array {
        return DB::transaction(function () use ($correcao, $usuarioConfirmador, $forcarSobrescrita): array {
            // Lock de linha para proteger contra múltiplos cliques simultâneos
            $correcaoLocked = OmrCorrecao::where('id', $correcao->id)->lockForUpdate()->firstOrFail();
            $avaliacao = $correcaoLocked->avaliacao()->lockForUpdate()->firstOrFail();
            $matricula = $correcaoLocked->matricula;

            if ($avaliacao->nota_maxima === null || (float) $avaliacao->nota_maxima <= 0) {
                throw new \InvalidArgumentException('A avaliação não possui nota máxima definida.');
            }

            // Verifica se a data limite de lançamento expirou
            if ($avaliacao->data_limite_lancamento && $avaliacao->data_limite_lancamento->isPast() && ! $avaliacao->data_limite_lancamento->isToday()) {
                throw new \InvalidArgumentException("O prazo limite de lançamento desta avaliação expirou em {$avaliacao->data_limite_lancamento->format('d/m/Y')}.");
            }

            // Verifica se já existe nota anterior lançada
            $notaExistente = Nota::where('avaliacao_id', $avaliacao->id)
                ->where('matricula_id', $matricula->id)
                ->first();

            $novaNota = (float) $correcaoLocked->nota_calculada;

            if ($notaExistente && ! $forcarSobrescrita) {
                $notaAtualValor = $notaExistente->valor !== null ? (float) $notaExistente->valor : null;
                if ($notaAtualValor !== null && abs($notaAtualValor - $novaNota) > 0.001) {
                    return [
                        'sucesso' => false,
                        'conflito' => true,
                        'nota_atual' => $notaAtualValor,
                        'nota_nova' => $novaNota,
                        'mensagem' => "O aluno já possui a nota {$notaAtualValor} lançada. Confirme para sobrescrever para {$novaNota}.",
                    ];
                }
            }

            // Persiste estritamente através do NotaLancamentoService oficial
            $alunoNome = $matricula->pessoa?->nome ?? 'Aluno';
            $dadosLancamento = [
                [
                    'matricula_id' => $matricula->id,
                    'valor' => number_format($novaNota, 2, '.', ''),
                    'situacao' => null,
                    'aluno_nome' => $alunoNome,
                ],
            ];

            $this->notaLancamentoService->salvarNotas($avaliacao, $dadosLancamento);

            // Atualiza status da correção e da folha
            $correcaoLocked->update([
                'status' => StatusOmrCorrecao::Confirmada,
                'confirmada_por_user_id' => $usuarioConfirmador?->id ?? auth()->id(),
                'confirmada_em' => now(),
            ]);

            if ($correcaoLocked->gabarito_folha_id) {
                GabaritoFolha::where('id', $correcaoLocked->gabarito_folha_id)->update([
                    'corrigida_em' => now(),
                ]);
            }

            // Registro de auditoria no ActivityLog
            activity('avaliacao')
                ->performedOn($avaliacao)
                ->causedBy($usuarioConfirmador ?? auth()->user())
                ->withProperties([
                    'matricula_id' => $matricula->id,
                    'nota' => $novaNota,
                    'omr_correcao_id' => $correcaoLocked->id,
                ])
                ->log("Nota de {$alunoNome} lançada via correção de gabarito OMR ({$novaNota}).");

            return [
                'sucesso' => true,
                'conflito' => false,
                'nota_lancada' => $novaNota,
                'mensagem' => "Nota ({$novaNota}) lançada com sucesso no diário.",
            ];
        });
    }

    /**
     * Calcula o total ponderado de acertos e a nota final.
     *
     * @param  array<int, array<string, mixed>>  $respostasBrutas
     * @return array{
     *     acertos: int,
     *     total_validas: int,
     *     nota_final: float,
     *     respostas_finais: array<int, ?string>,
     *     habilidades: array<int, array{acertos: int, total: int, percentual: float}>
     * }
     */
    public function calcularNotaEAcertos(
        AvaliacaoGabarito $gabarito,
        float $notaMaxima,
        array $respostasBrutas,
    ): array {
        $questoesOficiais = $gabarito->questoes()->get()->keyBy('numero');
        $modoAnulacao = $gabarito->anulacao_modo ?? ModoAnulacaoQuestao::PontuaTodos;

        $somaPesosAcertados = 0.0;
        $somaPesosValidos = 0.0;
        $totalAcertos = 0;
        $totalValidas = 0;
        $respostasFinais = [];
        $habilidadesMap = [];

        foreach ($questoesOficiais as $numero => $questao) {
            $peso = (float) $questao->peso;
            $respostaAluno = $respostasBrutas[$numero]['alternativa'] ?? null;
            $respostasFinais[$numero] = $respostaAluno;

            $habId = $questao->habilidade_id;
            if ($habId) {
                $habilidadesMap[$habId] ??= ['acertos' => 0, 'total' => 0];
                $habilidadesMap[$habId]['total']++;
            }

            if ($questao->anulada) {
                if ($modoAnulacao === ModoAnulacaoQuestao::PontuaTodos) {
                    $somaPesosAcertados += $peso;
                    $somaPesosValidos += $peso;
                    $totalAcertos++;
                    $totalValidas++;
                    if ($habId) {
                        $habilidadesMap[$habId]['acertos']++;
                    }
                }

                // Se modo for Desconsidera, não soma no válido nem no acertado
                continue;
            }

            $somaPesosValidos += $peso;
            $totalValidas++;

            $correta = strtoupper(trim((string) $questao->alternativa_correta));
            if ($correta !== '' && $respostaAluno !== null && strtoupper((string) $respostaAluno) === $correta) {
                $somaPesosAcertados += $peso;
                $totalAcertos++;
                if ($habId) {
                    $habilidadesMap[$habId]['acertos']++;
                }
            }
        }

        $notaCalculada = 0.0;
        if ($somaPesosValidos > 0.0) {
            $notaCalculada = round(($somaPesosAcertados / $somaPesosValidos) * $notaMaxima, 2);
        }

        // Consolida habilidades
        $habilidadesResultado = [];
        foreach ($habilidadesMap as $hId => $hData) {
            $percentual = $hData['total'] > 0
                ? round(($hData['acertos'] / $hData['total']) * 100, 2)
                : 0.0;
            $habilidadesResultado[$hId] = [
                'acertos' => $hData['acertos'],
                'total' => $hData['total'],
                'percentual' => $percentual,
            ];
        }

        return [
            'acertos' => $totalAcertos,
            'total_validas' => $totalValidas,
            'nota_final' => $notaCalculada,
            'respostas_finais' => $respostasFinais,
            'habilidades' => $habilidadesResultado,
        ];
    }

    /**
     * Valida mime e extensão do arquivo de forma defensiva.
     */
    protected function validarArquivoImagem(string $caminho): void
    {
        if (! file_exists($caminho)) {
            throw new \InvalidArgumentException('Arquivo de imagem não encontrado no armazenamento.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($caminho);

        if (! in_array($mime, TiposArquivo::imagens(), true)) {
            throw new \InvalidArgumentException("Tipo de arquivo inválido ({$mime}). Envie uma foto JPG, PNG ou WebP.");
        }
    }

    /**
     * Carrega imagem com GD, ajusta EXIF, reduz se necessário e salva cópia limpa.
     *
     * @return array{imagem: GdImage, caminho: string, sha256: string}
     */
    protected function carregarEReencodarImagem(string $caminhoOrigem): array
    {
        $dadosBrutos = file_get_contents($caminhoOrigem);
        if ($dadosBrutos === false) {
            throw new \RuntimeException('Não foi possível ler os dados do arquivo de imagem.');
        }

        $sha256 = hash('sha256', $dadosBrutos);
        $gd = imagecreatefromstring($dadosBrutos);

        if (! $gd instanceof GdImage) {
            throw new \InvalidArgumentException('A imagem enviada não pôde ser decodificada pelo GD.');
        }

        // Aplica orientação EXIF para JPEG
        if (function_exists('exif_read_data')) {
            try {
                $exif = @exif_read_data($caminhoOrigem);
                $orientacao = $exif['Orientation'] ?? null;
                if ($orientacao) {
                    $angulo = match ($orientacao) {
                        3 => 180,
                        6 => -90,
                        8 => 90,
                        default => 0,
                    };
                    if ($angulo !== 0) {
                        $rotacionada = imagerotate($gd, $angulo, 0);
                        if ($rotacionada instanceof GdImage) {
                            imagedestroy($gd);
                            $gd = $rotacionada;
                        }
                    }
                }
            } catch (\Throwable) {
                // Silencia exceções de leitura de EXIF se o arquivo não tiver cabeçalho
            }
        }

        // Reduz se maior que 1600px mantendo aspect ratio
        $w = imagesx($gd);
        $h = imagesy($gd);
        $maxLado = 1600;

        if ($w > $maxLado || $h > $maxLado) {
            $fator = min($maxLado / $w, $maxLado / $h);
            $novoW = (int) round($w * $fator);
            $novoH = (int) round($h * $fator);

            $redimensionada = imagecreatetruecolor($novoW, $novoH);
            imagecopyresampled($redimensionada, $gd, 0, 0, 0, 0, $novoW, $novoH, $w, $h);
            imagedestroy($gd);
            $gd = $redimensionada;
        }

        // Salva arquivo reencodado (limpo de metadados externos) no storage privado
        $nomeSalvo = 'omr/'.date('Y/m').'/'.Str::uuid().'.jpg';
        ob_start();
        imagejpeg($gd, null, 90);
        $jpegData = (string) ob_get_clean();

        Storage::disk('local')->put($nomeSalvo, $jpegData);

        return [
            'imagem' => $gd,
            'caminho' => $nomeSalvo,
            'sha256' => $sha256,
        ];
    }

    protected function obterPrimeiraMatriculaId(Avaliacao $avaliacao): int
    {
        $primeira = $avaliacao->turma?->matriculas()->where('situacao', 'ativa')->first();

        return $primeira?->id ?? 1;
    }
}
