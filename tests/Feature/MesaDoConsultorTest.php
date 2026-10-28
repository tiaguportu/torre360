<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\EditInteressado;
use App\Filament\Resources\Interessados\Pages\ListInteressados;
use App\Filament\Resources\Interessados\RelationManagers\HistoricosRelationManager;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Models\VisitaInteressado;
use App\Services\LeadFunilService;
use App\Services\Pendencias\Fontes\ContatosDoDia;
use App\Services\Pendencias\Fontes\LeadsQuentesParados;
use App\Services\Pendencias\Fontes\LeadsSemConsultor;
use App\Services\Pendencias\Fontes\LeadsSemProximaAcao;
use App\Services\Pendencias\Fontes\VisitasDoDia;
use App\Services\Pendencias\MesaDePendencias;
use App\Services\ProximaAcaoLeadService;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Mesa do consultor e próxima ação obrigatória (Onda 84): as cinco filas do CRM na Mesa de pendências, a regra da próxima
 * ação e as telas que a exigem.
 *
 * "Hoje" é segunda-feira, 26/10/2026, 10:00.
 */
class MesaDoConsultorTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private User $ana;

    private User $bruno;

    private User $gestor;

    private StatusInteressado $ativo;

    private StatusInteressado $matriculado;

    private OrigemInteressado $origem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-26 10:00:00'));
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['super_admin', 'admin', 'comercial'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        foreach (['ViewAny:Interessado', 'View:Interessado', 'Update:Interessado'] as $nome) {
            Role::findByName('comercial')->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
            Role::findByName('admin')->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        Role::findByName('admin')->givePermissionTo(Permission::firstOrCreate(['name' => 'ViewAll:Interessado', 'guard_name' => 'web']));

        $this->ana = $this->usuario('comercial', 'Ana Consultora');
        $this->bruno = $this->usuario('comercial', 'Bruno Consultor');
        $this->gestor = $this->usuario('admin', 'Gestora');

        $this->ativo = StatusInteressado::create(['nome' => 'Em Atendimento', 'cor' => 'warning', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $this->matriculado = StatusInteressado::create(['nome' => 'Matriculado', 'cor' => 'success', 'ordem' => 2, 'is_final' => true, 'is_ganho' => true]);
        $this->origem = OrigemInteressado::create(['nome' => 'Site']);
        TipoContatoInteressado::firstOrCreate(['nome' => 'Telefone']);
    }

    private function usuario(string $papel, string $nome): User
    {
        $usuario = User::factory()->create(['name' => $nome, 'activated_at' => now()->subMonth(), 'email_verified_at' => now()]);
        $usuario->assignRole($papel);

        return $usuario;
    }

    private function como(User $usuario): User
    {
        session(['active_role' => $usuario->getRoleNames()->first()]);

        return $usuario;
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function lead(?User $dono, array $atributos = [], ?Carbon $criadoEm = null): Interessado
    {
        $lead = Interessado::create($atributos + [
            'pessoa_id' => Pessoa::factory()->create()->id,
            'usuario_id' => $dono?->id,
            'status_interessado_id' => $this->ativo->id,
            'origem_interessado_id' => $this->origem->id,
        ]);

        $lead->forceFill(['created_at' => $criadoEm ?? now()->subDays(10)])->saveQuietly();

        return $lead->fresh();
    }

    // --- Próxima ação ------------------------------------------------------------------------------------------------

    public function test_definir_grava_o_que_fazer_e_quando(): void
    {
        $lead = $this->lead($this->ana);

        app(ProximaAcaoLeadService::class)->definir($lead, now()->addDay(), '  Mandar a proposta  ');

        $lead->refresh();
        $this->assertSame('Mandar a proposta', $lead->proxima_acao);
        $this->assertSame('2026-10-27 10:00', $lead->data_proximo_contato->format('Y-m-d H:i'));
    }

    public function test_definir_recusa_data_no_passado_e_sem_data(): void
    {
        $lead = $this->lead($this->ana);

        foreach ([[now()->subHour(), 'agora ou no futuro'], [null, 'Informe quando']] as [$quando, $trecho]) {
            try {
                app(ProximaAcaoLeadService::class)->definir($lead, $quando, 'Ligar');
                $this->fail('devia recusar');
            } catch (\DomainException $e) {
                $this->assertStringContainsString($trecho, $e->getMessage());
            }
        }

        $this->assertNull($lead->refresh()->proxima_acao);
    }

    public function test_resultado_que_encerra_o_ciclo_limpa_a_proxima_acao(): void
    {
        $lead = $this->lead($this->ana, ['data_proximo_contato' => now()->addDay(), 'proxima_acao' => 'Ligar']);
        $servico = app(ProximaAcaoLeadService::class);

        $this->assertTrue($servico->exigidaPara('retornar'));
        $this->assertTrue($servico->exigidaPara(null));
        $this->assertFalse($servico->exigidaPara('matriculou'));
        $this->assertFalse($servico->exigidaPara('sem_interesse'));

        $servico->definir($lead, null, null, 'sem_interesse');

        $lead->refresh();
        $this->assertNull($lead->data_proximo_contato);
        $this->assertNull($lead->proxima_acao);
    }

    public function test_falta_proxima_acao_so_para_lead_em_andamento(): void
    {
        $servico = app(ProximaAcaoLeadService::class);

        $this->assertTrue($servico->faltaProximaAcao($this->lead($this->ana)));
        $this->assertFalse($servico->faltaProximaAcao($this->lead($this->ana, ['data_proximo_contato' => now()->addDay()])));
        $this->assertFalse($servico->faltaProximaAcao($this->lead($this->ana, ['status_interessado_id' => $this->matriculado->id])));
    }

    public function test_registrar_atendimento_grava_a_proxima_acao_e_o_resultado_final_a_limpa(): void
    {
        $lead = $this->lead($this->ana);
        $tipo = TipoContatoInteressado::where('nome', 'Telefone')->firstOrFail();
        $dados = ['tipo_contato_interessado_id' => $tipo->id, 'relato' => 'Falei com a mãe.'];

        app(LeadFunilService::class)->registrarAtendimento($lead, $dados + ['resultado' => 'retornar', 'data_proximo_contato' => now()->addDays(2), 'proxima_acao' => 'Enviar a proposta'], $this->ana->id);

        $lead->refresh();
        $this->assertSame('Enviar a proposta', $lead->proxima_acao);
        $this->assertNotNull($lead->data_proximo_contato);

        app(LeadFunilService::class)->registrarAtendimento($lead, $dados + ['resultado' => 'matriculou'], $this->ana->id);

        $lead->refresh();
        $this->assertNull($lead->proxima_acao);
        $this->assertNull($lead->data_proximo_contato);
    }

    // --- Filas -------------------------------------------------------------------------------------------------------

    public function test_sem_proxima_acao_respeita_a_carencia_o_status_e_a_carteira(): void
    {
        $this->como($this->ana);
        $antiga = $this->lead($this->ana, [], now()->subDays(3));
        $this->lead($this->ana, [], now()->subMinutes(30));
        $this->lead($this->ana, ['data_proximo_contato' => now()->addDay()]);
        $this->lead($this->ana, ['status_interessado_id' => $this->matriculado->id]);
        $this->lead($this->bruno);
        $semDono = $this->lead(null, [], now()->subHours(5));

        $fila = new LeadsSemProximaAcao;

        $this->assertSame(2, $fila->contar($this->ana), 'a antiga e a sem dono (que a consultora pode assumir)');
        $this->assertSame(1, $fila->atrasados($this->ana), 'só a de 3 dias passou do prazo');

        $itens = $fila->itens($this->ana, 5);
        $this->assertSame($antiga->pessoa->nome, $itens[0]->titulo);
        $this->assertTrue($itens[0]->atrasado);
        $this->assertFalse($itens[1]->atrasado);
        $this->assertStringContainsString('Ana Consultora', $itens[0]->detalhe);
        $this->assertNotNull($semDono);
    }

    public function test_gestor_ve_a_carteira_de_todos(): void
    {
        $this->como($this->gestor);
        $this->lead($this->ana);
        $this->lead($this->bruno);

        $this->assertSame(2, (new LeadsSemProximaAcao)->contar($this->gestor));
    }

    public function test_contatos_do_dia_separam_o_que_vence_hoje_do_que_ja_passou(): void
    {
        $this->como($this->ana);
        $atrasado = $this->lead($this->ana, ['data_proximo_contato' => now()->subDay(), 'proxima_acao' => 'Ligar de volta']);
        $this->lead($this->ana, ['data_proximo_contato' => now()->addHours(3), 'proxima_acao' => 'Confirmar visita']);
        $this->lead($this->ana, ['data_proximo_contato' => now()->addDay()->setTime(9, 0)]);
        $this->lead($this->bruno, ['data_proximo_contato' => now()->subDay()]);

        $fila = new ContatosDoDia;

        $this->assertSame(2, $fila->contar($this->ana));
        $this->assertSame(1, $fila->atrasados($this->ana), 'o de hoje, mais tarde, ainda não está atrasado');

        $itens = $fila->itens($this->ana, 5);
        $this->assertSame($atrasado->pessoa->nome, $itens[0]->titulo);
        $this->assertStringContainsString('Ligar de volta', $itens[0]->detalhe);
        $this->assertTrue($itens[0]->atrasado);
        $this->assertFalse($itens[1]->atrasado);
    }

    public function test_visitas_do_dia_e_sem_resultado(): void
    {
        $this->como($this->ana);
        $lead = $this->lead($this->ana);
        $deBruno = $this->lead($this->bruno);

        $visita = fn (Interessado $l, Carbon $quando, string $status = 'agendada') => VisitaInteressado::create(['interessado_id' => $l->id, 'usuario_id' => $this->ana->id, 'data_hora' => $quando, 'status' => $status]);

        $visita($lead, now()->addHours(2));
        $visita($lead, now()->subHours(6));
        $visita($lead, now()->subHours(1));
        $visita($lead, now()->subHours(8), 'realizada');
        $visita($lead, now()->addDay());
        $visita($deBruno, now()->addHour());

        $fila = new VisitasDoDia;

        $this->assertSame(3, $fila->contar($this->ana));
        $this->assertSame(1, $fila->atrasados($this->ana), 'só a de 6 horas atrás passou do prazo para o resultado');
        $this->assertCount(3, $fila->itens($this->ana, 5));
        $this->assertStringContainsString('Passou sem resultado', $fila->itens($this->ana, 5)[0]->detalhe);
    }

    public function test_leads_quentes_parados(): void
    {
        $this->como($this->ana);
        $tipo = TipoContatoInteressado::where('nome', 'Telefone')->firstOrFail();
        $contato = fn (Interessado $l, Carbon $quando) => HistoricoContato::create(['interessado_id' => $l->id, 'tipo_contato_interessado_id' => $tipo->id, 'usuario_id' => $this->ana->id, 'data_contato' => $quando, 'relato' => 'Contato', 'automatico' => false]);

        $parado = $this->lead($this->ana, ['lead_score' => 85]);
        $contato($parado, now()->subDays(4));
        $muitoParado = $this->lead($this->ana, ['temperatura' => 'quente']);
        $contato($muitoParado, now()->subDays(9));
        $recente = $this->lead($this->ana, ['lead_score' => 90]);
        $contato($recente, now()->subDay());
        $frio = $this->lead($this->ana, ['lead_score' => 20]);
        $contato($frio, now()->subDays(20));

        $fila = new LeadsQuentesParados;

        $this->assertSame(2, $fila->contar($this->ana));
        $this->assertSame(1, $fila->atrasados($this->ana), 'só o parado há mais de 6 dias (o dobro do prazo)');
        $this->assertSame($parado->pessoa->nome, $fila->itens($this->ana, 5)[0]->titulo, 'ordenado pelo score');
    }

    public function test_leads_sem_consultor_so_para_quem_pode_assumir(): void
    {
        $this->como($this->ana);
        $this->lead(null, [], now()->subHours(30));
        $this->lead(null, [], now()->subHours(3));
        $this->lead($this->ana);

        $fila = new LeadsSemConsultor;

        $this->assertSame('Update:Interessado', $fila->permissao());
        $this->assertSame(2, $fila->contar($this->ana));
        $this->assertSame(1, $fila->atrasados($this->ana));
    }

    // --- Mesa --------------------------------------------------------------------------------------------------------

    public function test_a_mesa_mostra_as_filas_do_crm_a_quem_tem_permissao_e_so_a_carteira_de_cada_um(): void
    {
        $this->lead($this->ana, ['data_proximo_contato' => now()->subDay()]);
        $this->lead($this->bruno, ['data_proximo_contato' => now()->subDay()]);

        $this->como($this->ana);
        $filas = app(MesaDePendencias::class)->filas($this->ana)->keyBy(fn ($fila) => $fila->fonte->chave());

        foreach (['contatos_do_dia', 'visitas_do_dia', 'leads_sem_proxima_acao', 'leads_quentes_parados', 'leads_sem_consultor'] as $chave) {
            $this->assertTrue($filas->has($chave), "{$chave} deveria estar na Mesa");
            $this->assertFalse($filas[$chave]->erro, "{$chave} não pode falhar");
        }

        $this->assertFalse($filas->has('primeira_resposta_estourada'), 'o SLA de 1ª resposta foi retirado e a fila saiu da Mesa');

        $this->assertSame(1, $filas['contatos_do_dia']->total, 'só o lead da Ana');
        $this->assertSame(1, $filas['contatos_do_dia']->atrasados);

        // Quem não tem permissão de CRM não vê essas filas.
        $professor = User::factory()->create(['activated_at' => now()->subMonth()]);
        $this->como($professor);
        $chaves = app(MesaDePendencias::class)->fontesDe($professor);
        $this->assertSame([], array_values(array_filter($chaves, fn ($f) => str_starts_with($f->area(), 'CRM'))));
    }

    // --- Telas -------------------------------------------------------------------------------------------------------

    public function test_acao_atendimento_exige_a_proxima_acao_e_a_data(): void
    {
        $lead = $this->lead($this->ana);
        $tipo = TipoContatoInteressado::where('nome', 'Telefone')->firstOrFail();
        $base = ['tipo_contato_interessado_id' => $tipo->id, 'data_contato' => now()->subMinute()->format('Y-m-d H:i:s'), 'relato' => 'Falei com a mãe.', 'resultado' => 'retornar'];

        Livewire::actingAs($this->como($this->ana))
            ->test(ListInteressados::class)
            ->callTableAction('registrarAtendimento', $lead, data: $base + ['proxima_acao' => '', 'data_proximo_contato' => null])
            ->assertHasTableActionErrors(['proxima_acao', 'data_proximo_contato']);

        $this->assertSame(0, HistoricoContato::count());

        Livewire::actingAs($this->como($this->ana))->test(ListInteressados::class)->callTableAction('registrarAtendimento', $lead, data: $base + ['proxima_acao' => 'Mandar a proposta', 'data_proximo_contato' => now()->addDays(2)->format('Y-m-d H:i:s')])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Mandar a proposta', $lead->refresh()->proxima_acao);
    }

    public function test_acao_atendimento_com_resultado_final_nao_exige_proxima_acao(): void
    {
        $lead = $this->lead($this->ana, ['data_proximo_contato' => now()->addDay(), 'proxima_acao' => 'Antiga']);
        $tipo = TipoContatoInteressado::where('nome', 'Telefone')->firstOrFail();

        Livewire::actingAs($this->como($this->ana))
            ->test(ListInteressados::class)
            ->callTableAction('registrarAtendimento', $lead, data: ['tipo_contato_interessado_id' => $tipo->id, 'data_contato' => now()->subMinute()->format('Y-m-d H:i:s'), 'relato' => 'Família escolheu outra escola.', 'resultado' => 'sem_interesse'])
            ->assertHasNoTableActionErrors();

        $lead->refresh();
        $this->assertNull($lead->proxima_acao);
        $this->assertNull($lead->data_proximo_contato);
    }

    public function test_botao_proxima_acao_marca_e_some_nos_leads_finalizados(): void
    {
        $lead = $this->lead($this->ana);
        $final = $this->lead($this->ana, ['status_interessado_id' => $this->matriculado->id]);

        Livewire::actingAs($this->como($this->ana))
            ->test(ListInteressados::class)
            ->assertTableActionVisible('definirProximaAcao', $lead)
            ->assertTableActionHidden('definirProximaAcao', $final)
            ->callTableAction('definirProximaAcao', $lead, data: ['proxima_acao' => 'Ligar para confirmar a visita', 'data_proximo_contato' => now()->addDay()->format('Y-m-d H:i:s')])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Próxima ação marcada');

        $this->assertSame('Ligar para confirmar a visita', $lead->refresh()->proxima_acao);
        $this->assertFalse(app(ProximaAcaoLeadService::class)->faltaProximaAcao($lead));
    }

    public function test_contato_registrado_pela_ficha_marca_a_proxima_acao_no_lead(): void
    {
        $lead = $this->lead($this->ana);
        $tipo = TipoContatoInteressado::where('nome', 'Telefone')->firstOrFail();

        Livewire::actingAs($this->como($this->ana))
            ->test(HistoricosRelationManager::class, ['ownerRecord' => $lead, 'pageClass' => EditInteressado::class])
            ->callAction(TestAction::make('create')->table(), [
                'tipo_contato_interessado_id' => $tipo->id,
                'data_contato' => now()->subMinute()->format('Y-m-d H:i:s'),
                'resultado' => 'retornar',
                'relato' => 'Pediram para ligar na quinta.',
                'proxima_acao' => 'Ligar na quinta',
                'data_proximo_contato' => now()->addDays(3)->format('Y-m-d H:i:s'),
            ])
            ->assertHasNoFormErrors();

        $lead->refresh();
        $this->assertSame('Ligar na quinta', $lead->proxima_acao);
        $this->assertSame('2026-10-29 10:00', $lead->data_proximo_contato->format('Y-m-d H:i'));
        $this->assertSame(1, HistoricoContato::where('interessado_id', $lead->id)->count());
    }

    public function test_contato_pela_ficha_sem_proxima_acao_e_recusado_quando_o_ciclo_segue(): void
    {
        $lead = $this->lead($this->ana);
        $tipo = TipoContatoInteressado::where('nome', 'Telefone')->firstOrFail();

        Livewire::actingAs($this->como($this->ana))
            ->test(HistoricosRelationManager::class, ['ownerRecord' => $lead, 'pageClass' => EditInteressado::class])
            ->callAction(TestAction::make('create')->table(), [
                'tipo_contato_interessado_id' => $tipo->id,
                'data_contato' => now()->subMinute()->format('Y-m-d H:i:s'),
                'resultado' => 'retornar',
                'relato' => 'Sem retorno.',
            ])
            ->assertHasFormErrors(['proxima_acao']);

        $this->assertSame(0, HistoricoContato::count());
    }
}
