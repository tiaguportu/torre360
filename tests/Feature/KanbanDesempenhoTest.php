<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\KanbanInteressados;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Models\VisitaInteressado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * O Kanban carregava todos os leads (com 9 relações) a cada coluna e a cada render, incluindo matriculados e
 * perdidos de anos atrás. Agora cada coluna traz só os primeiros cards na ordem de urgência, o total vem de um
 * agregado, e as colunas finais mostram apenas a janela recente.
 */
class KanbanDesempenhoTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private StatusInteressado $novo;

    private StatusInteressado $atendimento;

    private StatusInteressado $matriculado;

    private StatusInteressado $perdido;

    private OrigemInteressado $origem;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'crm.kanban.cards_por_coluna' => 5,
            'crm.kanban.cards_maximo_por_coluna' => 12,
            'crm.kanban.dias_finalizados' => 90,
        ]);

        $this->novo = StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $this->atendimento = StatusInteressado::create(['nome' => 'Em Atendimento', 'cor' => 'warning', 'ordem' => 2, 'is_final' => false, 'is_ganho' => false]);
        $this->matriculado = StatusInteressado::create(['nome' => 'Matriculado', 'cor' => 'success', 'ordem' => 3, 'is_final' => true, 'is_ganho' => true]);
        $this->perdido = StatusInteressado::create(['nome' => 'Perdido', 'cor' => 'danger', 'ordem' => 4, 'is_final' => true, 'is_ganho' => false]);
        $this->origem = OrigemInteressado::create(['nome' => 'Site']);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');
    }

    /**
     * @return Collection<int, Interessado>
     */
    private function leads(StatusInteressado $status, int $quantidade, array $atributos = []): Collection
    {
        return collect(range(1, $quantidade))->map(fn () => Interessado::create($atributos + [
            'pessoa_id' => Pessoa::factory()->create()->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $this->origem->id,
        ]));
    }

    private function coluna(Collection $colunas, StatusInteressado $status): array
    {
        return $colunas->firstWhere(fn (array $coluna) => $coluna['status']->is($status));
    }

    public function test_coluna_mostra_so_o_primeiro_lote_mas_informa_o_total_real(): void
    {
        $this->leads($this->novo, 12);

        $colunas = Livewire::actingAs($this->admin)->test(KanbanInteressados::class)->instance()->colunas();
        $coluna = $this->coluna($colunas, $this->novo);

        $this->assertSame(12, $coluna['total']);
        $this->assertCount(5, $coluna['leads']);
        $this->assertTrue($coluna['tem_mais']);
    }

    public function test_coluna_com_poucos_leads_nao_oferece_carregar_mais(): void
    {
        $this->leads($this->novo, 3);

        $colunas = Livewire::actingAs($this->admin)->test(KanbanInteressados::class)->instance()->colunas();
        $coluna = $this->coluna($colunas, $this->novo);

        $this->assertSame(3, $coluna['total']);
        $this->assertCount(3, $coluna['leads']);
        $this->assertFalse($coluna['tem_mais']);
    }

    public function test_carregar_mais_soma_um_lote_ate_o_teto_configurado(): void
    {
        $this->leads($this->novo, 15);

        $componente = Livewire::actingAs($this->admin)->test(KanbanInteressados::class);
        $quantos = fn () => $this->coluna($componente->instance()->colunas(), $this->novo)['leads']->count();

        $this->assertSame(5, $quantos());

        $componente->call('carregarMais', $this->novo->id);
        $this->assertSame(10, $quantos());

        $componente->call('carregarMais', $this->novo->id);
        $this->assertSame(12, $quantos());

        // Passou do teto (12): continua em 12, o restante fica para a listagem com filtros.
        $componente->call('carregarMais', $this->novo->id);
        $this->assertSame(12, $quantos());
        $this->assertTrue($this->coluna($componente->instance()->colunas(), $this->novo)['tem_mais']);
    }

    public function test_carregar_mais_de_uma_coluna_nao_altera_as_outras(): void
    {
        $this->leads($this->novo, 8);
        $this->leads($this->atendimento, 8);

        $componente = Livewire::actingAs($this->admin)->test(KanbanInteressados::class)
            ->call('carregarMais', $this->novo->id);

        $colunas = $componente->instance()->colunas();

        $this->assertCount(8, $this->coluna($colunas, $this->novo)['leads']);
        $this->assertCount(5, $this->coluna($colunas, $this->atendimento)['leads']);
    }

    public function test_cards_seguem_a_ordem_de_urgencia_e_leads_sem_data_vao_por_ultimo(): void
    {
        $semData = $this->leads($this->novo, 1, ['data_proximo_contato' => null])->first();
        $depois = $this->leads($this->novo, 1, ['data_proximo_contato' => now()->addDays(5)])->first();
        $atrasado = $this->leads($this->novo, 1, ['data_proximo_contato' => now()->subDays(3)])->first();

        $colunas = Livewire::actingAs($this->admin)->test(KanbanInteressados::class)->instance()->colunas();

        $this->assertSame(
            [$atrasado->id, $depois->id, $semData->id],
            $this->coluna($colunas, $this->novo)['leads']->pluck('id')->all()
        );
    }

    public function test_colunas_finais_so_mostram_leads_movidos_na_janela_recente(): void
    {
        $recente = $this->leads($this->matriculado, 1)->first();
        $antigo = $this->leads($this->matriculado, 1)->first();
        $perdidoAntigo = $this->leads($this->perdido, 1)->first();

        DB::table('interessado')->whereIn('id', [$antigo->id, $perdidoAntigo->id])->update(['updated_at' => now()->subDays(200)]);

        $colunas = Livewire::actingAs($this->admin)->test(KanbanInteressados::class)->instance()->colunas();

        $matriculados = $this->coluna($colunas, $this->matriculado);
        $this->assertSame(1, $matriculados['total']);
        $this->assertSame([$recente->id], $matriculados['leads']->pluck('id')->all());
        $this->assertSame(90, $matriculados['janela_dias']);

        $perdidos = $this->coluna($colunas, $this->perdido);
        $this->assertSame(0, $perdidos['total']);
        $this->assertCount(0, $perdidos['leads']);

        // Etapas ativas não têm janela: lead parado há muito tempo continua no funil.
        $parado = $this->leads($this->novo, 1)->first();
        DB::table('interessado')->where('id', $parado->id)->update(['updated_at' => now()->subDays(400)]);

        $colunas = Livewire::actingAs($this->admin)->test(KanbanInteressados::class)->instance()->colunas();
        $this->assertSame([$parado->id], $this->coluna($colunas, $this->novo)['leads']->pluck('id')->all());
        $this->assertNull($this->coluna($colunas, $this->novo)['janela_dias']);
    }

    public function test_total_e_valor_da_coluna_somam_todos_os_leads_e_nao_so_os_exibidos(): void
    {
        $this->leads($this->novo, 7, ['valor_estimado' => 1000]);

        $colunas = Livewire::actingAs($this->admin)->test(KanbanInteressados::class)->instance()->colunas();
        $coluna = $this->coluna($colunas, $this->novo);

        $this->assertCount(5, $coluna['leads']);
        $this->assertSame(7, $coluna['total']);
        $this->assertSame(7000.0, $coluna['valor']);
    }

    public function test_filtro_por_consultor_vale_para_cards_e_totais(): void
    {
        $consultor = User::factory()->create();
        $outro = User::factory()->create();

        $meus = $this->leads($this->novo, 2, ['usuario_id' => $consultor->id]);
        $this->leads($this->novo, 3, ['usuario_id' => $outro->id]);

        $componente = Livewire::actingAs($this->admin)->test(KanbanInteressados::class)->set('filtroConsultorId', $consultor->id);
        $coluna = $this->coluna($componente->instance()->colunas(), $this->novo);

        $this->assertSame(2, $coluna['total']);
        $this->assertEqualsCanonicalizing($meus->pluck('id')->all(), $coluna['leads']->pluck('id')->all());
    }

    public function test_numero_de_consultas_nao_depende_da_quantidade_de_cards(): void
    {
        $tipo = TipoContatoInteressado::firstOrCreate(['nome' => 'Telefone']);

        $enriquecer = function (Interessado $lead) use ($tipo): void {
            InteressadoDependente::create(['interessado_id' => $lead->id, 'nome_crianca' => 'Criança '.$lead->id]);
            VisitaInteressado::factory()->create(['interessado_id' => $lead->id]);
            HistoricoContato::create([
                'interessado_id' => $lead->id,
                'tipo_contato_interessado_id' => $tipo->id,
                'relato' => 'Ligação',
                'data_contato' => now(),
            ]);
        };

        $contarConsultas = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();

            // ProibeLazyLoading falha o teste se algum card dispara carga sob demanda (N+1) ao renderizar.
            Livewire::actingAs($this->admin)->test(KanbanInteressados::class)->assertOk();

            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        $this->leads($this->novo, 1)->each($enriquecer);
        $contarConsultas(); // aquece caches de permissão/configuração

        $poucos = $contarConsultas();

        $this->leads($this->novo, 4)->each($enriquecer);
        $this->leads($this->atendimento, 5)->each($enriquecer);

        $muitos = $contarConsultas();

        $this->assertSame($poucos, $muitos, 'O Kanban voltou a fazer consultas por card.');
    }

    public function test_view_mostra_o_botao_de_carregar_mais_so_quando_ha_cards_ocultos(): void
    {
        $this->leads($this->novo, 7);
        $this->leads($this->atendimento, 2);

        Livewire::actingAs($this->admin)
            ->test(KanbanInteressados::class)
            ->assertSee('Carregar mais (5 de 7)')
            ->assertDontSee('Carregar mais (2 de 2)')
            ->call('carregarMais', $this->novo->id)
            ->assertDontSee('Carregar mais');
    }
}
