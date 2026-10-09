<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusVisitaInteressado;
use App\Filament\Resources\Interessados\Pages\EditInteressado;
use App\Filament\Resources\Interessados\RelationManagers\TimelineRelationManager;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Models\VisitaInteressado;
use App\Services\Customer360TimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Customer360TimelineLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Interessado $interessado;

    protected TipoContatoInteressado $tipoWhatsapp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $status = StatusInteressado::create(['nome' => 'Novo', 'ordem' => 1]);
        $pessoa = Pessoa::factory()->create(['telefone' => '11988887777']);

        $this->interessado = Interessado::factory()->create([
            'pessoa_id' => $pessoa->id,
            'usuario_id' => $this->user->id,
            'status_interessado_id' => $status->id,
        ]);

        $this->tipoWhatsapp = TipoContatoInteressado::create(['nome' => 'WhatsApp']);

        // Criar o lead já gera eventos de auditoria; começamos cada teste com a linha do tempo vazia.
        Activity::query()->delete();
    }

    private function componente(): Testable
    {
        return Livewire::test(TimelineRelationManager::class, [
            'ownerRecord' => $this->interessado,
            'pageClass' => EditInteressado::class,
        ]);
    }

    private function registrarContato(string $relato, \DateTimeInterface $quando): void
    {
        HistoricoContato::create([
            'interessado_id' => $this->interessado->id,
            'usuario_id' => $this->user->id,
            'tipo_contato_interessado_id' => $this->tipoWhatsapp->id,
            'relato' => $relato,
            'data_contato' => $quando,
        ]);
    }

    public function test_todo_evento_informa_um_tom_de_cor_semantico(): void
    {
        $this->registrarContato('Contato por WhatsApp', now());

        VisitaInteressado::create([
            'interessado_id' => $this->interessado->id,
            'usuario_id' => $this->user->id,
            'data_hora' => now()->addDay(),
            'status' => StatusVisitaInteressado::Agendada,
        ]);

        Activity::create([
            'log_name' => 'crm',
            'description' => 'updated',
            'subject_type' => Interessado::class,
            'subject_id' => $this->interessado->id,
            'causer_type' => User::class,
            'causer_id' => $this->user->id,
            'properties' => ['old' => ['temperatura' => 'frio'], 'attributes' => ['temperatura' => 'quente']],
            'created_at' => now()->subHour(),
        ]);

        $eventos = app(Customer360TimelineService::class)->obterTimeline($this->interessado);

        $this->assertCount(3, $eventos);

        $tonsValidos = ['emerald', 'sky', 'purple', 'amber', 'indigo', 'teal', 'violet', 'blue', 'rose', 'gray'];
        foreach ($eventos as $evento) {
            $this->assertContains($evento['tom'] ?? null, $tonsValidos, "Evento {$evento['id']} sem tom válido.");
        }

        $this->assertSame('emerald', $eventos->firstWhere('tipo', 'contato')['tom']);
        $this->assertSame('teal', $eventos->firstWhere('tipo', 'visita')['tom']);
    }

    public function test_feed_agrupa_eventos_por_dia_e_exibe_indicadores(): void
    {
        $this->registrarContato('Conversa de hoje sobre bolsas', now());
        $this->registrarContato('Conversa antiga sobre matrícula', now()->subDays(10));

        $this->componente()
            ->assertSee('Linha do Tempo Omnichannel')
            ->assertSee('Hoje')
            ->assertSee('Conversa de hoje sobre bolsas')
            ->assertSee('Conversa antiga sobre matrícula')
            ->assertSee(now()->subDays(10)->locale('pt_BR')->isoFormat('D [de] MMMM [de] YYYY'))
            ->assertSee('Lead Score')
            ->assertSee('Retornar no WhatsApp');
    }

    public function test_visita_futura_ganha_marca_de_agendado(): void
    {
        VisitaInteressado::create([
            'interessado_id' => $this->interessado->id,
            'usuario_id' => $this->user->id,
            'data_hora' => now()->addDays(3),
            'status' => StatusVisitaInteressado::Agendada,
        ]);

        $this->componente()->assertSee('Agendado');
    }

    public function test_estado_vazio_sem_eventos_e_com_filtro_sem_resultado(): void
    {
        $this->componente()
            ->assertSee('A jornada ainda não começou');

        $this->registrarContato('Primeiro contato', now());

        $this->componente()
            ->set('termoBusca', 'termo-que-nao-existe')
            ->assertSee('Nenhum evento encontrado')
            ->assertSee('Ver todos os eventos');
    }

    public function test_registro_rapido_so_aparece_para_quem_pode_registrar(): void
    {
        $this->componente()->assertDontSee('Registrar nova interação');

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['activated_at' => now()]);
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(TimelineRelationManager::class, [
                'ownerRecord' => $this->interessado,
                'pageClass' => EditInteressado::class,
            ])
            ->assertSee('Registrar nova interação')
            ->assertSee('Gravar interação e recalcular score');
    }

    public function test_feed_exibe_um_lote_por_vez_e_carrega_mais_eventos(): void
    {
        $lote = TimelineRelationManager::EVENTOS_POR_LOTE;
        $total = $lote + 5;

        // Relato #000 é o mais recente; os últimos 5 ficam fora do primeiro lote.
        for ($i = 0; $i < $total; $i++) {
            $this->registrarContato(sprintf('Relato #%03d', $i), now()->subMinutes($i));
        }

        $componente = $this->componente()
            ->assertSet('limiteEventos', $lote)
            ->assertSee('Relato #000')
            ->assertSee(sprintf('Relato #%03d', $lote - 1))
            ->assertDontSee(sprintf('Relato #%03d', $lote))
            ->assertSee("Mostrando {$lote} de {$total} eventos")
            ->assertSee('Carregar mais 5');

        $componente->call('carregarMais')
            ->assertSet('limiteEventos', $lote * 2)
            ->assertSee(sprintf('Relato #%03d', $total - 1))
            ->assertDontSee('Carregar mais')
            ->assertSee("{$total} eventos")
            ->assertSee('início do histórico');
    }

    public function test_trocar_filtro_ou_busca_reinicia_a_paginacao_do_feed(): void
    {
        $lote = TimelineRelationManager::EVENTOS_POR_LOTE;

        $this->componente()
            ->call('carregarMais')
            ->assertSet('limiteEventos', $lote * 2)
            ->call('filtrar', 'contatos')
            ->assertSet('limiteEventos', $lote)
            ->call('carregarMais')
            ->set('termoBusca', 'qualquer')
            ->assertSet('limiteEventos', $lote)
            ->call('carregarMais')
            ->call('limparFiltros')
            ->assertSet('limiteEventos', $lote);
    }

    public function test_ajuda_descreve_o_layout_atual_e_respeita_a_permissao_do_registro_rapido(): void
    {
        $ajuda = fn (User $usuario): string => (function (): string {
            return $this->getHelpContent();
        })->call(
            Livewire::actingAs($usuario)
                ->test(TimelineRelationManager::class, [
                    'ownerRecord' => $this->interessado,
                    'pageClass' => EditInteressado::class,
                ])
                ->instance()
        );

        $semPermissao = $ajuda($this->user);

        $this->assertStringContainsString('Retornar no WhatsApp', $semPermissao);
        $this->assertStringContainsString('Carregar mais', $semPermissao);
        $this->assertStringContainsString('Indicadores 360°', $semPermissao);
        $this->assertStringNotContainsString('Registrar nova interação', $semPermissao);
        $this->assertStringNotContainsString('Pontuação do Lead', $semPermissao);
        $this->assertStringNotContainsString('botão direto na timeline', $semPermissao);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['activated_at' => now()]);
        $admin->assignRole('super_admin');

        $comPermissao = $ajuda($admin);

        $this->assertStringContainsString('Registrar nova interação', $comPermissao);
        $this->assertStringContainsString('Pontuação do Lead', $comPermissao);
    }

    public function test_view_nao_usa_classes_tailwind_que_o_painel_nao_compila(): void
    {
        $this->registrarContato('Qualquer relato', now());

        // O painel admin não compila Tailwind próprio: estas classes não existem no CSS servido.
        $this->componente()
            ->assertDontSeeHtml('rounded-2xl')
            ->assertDontSeeHtml('space-y-6')
            ->assertDontSeeHtml('bg-gradient-to-br')
            ->assertSeeHtml('tl360-feed');
    }
}
