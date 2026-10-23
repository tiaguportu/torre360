<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InteressadoRedesSociaisEPessoaTelefoneTest extends TestCase
{
    use RefreshDatabase;

    private StatusInteressado $status;

    private OrigemInteressado $origem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->status = StatusInteressado::firstOrCreate(
            ['nome' => 'Novo Lead'],
            ['cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false],
        );

        $this->origem = OrigemInteressado::firstOrCreate(['nome' => 'Site']);
    }

    /**
     * Testa persistência e recuperação do campo redes_sociais no model Interessado.
     */
    public function test_interessado_persiste_e_recupera_redes_sociais_como_array(): void
    {
        $pessoa = Pessoa::factory()->create();

        $redes = [
            ['rede' => 'instagram', 'url' => 'https://instagram.com/colegio.teste'],
            ['rede' => 'linkedin', 'url' => 'https://linkedin.com/company/colegio-teste'],
        ];

        $interessado = Interessado::create([
            'pessoa_id' => $pessoa->id,
            'origem_interessado_id' => $this->origem->id,
            'status_interessado_id' => $this->status->id,
            'redes_sociais' => $redes,
        ]);

        $this->assertDatabaseHas('interessado', [
            'id' => $interessado->id,
        ]);

        $carregado = Interessado::find($interessado->id);
        $this->assertIsArray($carregado->redes_sociais);
        $this->assertCount(2, $carregado->redes_sociais);
        $this->assertSame('instagram', $carregado->redes_sociais[0]['rede']);
        $this->assertSame('https://instagram.com/colegio.teste', $carregado->redes_sociais[0]['url']);
    }

    /**
     * Testa persistência de telefones nos padrões brasileiros (fixo e celular com DDD).
     */
    public function test_pessoa_persiste_telefones_padrao_brasil(): void
    {
        $pessoaFixo = Pessoa::factory()->create([
            'telefone' => '(11) 3333-4444',
        ]);

        $pessoaCelular = Pessoa::factory()->create([
            'telefone' => '(11) 98888-7777',
        ]);

        $this->assertDatabaseHas('pessoa', [
            'id' => $pessoaFixo->id,
            'telefone' => '(11) 3333-4444',
        ]);

        $this->assertDatabaseHas('pessoa', [
            'id' => $pessoaCelular->id,
            'telefone' => '(11) 98888-7777',
        ]);
    }
}
