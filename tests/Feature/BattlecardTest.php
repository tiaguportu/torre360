<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Concorrentes\Pages\ListConcorrentes;
use App\Filament\Resources\Interessados\Pages\KanbanInteressados;
use App\Filament\Resources\Objecoes\Pages\ListObjecoes;
use App\Models\Concorrente;
use App\Models\Interessado;
use App\Models\Objecao;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Services\BattlecardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BattlecardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private StatusInteressado $statusPerdido;

    private StatusInteressado $statusNovo;

    private OrigemInteressado $origem;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');

        $this->statusNovo = StatusInteressado::create([
            'nome' => 'Novo',
            'cor' => 'info',
            'ordem' => 1,
            'is_final' => false,
            'is_ganho' => false,
        ]);

        $this->statusPerdido = StatusInteressado::create([
            'nome' => 'Perdido',
            'cor' => 'danger',
            'ordem' => 5,
            'is_final' => true,
            'is_ganho' => false,
        ]);

        $this->origem = OrigemInteressado::create(['nome' => 'Instagram']);
        TipoContatoInteressado::firstOrCreate(['nome' => 'Presencial']);
    }

    public function test_pode_criar_concorrente_com_casts_e_metodos(): void
    {
        $concorrente = Concorrente::create([
            'nome' => 'Colégio Exemplo Elite',
            'sigla' => 'CEE',
            'faixa_preco' => 'mais_caro',
            'mensalidade_estimada' => 1950.00,
            'proposta_pedagogica' => 'Ensino tradicional focado em vestibulares.',
            'pontos_fortes' => ['Laboratórios modernos', 'Alta aprovação'],
            'pontos_fracos' => ['Turmas lotadas', 'Sem formação socioemocional'],
            'diferenciais_nossos' => 'Acolhimento individualizado e turmas reduzidas.',
            'estrategia_abordagem' => 'Ressaltar o acompanhamento próximo de cada aluno.',
            'is_ativo' => true,
        ]);

        $this->assertDatabaseHas('crm_concorrentes', [
            'id' => $concorrente->id,
            'nome' => 'Colégio Exemplo Elite',
            'sigla' => 'CEE',
            'faixa_preco' => 'mais_caro',
        ]);

        $this->assertIsArray($concorrente->pontos_fortes);
        $this->assertCount(2, $concorrente->pontos_fortes);
        $this->assertIsArray($concorrente->pontos_fracos);
        $this->assertEquals('Mais caro / Premium', $concorrente->rotuloFaixaPreco());
        $this->assertTrue($concorrente->is_ativo);
    }

    public function test_pode_criar_objecao_com_categoria_e_rotulos(): void
    {
        $objecao = Objecao::create([
            'titulo' => 'Mensalidade fora da minha meta de orçamento',
            'categoria' => 'preco',
            'descricao' => 'Família alega que esperava mensalidades menores.',
            'resposta_sugerida' => 'Entendemos perfeitamente. O que mais pesou na sua decisão além do valor?',
            'pergunta_virada' => 'Se encontrarmos uma condição viável, a escola atende as expectativas da família?',
            'dicas_postura' => 'Não conceder descontos precipitadamente.',
            'ordem' => 1,
            'is_ativo' => true,
        ]);

        $this->assertDatabaseHas('crm_objecoes', [
            'id' => $objecao->id,
            'categoria' => 'preco',
        ]);

        $this->assertEquals('Preço / Financeiro', $objecao->rotuloCategoria());
        $this->assertEquals('rose', $objecao->corCategoria());

        $ativas = Objecao::ativos()->get();
        $this->assertTrue($ativas->contains('id', $objecao->id));

        $porPreco = Objecao::porCategoria('preco')->get();
        $this->assertTrue($porPreco->contains('id', $objecao->id));
    }

    public function test_radar_concorrencia_calcula_ranking_e_fatores_decisao(): void
    {
        $concorrenteA = Concorrente::create([
            'nome' => 'Colégio Alpha',
            'sigla' => 'ALP',
            'faixa_preco' => 'mais_barato',
            'is_ativo' => true,
        ]);

        $concorrenteB = Concorrente::create([
            'nome' => 'Colégio Beta',
            'sigla' => 'BET',
            'faixa_preco' => 'mais_caro',
            'is_ativo' => true,
        ]);

        // Criar 2 perdas para Alpha e 1 perda para Beta
        for ($i = 1; $i <= 2; $i++) {
            $p = Pessoa::factory()->create();
            Interessado::create([
                'pessoa_id' => $p->id,
                'origem_interessado_id' => $this->origem->id,
                'status_interessado_id' => $this->statusPerdido->id,
                'motivo_perda' => 'Concorrência',
                'concorrente_id' => $concorrenteA->id,
                'fator_decisivo_concorrente' => 'Preço / Bolsa',
            ]);
        }

        $pBeta = Pessoa::factory()->create();
        Interessado::create([
            'pessoa_id' => $pBeta->id,
            'origem_interessado_id' => $this->origem->id,
            'status_interessado_id' => $this->statusPerdido->id,
            'motivo_perda' => 'Concorrência',
            'concorrente_id' => $concorrenteB->id,
            'fator_decisivo_concorrente' => 'Localização / Distância',
        ]);

        $service = app(BattlecardService::class);
        $radar = $service->obterRadarConcorrencia();

        $this->assertEquals(2, $radar['total_concorrentes']);
        $this->assertEquals(3, $radar['total_perdas']);

        $ranking = $radar['ranking'];
        $this->assertCount(2, $ranking);
        $this->assertEquals('Colégio Alpha', $ranking->first()['nome']);
        $this->assertEquals(2, $ranking->first()['perdas']);
        $this->assertEquals(66.7, $ranking->first()['percentual']);

        $fatores = $radar['fatores_decisao'];
        $this->assertCount(2, $fatores);
        $this->assertEquals('Preço / Bolsa', $fatores->first()['fator']);
        $this->assertEquals(2, $fatores->first()['total']);
    }

    public function test_kanban_salva_concorrente_e_fator_decisivo_ao_confirmar_perda(): void
    {
        $concorrente = Concorrente::create([
            'nome' => 'Escola Futuro Brilhante',
            'is_ativo' => true,
        ]);

        $pessoa = Pessoa::factory()->create();
        $lead = Interessado::create([
            'pessoa_id' => $pessoa->id,
            'origem_interessado_id' => $this->origem->id,
            'status_interessado_id' => $this->statusNovo->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(KanbanInteressados::class)
            ->call('updateRecordStatus', $lead->id, $this->statusPerdido->id)
            ->set('motivoPerda', 'Concorrência')
            ->set('concorrenteId', $concorrente->id)
            ->set('fatorDecisivoConcorrente', 'Metodologia Pedagógica')
            ->set('observacoesPerda', 'Família optou pelo método construtivista da outra escola.')
            ->call('confirmarPerda')
            ->assertHasNoErrors();

        $lead->refresh();
        $this->assertEquals($this->statusPerdido->id, $lead->status_interessado_id);
        $this->assertEquals($concorrente->id, $lead->concorrente_id);
        $this->assertEquals('Metodologia Pedagógica', $lead->fator_decisivo_concorrente);
        $this->assertStringContainsString('Concorrência: Escola Futuro Brilhante', $lead->motivo_perda);
    }

    public function test_paginas_de_concorrentes_e_objecoes_sao_renderizadas_com_sucesso(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ListConcorrentes::class)
            ->assertSuccessful()
            ->assertActionExists('ajuda');

        Livewire::test(ListObjecoes::class)
            ->assertSuccessful()
            ->assertActionExists('ajuda');
    }

    public function test_modal_battlecards_renderiza_com_sucesso(): void
    {
        $concorrente = Concorrente::create([
            'nome' => 'Colégio Teste View',
            'faixa_preco' => 'equivalente',
            'is_ativo' => true,
        ]);

        $objecao = Objecao::create([
            'titulo' => 'Dúvida Teste',
            'categoria' => 'pedagogico',
            'descricao' => 'Descrição da dúvida da família para visualização.',
            'resposta_sugerida' => 'Roteiro de resposta teste para visualização.',
            'is_ativo' => true,
        ]);

        $pessoa = Pessoa::factory()->create();
        $lead = Interessado::create([
            'pessoa_id' => $pessoa->id,
            'origem_interessado_id' => $this->origem->id,
            'status_interessado_id' => $this->statusNovo->id,
        ]);

        $view = $this->actingAs($this->admin)->view('filament.crm.modal-battlecards', [
            'lead' => $lead,
        ]);

        $view->assertSee('Colégio Teste View');
        $view->assertSee('Dúvida Teste');
        $view->assertSee('Roteiro de resposta teste para visualização');
    }
}
