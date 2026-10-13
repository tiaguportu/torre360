<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoLeituraQuestao;
use App\Enums\ModoAnulacaoQuestao;
use App\Enums\StatusOmrCorrecao;
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
}
