<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoLeituraQuestao;
use App\Enums\ModoAnulacaoQuestao;
use App\Enums\StatusOmrCorrecao;
use App\Models\Avaliacao;
use App\Models\AvaliacaoGabarito;
use App\Models\AvaliacaoGabaritoQuestao;
use App\Models\GabaritoFolha;
use App\Models\Habilidade;
use App\Models\Matricula;
use App\Models\Turma;
use App\Services\Omr\GabaritoLayout;
use App\Services\Omr\OmrService;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\CriaAvaliacaoTestTrait;
use Tests\TestCase;

class OmrMotorLeituraTest extends TestCase
{
    use CriaAvaliacaoTestTrait;
    use RefreshDatabase;

    /**
     * Gera uma imagem sintética com GD simulando uma folha de respostas A4 escaneada.
     *
     * @param  array<int, string|null>  $marcacoes  [1 => 'A', 2 => 'B', 3 => 'DUPLA', 4 => null...]
     */
    protected function gerarImagemSintetica(
        GabaritoLayout $layout,
        ?string $uuidQr = null,
        array $marcacoes = [],
        bool $incluirMarcadores = true,
        int $larguraPx = 1050,
        int $alturaPx = 1485,
    ): string {
        $img = imagecreatetruecolor($larguraPx, $alturaPx);
        $branco = imagecolorallocate($img, 255, 255, 255);
        $preto = imagecolorallocate($img, 10, 10, 10);
        imagefilledrectangle($img, 0, 0, $larguraPx, $alturaPx, $branco);

        $escalaX = $larguraPx / GabaritoLayout::LARGURA_A4_MM;
        $escalaY = $alturaPx / GabaritoLayout::ALTURA_A4_MM;

        if ($incluirMarcadores) {
            $marcadores = $layout->marcadores();
            foreach ($marcadores as $canto => $m) {
                $x1 = (int) round($m['x'] * $escalaX);
                $y1 = (int) round($m['y'] * $escalaY);
                $wPx = (int) round($m['w'] * $escalaX);
                $hPx = (int) round($m['h'] * $escalaY);

                imagefilledrectangle($img, $x1, $y1, $x1 + $wPx, $y1 + $hPx, $preto);

                // TL vazado
                if ($m['tipo'] === 'vazado') {
                    $furoW = (int) round(4.0 * $escalaX);
                    $furoH = (int) round(4.0 * $escalaY);
                    $fx = $x1 + (int) round(($wPx - $furoW) / 2);
                    $fy = $y1 + (int) round(($hPx - $furoH) / 2);
                    imagefilledrectangle($img, $fx, $fy, $fx + $furoW, $fy + $furoH, $branco);
                }
            }
        }

        // Desenha QR Code se fornecido
        if ($uuidQr) {
            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel' => QRCode::ECC_M,
                'outputBase64' => false,
            ]);
            $qrData = (new QRCode($options))->render($uuidQr);
            if (is_string($qrData) && str_starts_with($qrData, 'data:')) {
                $partes = explode(',', $qrData, 2);
                $qrData = base64_decode($partes[1] ?? '');
            }
            $qrGd = @imagecreatefromstring((string) $qrData);
            if ($qrGd) {
                $area = $layout->areaQrCode();
                $qrX = (int) round($area['x'] * $escalaX);
                $qrY = (int) round($area['y'] * $escalaY);
                $qrW = (int) round($area['w'] * $escalaX);
                $qrH = (int) round($area['h'] * $escalaY);
                imagecopyresampled($img, $qrGd, $qrX, $qrY, 0, 0, $qrW, $qrH, imagesx($qrGd), imagesy($qrGd));
                imagedestroy($qrGd);
            }
        }

        // Desenha as bolhas da grade
        $grade = $layout->coordenadasBolhas();
        foreach ($grade as $numero => $alternativas) {
            $marcada = $marcacoes[$numero] ?? null;

            foreach ($alternativas as $letra => $ponto) {
                $cx = (int) round($ponto['x'] * $escalaX);
                $cy = (int) round($ponto['y'] * $escalaY);
                $raio = (int) round($ponto['raio'] * $escalaX);

                // Borda da bolha
                imageellipse($img, $cx, $cy, $raio * 2, $raio * 2, $preto);

                // Preenche a bolha se estiver marcada
                if ($marcada === $letra || ($marcada === 'DUPLA' && in_array($letra, ['A', 'B']))) {
                    imagefilledellipse($img, $cx, $cy, $raio * 2, $raio * 2, $preto);
                }
            }
        }

        $caminhoTemp = tempnam(sys_get_temp_dir(), 'omr_test_').'.jpg';
        imagejpeg($img, $caminhoTemp, 95);
        imagedestroy($img);

        return $caminhoTemp;
    }

    public function test_round_trip_omr_detecta_respostas_e_calcula_nota_corretamente(): void
    {
        Storage::fake('local');

        $turma = Turma::factory()->create();
        $avaliacao = $this->criarAvaliacao([
            'turma_id' => $turma->id,
            'nota_maxima' => 10.00,
        ]);
        $gabarito = AvaliacaoGabarito::factory()->create([
            'avaliacao_id' => $avaliacao->id,
            'total_questoes' => 5,
            'alternativas' => 5,
            'anulacao_modo' => ModoAnulacaoQuestao::PontuaTodos,
        ]);

        $hab = Habilidade::factory()->create(['codigo' => 'EF06MA01']);

        // Cadastra gabarito oficial: 1=A, 2=B, 3=C, 4=D, 5=E (questão 5 vinculada a habilidade)
        $oficiais = [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E'];
        foreach ($oficiais as $num => $letra) {
            AvaliacaoGabaritoQuestao::factory()->create([
                'avaliacao_gabarito_id' => $gabarito->id,
                'numero' => $num,
                'alternativa_correta' => $letra,
                'peso' => 2.00, // cada questão vale 2.0 pontos
                'habilidade_id' => $num === 5 ? $hab->id : null,
            ]);
        }

        $matricula = Matricula::factory()->create(['turma_id' => $turma->id]);
        $folha = GabaritoFolha::factory()->create([
            'avaliacao_id' => $avaliacao->id,
            'matricula_id' => $matricula->id,
        ]);

        $layout = new GabaritoLayout(totalQuestoes: 5, alternativas: 5);

        // Aluno marca: 1=A (acerto), 2=B (acerto), 3=A (erro, correta era C), 4=null (em branco), 5=E (acerto)
        // Acertos: 1, 2 e 5 (3 acertos de 5 válidas = 6.00 pontos)
        $marcacoesAluno = [
            1 => 'A',
            2 => 'B',
            3 => 'A',
            4 => null,
            5 => 'E',
        ];

        $imagemPath = $this->gerarImagemSintetica($layout, $folha->uuid, $marcacoesAluno);

        $omrService = app(OmrService::class);
        $correcao = $omrService->processarImagem($avaliacao, $imagemPath, null, $matricula->id);

        @unlink($imagemPath);

        $this->assertSame(StatusOmrCorrecao::AguardandoConferencia, $correcao->status);
        $this->assertEquals(3, $correcao->acertos);
        $this->assertEquals(5, $correcao->total_validas);
        $this->assertEquals(6.00, (float) $correcao->nota_calculada);

        // Respostas gravadas
        $respostas = $correcao->respostas;
        $this->assertSame('A', $respostas[1]['alternativa']);
        $this->assertSame('B', $respostas[2]['alternativa']);
        $this->assertSame('A', $respostas[3]['alternativa']);
        $this->assertNull($respostas[4]['alternativa']);
        $this->assertSame('E', $respostas[5]['alternativa']);

        // Habilidade BNCC gravada
        $this->assertDatabaseHas('omr_correcao_habilidades', [
            'omr_correcao_id' => $correcao->id,
            'habilidade_id' => $hab->id,
            'acertos' => 1,
            'total' => 1,
            'percentual' => 100.00,
        ]);
    }

    public function test_omr_detecta_dupla_marcacao_e_gera_alerta(): void
    {
        Storage::fake('local');

        $avaliacao = $this->criarAvaliacao(['nota_maxima' => 10.00]);
        $gabarito = AvaliacaoGabarito::factory()->create(['avaliacao_id' => $avaliacao->id, 'total_questoes' => 3]);
        AvaliacaoGabaritoQuestao::factory()->create(['avaliacao_gabarito_id' => $gabarito->id, 'numero' => 1, 'alternativa_correta' => 'A']);
        AvaliacaoGabaritoQuestao::factory()->create(['avaliacao_gabarito_id' => $gabarito->id, 'numero' => 2, 'alternativa_correta' => 'B']);
        AvaliacaoGabaritoQuestao::factory()->create(['avaliacao_gabarito_id' => $gabarito->id, 'numero' => 3, 'alternativa_correta' => 'C']);

        $matricula = Matricula::factory()->create(['turma_id' => $avaliacao->turma_id]);
        $layout = new GabaritoLayout(totalQuestoes: 3, alternativas: 5);

        // Questão 2 com dupla marcação
        $imagemPath = $this->gerarImagemSintetica($layout, null, [
            1 => 'A',
            2 => 'DUPLA',
            3 => 'C',
        ]);

        $omrService = app(OmrService::class);
        $correcao = $omrService->processarImagem($avaliacao, $imagemPath, null, $matricula->id);
        @unlink($imagemPath);

        $this->assertNull($correcao->respostas[2]['alternativa']);
        $this->assertSame(EstadoLeituraQuestao::Dupla->value, $correcao->respostas[2]['estado']);
        $this->assertNotEmpty($correcao->alertas);
    }

    public function test_imagem_sem_marcadores_registra_status_falha_com_mensagem_amigavel(): void
    {
        Storage::fake('local');

        $avaliacao = $this->criarAvaliacao(['nota_maxima' => 10.00]);
        AvaliacaoGabarito::factory()->create(['avaliacao_id' => $avaliacao->id]);
        $matricula = Matricula::factory()->create(['turma_id' => $avaliacao->turma_id]);

        $layout = new GabaritoLayout(totalQuestoes: 5);
        $imagemPath = $this->gerarImagemSintetica($layout, null, [], incluirMarcadores: false);

        $omrService = app(OmrService::class);
        $correcao = $omrService->processarImagem($avaliacao, $imagemPath, null, $matricula->id);
        @unlink($imagemPath);

        $this->assertSame(StatusOmrCorrecao::Falha, $correcao->status);
        $this->assertStringContainsString('marcadores', $correcao->mensagem_erro);
    }

    public function test_arquivo_nao_imagem_e_rejeitado(): void
    {
        $avaliacao = $this->criarAvaliacao(['nota_maxima' => 10.00]);
        AvaliacaoGabarito::factory()->create(['avaliacao_id' => $avaliacao->id]);

        $arquivoFake = tempnam(sys_get_temp_dir(), 'txt_');
        file_put_contents($arquivoFake, 'arquivo de texto simples');

        $omrService = app(OmrService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tipo de arquivo inválido');

        try {
            $omrService->processarImagem($avaliacao, $arquivoFake);
        } finally {
            @unlink($arquivoFake);
        }
    }

    public function test_folha_com_qr_code_ilegivel_ou_ausente_resulta_em_matricula_id_nulo(): void
    {
        Storage::fake('local');

        $turma = Turma::factory()->create();
        $avaliacao = $this->criarAvaliacao([
            'turma_id' => $turma->id,
            'nota_maxima' => 10.00,
        ]);
        $gabarito = AvaliacaoGabarito::factory()->create([
            'avaliacao_id' => $avaliacao->id,
            'total_questoes' => 3,
        ]);
        AvaliacaoGabaritoQuestao::factory()->create(['avaliacao_gabarito_id' => $gabarito->id, 'numero' => 1, 'alternativa_correta' => 'A']);
        AvaliacaoGabaritoQuestao::factory()->create(['avaliacao_gabarito_id' => $gabarito->id, 'numero' => 2, 'alternativa_correta' => 'B']);
        AvaliacaoGabaritoQuestao::factory()->create(['avaliacao_gabarito_id' => $gabarito->id, 'numero' => 3, 'alternativa_correta' => 'C']);

        // Cria matrícula na turma, mas o gabarito é sintético SEM QR (ou QR ausente) e sem matrícula manual
        Matricula::factory()->create(['turma_id' => $turma->id]);

        $layout = new GabaritoLayout(totalQuestoes: 3, alternativas: 5);
        $imagemPath = $this->gerarImagemSintetica($layout, null, [
            1 => 'A',
            2 => 'B',
            3 => 'C',
        ]);

        $omrService = app(OmrService::class);
        $correcao = $omrService->processarImagem($avaliacao, $imagemPath, null, null);
        @unlink($imagemPath);

        $this->assertNull($correcao->matricula_id, 'Matrícula deve ser nula quando QR não é lido e não há seleção manual.');
        $this->assertSame(StatusOmrCorrecao::AguardandoConferencia, $correcao->status);
        $this->assertNotEmpty($correcao->alertas);
        $this->assertTrue(collect($correcao->alertas)->contains(fn ($alerta) => str_contains($alerta, 'Matrícula não identificada')));
    }

    /**
     * Avaliação com gabarito de 3 questões (A, B, C), 5 alternativas e nota máxima 10.
     *
     * @return array{0: Avaliacao, 1: Matricula, 2: GabaritoLayout}
     */
    private function avaliacaoDeTresQuestoes(): array
    {
        Storage::fake('local');

        $turma = Turma::factory()->create();
        $avaliacao = $this->criarAvaliacao(['turma_id' => $turma->id, 'nota_maxima' => 10.00]);
        $gabarito = AvaliacaoGabarito::factory()->create([
            'avaliacao_id' => $avaliacao->id,
            'total_questoes' => 3,
            'alternativas' => 5,
        ]);

        foreach ([1 => 'A', 2 => 'B', 3 => 'C'] as $numero => $letra) {
            AvaliacaoGabaritoQuestao::factory()->create([
                'avaliacao_gabarito_id' => $gabarito->id,
                'numero' => $numero,
                'alternativa_correta' => $letra,
                'peso' => 1.00,
            ]);
        }

        return [
            $avaliacao,
            Matricula::factory()->create(['turma_id' => $turma->id]),
            new GabaritoLayout(totalQuestoes: 3, alternativas: 5),
        ];
    }

    /**
     * Gira a imagem (ângulo anti-horário, como `imagerotate`) preenchendo o fundo de branco e a regrava em JPEG.
     */
    private function girarImagem(string $caminho, float $angulo): string
    {
        $origem = imagecreatefromjpeg($caminho);
        $branco = imagecolorallocate($origem, 255, 255, 255);
        $girada = imagerotate($origem, $angulo, $branco);
        imagedestroy($origem);

        $saida = tempnam(sys_get_temp_dir(), 'omr_rot_').'.jpg';
        imagejpeg($girada, $saida, 95);
        imagedestroy($girada);
        @unlink($caminho);

        return $saida;
    }

    /**
     * @return array<string, array{0: float}>
     */
    public static function rotacoesDaFoto(): array
    {
        return [
            'de cabeça para baixo (180°)' => [180.0],
            'de lado (90° anti-horário)' => [90.0],
            'de lado (90° horário)' => [-90.0],
        ];
    }

    #[DataProvider('rotacoesDaFoto')]
    public function test_folha_fotografada_girada_e_lida_na_orientacao_correta(float $angulo): void
    {
        [$avaliacao, $matricula, $layout] = $this->avaliacaoDeTresQuestoes();

        $imagem = $this->gerarImagemSintetica($layout, null, [1 => 'A', 2 => 'B', 3 => 'E']);
        $imagem = $this->girarImagem($imagem, $angulo);

        $correcao = app(OmrService::class)->processarImagem($avaliacao, $imagem, null, $matricula->id);
        @unlink($imagem);

        $this->assertSame(StatusOmrCorrecao::AguardandoConferencia, $correcao->status, (string) $correcao->mensagem_erro);
        $this->assertSame('A', $correcao->respostas[1]['alternativa']);
        $this->assertSame('B', $correcao->respostas[2]['alternativa']);
        $this->assertSame('E', $correcao->respostas[3]['alternativa']);
        $this->assertEquals(2, $correcao->acertos);
    }

    public function test_pequena_inclinacao_da_foto_e_tolerada(): void
    {
        [$avaliacao, $matricula, $layout] = $this->avaliacaoDeTresQuestoes();

        $imagem = $this->gerarImagemSintetica($layout, null, [1 => 'A', 2 => 'B', 3 => 'C']);
        $imagem = $this->girarImagem($imagem, 3.0);

        $correcao = app(OmrService::class)->processarImagem($avaliacao, $imagem, null, $matricula->id);
        @unlink($imagem);

        $this->assertSame(StatusOmrCorrecao::AguardandoConferencia, $correcao->status, (string) $correcao->mensagem_erro);
        $this->assertEquals(3, $correcao->acertos);
    }

    public function test_marcacao_fraca_nao_vira_resposta_valida_e_pede_conferencia(): void
    {
        [$avaliacao, $matricula, $layout] = $this->avaliacaoDeTresQuestoes();

        // A leitura amostra 80% do raio da bolha. Um preenchimento de 0,44 do raio cobre cerca de 30% dessa área:
        // entre o limiar de vazia (0,18) e o de marcada (0,42), isto é, uma marcação fraca de verdade.
        $caminho = $this->gerarImagemSintetica($layout, null, [1 => 'A', 3 => 'C']);
        $img = imagecreatefromjpeg($caminho);
        $preto = imagecolorallocate($img, 10, 10, 10);
        $ponto = $layout->coordenadasBolhas()[2]['B'];
        $escala = imagesx($img) / GabaritoLayout::LARGURA_A4_MM;
        $diametroFraco = (int) round($ponto['raio'] * $escala * 2 * 0.44);
        imagefilledellipse($img, (int) round($ponto['x'] * $escala), (int) round($ponto['y'] * $escala), $diametroFraco, $diametroFraco, $preto);
        imagejpeg($img, $caminho, 95);
        imagedestroy($img);

        $correcao = app(OmrService::class)->processarImagem($avaliacao, $caminho, null, $matricula->id);
        @unlink($caminho);

        $estado = $correcao->respostas[2]['estado'];
        $this->assertContains($estado, [EstadoLeituraQuestao::Ambigua->value, EstadoLeituraQuestao::EmBranco->value], "Marcação fraca lida como '{$estado}'.");
        $this->assertNotSame(EstadoLeituraQuestao::Valida->value, $estado);
    }

    public function test_imagem_com_dimensao_excessiva_e_rejeitada_antes_de_decodificar(): void
    {
        [$avaliacao] = $this->avaliacaoDeTresQuestoes();

        // 10.001 × 10 px: passa no tipo e no peso, mas excede a resolução máxima aceita.
        $img = imagecreatetruecolor(10001, 10);
        imagefilledrectangle($img, 0, 0, 10001, 10, imagecolorallocate($img, 255, 255, 255));
        $caminho = tempnam(sys_get_temp_dir(), 'omr_big_').'.jpg';
        imagejpeg($img, $caminho, 50);
        imagedestroy($img);

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('resolução máxima');
            app(OmrService::class)->processarImagem($avaliacao, $caminho);
        } finally {
            @unlink($caminho);
        }
    }

    public function test_qr_de_folha_de_outra_avaliacao_alerta_e_nao_identifica_o_aluno(): void
    {
        [$avaliacao, , $layout] = $this->avaliacaoDeTresQuestoes();

        $outraAvaliacao = $this->criarAvaliacao(['nota_maxima' => 10.00]);
        $folhaAlheia = GabaritoFolha::factory()->create([
            'avaliacao_id' => $outraAvaliacao->id,
            'matricula_id' => Matricula::factory()->create(['turma_id' => $outraAvaliacao->turma_id])->id,
        ]);

        $imagem = $this->gerarImagemSintetica($layout, $folhaAlheia->uuid, [1 => 'A', 2 => 'B', 3 => 'C']);
        $correcao = app(OmrService::class)->processarImagem($avaliacao, $imagem);
        @unlink($imagem);

        $this->assertNull($correcao->matricula_id, 'Folha de outra avaliação não pode identificar o aluno.');
        $this->assertNull($correcao->gabarito_folha_id);
        $this->assertTrue(collect($correcao->alertas)->contains(fn ($a) => str_contains((string) $a, 'outra avaliação')));
    }

    public function test_folha_ja_corrigida_gera_aviso_de_correcao_previa(): void
    {
        [$avaliacao, $matricula, $layout] = $this->avaliacaoDeTresQuestoes();

        $folha = GabaritoFolha::factory()->create([
            'avaliacao_id' => $avaliacao->id,
            'matricula_id' => $matricula->id,
            'corrigida_em' => now()->subDay(),
        ]);

        $imagem = $this->gerarImagemSintetica($layout, $folha->uuid, [1 => 'A', 2 => 'B', 3 => 'C']);
        $correcao = app(OmrService::class)->processarImagem($avaliacao, $imagem);
        @unlink($imagem);

        $this->assertSame($matricula->id, $correcao->matricula_id);
        $this->assertTrue(collect($correcao->alertas)->contains(fn ($a) => str_contains((string) $a, 'correção prévia')));
    }

    public function test_mesma_imagem_enviada_duas_vezes_alerta_sobre_duplicidade(): void
    {
        [$avaliacao, $matricula, $layout] = $this->avaliacaoDeTresQuestoes();

        $imagem = $this->gerarImagemSintetica($layout, null, [1 => 'A', 2 => 'B', 3 => 'C']);
        $service = app(OmrService::class);

        $primeira = $service->processarImagem($avaliacao, $imagem, null, $matricula->id);
        $segunda = $service->processarImagem($avaliacao, $imagem, null, $matricula->id);
        @unlink($imagem);

        $this->assertFalse(collect($primeira->alertas ?? [])->contains(fn ($a) => str_contains((string) $a, 'mesma imagem')));
        $this->assertTrue(collect($segunda->alertas ?? [])->contains(fn ($a) => str_contains((string) $a, 'mesma imagem')));
    }
}
