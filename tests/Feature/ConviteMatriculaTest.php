<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\Unidade;
use App\Services\ConviteMatriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConviteMatriculaTest extends TestCase
{
    use RefreshDatabase;

    private function criarInteressadoComDependente(): Interessado
    {
        $status = StatusInteressado::query()->firstOrCreate(
            ['nome' => 'Novo'],
            ['is_final' => false, 'is_ganho' => false, 'ordem' => 1]
        );
        $origem = OrigemInteressado::firstOrCreate(['nome' => 'Site']);

        $unidade = Unidade::create(['nome' => 'Unidade Sede '.uniqid()]);
        $curso = Curso::create(['nome_externo' => 'Fundamental', 'nome_interno' => 'Fundamental', 'unidade_id' => $unidade->id]);
        $serie = Serie::create(['nome' => '1º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);

        $pessoa = Pessoa::create(['nome' => 'Responsável Convite '.uniqid(), 'email' => uniqid().'@example.com', 'telefone' => '11988887777']);
        $interessado = Interessado::create([
            'pessoa_id' => $pessoa->id,
            'origem_interessado_id' => $origem->id,
            'status_interessado_id' => $status->id,
        ]);

        InteressadoDependente::create([
            'interessado_id' => $interessado->id,
            'nome_crianca' => 'Criança Convite',
            'serie_id' => $serie->id,
        ]);

        return $interessado->fresh(['pessoa', 'dependentes']);
    }

    public function test_gera_token_com_expiracao_futura(): void
    {
        $interessado = $this->criarInteressadoComDependente();

        $link = app(ConviteMatriculaService::class)->gerarConvite($interessado);

        $interessado->refresh();
        $this->assertNotNull($interessado->token_convite);
        $this->assertTrue($interessado->token_convite_expira_em->isFuture());
        $this->assertNull($interessado->token_convite_usado_em);
        $this->assertStringContainsString($interessado->token_convite, $link);
    }

    public function test_validar_token_retorna_null_para_token_inexistente(): void
    {
        $this->assertNull(app(ConviteMatriculaService::class)->validarToken('token-que-nao-existe'));
    }

    public function test_validar_token_retorna_null_para_token_expirado(): void
    {
        $interessado = $this->criarInteressadoComDependente();
        app(ConviteMatriculaService::class)->gerarConvite($interessado);
        $interessado->update(['token_convite_expira_em' => now()->subDay()]);

        $this->assertNull(app(ConviteMatriculaService::class)->validarToken($interessado->token_convite));
    }

    public function test_validar_token_retorna_null_para_token_ja_usado(): void
    {
        $interessado = $this->criarInteressadoComDependente();
        app(ConviteMatriculaService::class)->gerarConvite($interessado);
        $interessado->update(['token_convite_usado_em' => now()]);

        $this->assertNull(app(ConviteMatriculaService::class)->validarToken($interessado->token_convite));
    }

    public function test_confirmar_atualiza_dados_e_marca_token_como_usado(): void
    {
        $interessado = $this->criarInteressadoComDependente();
        app(ConviteMatriculaService::class)->gerarConvite($interessado);
        $dependente = $interessado->dependentes->first();

        $outraSerie = Serie::create(['nome' => '2º Ano', 'curso_id' => $dependente->serie->curso_id, 'sistema_avaliacao' => 'Nota']);

        app(ConviteMatriculaService::class)->confirmar(
            $interessado,
            ['telefone' => '11999990000', 'email' => 'novoemail@example.com'],
            [['id' => $dependente->id, 'serie_id' => $outraSerie->id, 'turno_preferencia' => 'Manhã']]
        );

        $interessado->refresh();
        $this->assertNotNull($interessado->token_convite_usado_em);
        $this->assertEquals('11999990000', $interessado->pessoa->fresh()->telefone);
        $this->assertEquals('novoemail@example.com', $interessado->pessoa->fresh()->email);
        $this->assertEquals($outraSerie->id, $dependente->fresh()->serie_id);
        $this->assertDatabaseHas('historico_contato', ['interessado_id' => $interessado->id]);
    }

    public function test_confirmar_ignora_dependente_de_outro_interessado(): void
    {
        $interessado = $this->criarInteressadoComDependente();
        app(ConviteMatriculaService::class)->gerarConvite($interessado);

        $outroInteressado = $this->criarInteressadoComDependente();
        $dependenteAlheio = $outroInteressado->dependentes->first();
        $serieOriginal = $dependenteAlheio->serie_id;

        $outraSerie = Serie::create(['nome' => '3º Ano', 'curso_id' => $dependenteAlheio->serie->curso_id, 'sistema_avaliacao' => 'Nota']);

        app(ConviteMatriculaService::class)->confirmar(
            $interessado,
            [],
            [['id' => $dependenteAlheio->id, 'serie_id' => $outraSerie->id]]
        );

        $this->assertEquals($serieOriginal, $dependenteAlheio->fresh()->serie_id);
    }
}
