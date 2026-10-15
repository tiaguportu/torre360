<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoMatricula;
use App\Jobs\GerarFolhasTurmaPdfJob;
use App\Models\AvaliacaoGabarito;
use App\Models\GabaritoFolha;
use App\Models\Matricula;
use App\Models\Turma;
use App\Models\User;
use App\Services\Omr\FolhaGabaritoPdfService;
use App\Services\Omr\GabaritoLayout;
use App\Services\Omr\HomografiaService;
use App\Services\Omr\QrCodeLeitor;
use App\Services\QrCodeService;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Feature\Concerns\CriaAvaliacaoTestTrait;
use Tests\TestCase;

class OmrGabaritoPdfTest extends TestCase
{
    use CriaAvaliacaoTestTrait;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::firstOrCreate(['name' => 'GerenciarGabarito:Avaliacao', 'guard_name' => 'web']);
    }

    public function test_layout_gabarito_e_deterministico_por_versao(): void
    {
        $layout1 = new GabaritoLayout(totalQuestoes: 20, alternativas: 5, versao: 1);
        $layout2 = new GabaritoLayout(totalQuestoes: 20, alternativas: 5, versao: 1);

        $this->assertSame($layout1->toArray(), $layout2->toArray());
        $this->assertCount(20, $layout1->coordenadasBolhas());
        $this->assertSame(2, $layout1->numeroColunas());
    }

    public function test_geracao_de_pdf_de_folha_individual_e_valida(): void
    {
        Storage::fake('local');

        $avaliacao = $this->criarAvaliacao(['nota_maxima' => 10.00]);
        AvaliacaoGabarito::factory()->create([
            'avaliacao_id' => $avaliacao->id,
            'total_questoes' => 10,
        ]);

        $matricula = Matricula::factory()->create(['turma_id' => $avaliacao->turma_id]);
        $folha = GabaritoFolha::factory()->create([
            'avaliacao_id' => $avaliacao->id,
            'matricula_id' => $matricula->id,
        ]);

        $service = app(FolhaGabaritoPdfService::class);
        $pdf = $service->gerarPdfFolha($folha);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_geracao_de_folhas_da_turma_inteira_e_job(): void
    {
        Storage::fake('local');

        $turma = Turma::factory()->create();
        $avaliacao = $this->criarAvaliacao([
            'turma_id' => $turma->id,
            'nota_maxima' => 10.00,
        ]);
        AvaliacaoGabarito::factory()->create([
            'avaliacao_id' => $avaliacao->id,
            'total_questoes' => 10,
        ]);

        // Cria 3 alunos ativos
        Matricula::factory()->count(3)->create([
            'turma_id' => $turma->id,
            'situacao' => SituacaoMatricula::ATIVA,
        ]);

        $user = User::factory()->create();

        // Roda o job
        $job = new GerarFolhasTurmaPdfJob($avaliacao->id, $user->id);
        app()->call([$job, 'handle']);

        // Verifica que foram geradas as 3 folhas no banco com UUIDs únicos
        $this->assertDatabaseCount('gabarito_folhas', 3);
        $folhas = GabaritoFolha::where('avaliacao_id', $avaliacao->id)->get();
        $this->assertCount(3, $folhas);
        $this->assertNotEmpty($folhas->first()->uuid);
    }

    public function test_avaliacao_sem_nota_maxima_rejeita_geracao_de_folhas(): void
    {
        $avaliacao = $this->criarAvaliacao(['nota_maxima' => null]);
        AvaliacaoGabarito::factory()->create(['avaliacao_id' => $avaliacao->id]);

        $service = app(FolhaGabaritoPdfService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nota Máxima');
        $service->gerarPdfTurma($avaliacao);
    }

    public function test_download_de_folha_sem_permissao_retorna_403(): void
    {
        $user = User::factory()->create();
        $avaliacao = $this->criarAvaliacao(['nota_maxima' => 10.00]);
        $folha = GabaritoFolha::factory()->create(['avaliacao_id' => $avaliacao->id]);

        $response = $this->actingAs($user)->get(route('omr.folha.pdf', ['folha' => $folha->id]));

        $response->assertStatus(403);
    }

    public function test_qrcode_gerado_e_decodificado_pelo_leitor(): void
    {
        $uuid = (string) Str::uuid();
        $qrService = app(QrCodeService::class);
        $dataUri = $qrService->renderDataUri($uuid);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);

        $options = new QROptions([
            'version' => QRCode::VERSION_AUTO,
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_M,
            'scale' => 6,
            'outputBase64' => false,
        ]);
        $pngBlob = (new QRCode($options))->render($uuid);

        $gd = imagecreatefromstring($pngBlob);
        $this->assertNotFalse($gd);

        $layout = new GabaritoLayout(totalQuestoes: 20, alternativas: 5);
        $area = $layout->areaQrCode();

        $w = 1600;
        $h = (int) round(1600 * (297 / 210));
        $canvas = imagecreatetruecolor($w, $h);
        $branco = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $branco);

        $qrX = (int) round(($area['x'] / 210) * $w);
        $qrY = (int) round(($area['y'] / 297) * $h);
        $qrW = (int) round(($area['w'] / 210) * $w);
        $qrH = (int) round(($area['h'] / 297) * $h);

        imagecopyresampled($canvas, $gd, $qrX, $qrY, 0, 0, $qrW, $qrH, imagesx($gd), imagesy($gd));
        imagedestroy($gd);

        $homografia = new HomografiaService(
            tl: ['x' => (int) round(17 / 210 * $w), 'y' => (int) round(17 / 297 * $h)],
            tr: ['x' => (int) round(193 / 210 * $w), 'y' => (int) round(17 / 297 * $h)],
            br: ['x' => (int) round(193 / 210 * $w), 'y' => (int) round(280 / 297 * $h)],
            bl: ['x' => (int) round(17 / 210 * $w), 'y' => (int) round(280 / 297 * $h)],
        );

        $leitor = new QrCodeLeitor;
        $lido = $leitor->ler($canvas, $homografia, $layout);
        imagedestroy($canvas);

        $this->assertSame($uuid, $lido);
    }
}
