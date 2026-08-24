<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\Unidade;
use App\Models\User;
use App\Services\LeadScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadScoreServiceTest extends TestCase
{
    use RefreshDatabase;

    private function criarInteressado(array $atributos = []): Interessado
    {
        $status = StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1]);
        $origem = OrigemInteressado::create(['nome' => 'Site']);
        $pessoa = Pessoa::factory()->create();
        $usuario = User::factory()->create();

        return Interessado::create(array_merge([
            'pessoa_id' => $pessoa->id,
            'usuario_id' => $usuario->id,
            'origem_interessado_id' => $origem->id,
            'status_interessado_id' => $status->id,
        ], $atributos));
    }

    private function criarSerie(): Serie
    {
        $unidade = Unidade::create(['nome' => 'Unidade Teste']);
        $curso = Curso::create([
            'unidade_id' => $unidade->id,
            'nome_externo' => 'Ensino Fundamental',
            'nome_interno' => 'EF',
        ]);

        return Serie::create([
            'nome' => '1º Ano',
            'curso_id' => $curso->id,
            'sistema_avaliacao' => 'Nota',
        ]);
    }

    public function test_score_sobe_com_numero_de_filhos(): void
    {
        $semFilhos = $this->criarInteressado();
        $comFilhos = $this->criarInteressado();

        $serie = $this->criarSerie();
        InteressadoDependente::create([
            'interessado_id' => $comFilhos->id,
            'nome_crianca' => 'Criança 1',
            'serie_id' => $serie->id,
        ]);

        $this->assertGreaterThan(
            LeadScoreService::calcular($semFilhos),
            LeadScoreService::calcular($comFilhos)
        );
    }

    public function test_score_sobe_com_distancia_mais_proxima_da_escola(): void
    {
        $perto = $this->criarInteressado(['faixa_distancia_escola' => 'ate_2km']);
        $longe = $this->criarInteressado(['faixa_distancia_escola' => 'mais_de_10km']);

        $this->assertGreaterThan(
            LeadScoreService::calcular($longe),
            LeadScoreService::calcular($perto)
        );
    }

    public function test_score_sobe_com_interacoes_bem_sucedidas(): void
    {
        $interessado = $this->criarInteressado();
        $scoreAntes = LeadScoreService::calcular($interessado);

        $tipoContato = TipoContatoInteressado::create(['nome' => 'Telefone']);
        $interessado->historicos()->create([
            'tipo_contato_interessado_id' => $tipoContato->id,
            'relato' => 'Agendou visita à escola.',
            'data_contato' => now(),
            'resultado' => 'agendou_visita',
        ]);

        $scoreDepois = LeadScoreService::calcular($interessado->fresh());

        $this->assertGreaterThan($scoreAntes, $scoreDepois);
    }

    public function test_score_considera_valor_estimado(): void
    {
        $semValor = $this->criarInteressado();
        $comValor = $this->criarInteressado(['valor_estimado' => 5000]);

        $this->assertGreaterThan(
            LeadScoreService::calcular($semValor),
            LeadScoreService::calcular($comValor)
        );
    }

    public function test_recalcular_persiste_score_no_banco(): void
    {
        $interessado = $this->criarInteressado(['valor_estimado' => 5000]);

        $score = LeadScoreService::recalcular($interessado);

        $this->assertDatabaseHas('interessado', [
            'id' => $interessado->id,
            'lead_score' => $score,
        ]);
        $this->assertNotNull($interessado->fresh()->lead_score_atualizado_em);
    }

    public function test_cor_reflete_as_faixas_configuradas(): void
    {
        $this->assertEquals('success', LeadScoreService::cor(80));
        $this->assertEquals('warning', LeadScoreService::cor(50));
        $this->assertEquals('danger', LeadScoreService::cor(10));
        $this->assertEquals('gray', LeadScoreService::cor(null));
    }
}
