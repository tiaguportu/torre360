<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\User;
use App\Services\LeadSlaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CrmSlaGestaoSemDestinatarioLoteG5Test extends TestCase
{
    use RefreshDatabase;

    private StatusInteressado $statusNovo;

    private OrigemInteressado $origem;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'coordenador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'consultor', 'guard_name' => 'web']);

        $this->statusNovo = StatusInteressado::create([
            'nome' => 'Novo',
            'cor' => 'info',
            'ordem' => 1,
            'is_final' => false,
            'is_ganho' => false,
        ]);

        $this->origem = OrigemInteressado::create(['nome' => 'Site']);

        config([
            'crm.sla.primeira_resposta_minutos_padrao' => 120,
            'crm.sla.horario_comercial.inicio' => '08:00',
            'crm.sla.horario_comercial.fim' => '18:00',
            'crm.sla.horario_comercial.dias_uteis' => [1, 2, 3, 4, 5],
        ]);
    }

    private function criarLead(array $dados = []): Interessado
    {
        if (! isset($dados['pessoa_id'])) {
            $dados['pessoa_id'] = Pessoa::create([
                'nome' => 'Lead '.fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'telefone' => '119'.fake()->numerify('########'),
            ])->id;
        }

        return Interessado::create(array_merge([
            'status_interessado_id' => $this->statusNovo->id,
            'origem_interessado_id' => $this->origem->id,
        ], $dados));
    }

    public function test_sem_gestor_nao_marca_coluna_retorna_zero_e_zero_notificacoes(): void
    {
        // Nenhum usuário cadastrado no sistema
        $dataCriacao = Carbon::create(2026, 10, 13, 8, 0, 0);
        $lead = $this->criarLead([
            'usuario_id' => null,
            'data_primeiro_contato' => null,
            'sla_gestao_notificado_em' => null,
        ]);
        $lead->forceFill(['created_at' => $dataCriacao])->saveQuietly();

        Carbon::setTestNow(Carbon::create(2026, 10, 13, 11, 0, 0));

        $slaService = app(LeadSlaService::class);
        $detalhes = [];
        $total = $slaService->verificarENotificarEstouros($detalhes);

        // Sem destinatário: retorno deve ser 0 e gestao 0
        $this->assertSame(0, $total);
        $this->assertSame(0, $detalhes['gestao'] ?? 0);
        $this->assertSame(0, DatabaseNotification::count());

        // Coluna não pode ter sido preenchida
        $lead->refresh();
        $this->assertNull($lead->sla_gestao_notificado_em);
    }

    public function test_quando_admin_passa_a_existir_lead_anteriormente_sem_gestor_e_notificado(): void
    {
        $dataCriacao = Carbon::create(2026, 10, 13, 8, 0, 0);
        $lead = $this->criarLead([
            'usuario_id' => null,
            'data_primeiro_contato' => null,
            'sla_gestao_notificado_em' => null,
        ]);
        $lead->forceFill(['created_at' => $dataCriacao])->saveQuietly();

        Carbon::setTestNow(Carbon::create(2026, 10, 13, 11, 0, 0));

        $slaService = app(LeadSlaService::class);

        // 1ª execução sem gestor
        $detalhes1 = [];
        $total1 = $slaService->verificarENotificarEstouros($detalhes1);
        $this->assertSame(0, $total1);
        $this->assertNull($lead->fresh()->sla_gestao_notificado_em);

        // Agora um administrador é cadastrado e ativado
        $admin = User::factory()->create(['name' => 'Admin Chegou', 'activated_at' => now(), 'deactivated_at' => null]);
        $admin->assignRole('admin');

        // 2ª execução: o lead agora deve ser notificado e marcado
        $detalhes2 = [];
        $total2 = $slaService->verificarENotificarEstouros($detalhes2);

        $this->assertSame(1, $total2);
        $this->assertSame(1, $detalhes2['gestao'] ?? 0);
        $this->assertSame(1, $admin->notifications()->count());

        $lead->refresh();
        $this->assertNotNull($lead->sla_gestao_notificado_em);
    }

    public function test_admin_inativo_nao_conta_como_destinatario_e_nao_marca_lead(): void
    {
        $adminInativo = User::factory()->create([
            'name' => 'Admin Inativo',
            'activated_at' => now()->subYear(),
            'deactivated_at' => now()->subDay(),
        ]);
        $adminInativo->assignRole('admin');

        $dataCriacao = Carbon::create(2026, 10, 13, 8, 0, 0);
        $lead = $this->criarLead([
            'usuario_id' => null,
            'data_primeiro_contato' => null,
            'sla_gestao_notificado_em' => null,
        ]);
        $lead->forceFill(['created_at' => $dataCriacao])->saveQuietly();

        Carbon::setTestNow(Carbon::create(2026, 10, 13, 11, 0, 0));

        $slaService = app(LeadSlaService::class);
        $detalhes = [];
        $total = $slaService->verificarENotificarEstouros($detalhes);

        $this->assertSame(0, $total);
        $this->assertSame(0, $detalhes['gestao'] ?? 0);
        $this->assertSame(0, DatabaseNotification::count());

        $lead->refresh();
        $this->assertNull($lead->sla_gestao_notificado_em);
    }

    public function test_com_gestor_ativo_notifica_uma_unica_vez(): void
    {
        $coord = User::factory()->create(['name' => 'Coord Ativo', 'activated_at' => now(), 'deactivated_at' => null]);
        $coord->assignRole('coordenador');

        $dataCriacao = Carbon::create(2026, 10, 13, 8, 0, 0);
        $lead = $this->criarLead([
            'usuario_id' => null,
            'data_primeiro_contato' => null,
            'sla_gestao_notificado_em' => null,
        ]);
        $lead->forceFill(['created_at' => $dataCriacao])->saveQuietly();

        Carbon::setTestNow(Carbon::create(2026, 10, 13, 11, 0, 0));

        $slaService = app(LeadSlaService::class);
        $total1 = $slaService->verificarENotificarEstouros();
        $this->assertSame(1, $total1);
        $this->assertSame(1, $coord->notifications()->count());

        $total2 = $slaService->verificarENotificarEstouros();
        $this->assertSame(0, $total2);
        $this->assertSame(1, $coord->notifications()->count());
    }
}
