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
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CrmSlaGestaoSemConsultorLoteF4Test extends TestCase
{
    use RefreshDatabase;

    private StatusInteressado $statusNovo;

    private StatusInteressado $statusMatriculado;

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

        $this->statusMatriculado = StatusInteressado::create([
            'nome' => 'Matriculado',
            'cor' => 'success',
            'ordem' => 5,
            'is_final' => true,
            'is_ganho' => true,
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

    public function test_lead_sem_consultor_estourado_notifica_gestao_uma_unica_vez(): void
    {
        $coordenador = User::factory()->create(['activated_at' => now(), 'deactivated_at' => null]);
        $coordenador->assignRole('coordenador');

        $outro = User::factory()->create(['activated_at' => now(), 'deactivated_at' => null]);
        $outro->assignRole('consultor');

        // Criado terça-feira 08:00 (limite 10:00)
        $dataCriacao = Carbon::create(2026, 10, 13, 8, 0, 0);
        $lead = $this->criarLead([
            'usuario_id' => null,
            'data_primeiro_contato' => null,
            'sla_gestao_notificado_em' => null,
        ]);
        $lead->forceFill(['created_at' => $dataCriacao])->saveQuietly();

        // Terça-feira às 11:00 (estourado)
        Carbon::setTestNow(Carbon::create(2026, 10, 13, 11, 0, 0));

        $slaService = app(LeadSlaService::class);
        $detalhes = [];
        $total = $slaService->verificarENotificarEstouros($detalhes);

        $this->assertSame(1, $total);
        $this->assertSame(1, $detalhes['gestao'] ?? 0);
        $this->assertSame(0, $detalhes['consultor'] ?? 0);

        // Coordenador recebe 1 notificação no banco de dados
        $this->assertSame(1, $coordenador->notifications()->count());
        $this->assertSame(0, $outro->notifications()->count());

        $lead->refresh();
        $this->assertNotNull($lead->sla_gestao_notificado_em);

        // Reexecução não repete
        $totalRepetido = $slaService->verificarENotificarEstouros();
        $this->assertSame(0, $totalRepetido);
        $this->assertSame(1, $coordenador->notifications()->count());
    }

    public function test_depois_de_assumido_consultor_ainda_e_notificado_se_continuar_estourado(): void
    {
        $coordenador = User::factory()->create(['activated_at' => now(), 'deactivated_at' => null]);
        $coordenador->assignRole('coordenador');

        $consultor = User::factory()->create(['activated_at' => now(), 'deactivated_at' => null]);
        $consultor->assignRole('consultor');

        $dataCriacao = Carbon::create(2026, 10, 13, 8, 0, 0);
        $lead = $this->criarLead([
            'usuario_id' => null,
            'data_primeiro_contato' => null,
            'sla_gestao_notificado_em' => null,
            'sla_estouro_notificado_em' => null,
        ]);
        $lead->forceFill(['created_at' => $dataCriacao])->saveQuietly();

        Carbon::setTestNow(Carbon::create(2026, 10, 13, 11, 0, 0));

        $slaService = app(LeadSlaService::class);
        $slaService->verificarENotificarEstouros();

        // Agora o consultor assume o lead às 11:15
        $lead->refresh();
        $lead->usuario_id = $consultor->id;
        $lead->saveQuietly();

        // Continua estourado e sem data_primeiro_contato. O consultor deve ser notificado.
        Carbon::setTestNow(Carbon::create(2026, 10, 13, 11, 30, 0));
        $detalhes = [];
        $total = $slaService->verificarENotificarEstouros($detalhes);

        $this->assertSame(1, $total);
        $this->assertSame(1, $detalhes['consultor'] ?? 0);
        $this->assertSame(1, $consultor->notifications()->count());

        $lead->refresh();
        $this->assertNotNull($lead->sla_estouro_notificado_em);
    }

    public function test_fora_do_horario_comercial_nao_estoura_sla_da_gestao(): void
    {
        $coordenador = User::factory()->create(['activated_at' => now(), 'deactivated_at' => null]);
        $coordenador->assignRole('coordenador');

        // Lead criado terça 17:30 (SLA 120 min = consome 30 min na terça, 90 min na quarta -> limite quarta 09:30)
        $dataCriacao = Carbon::create(2026, 10, 13, 17, 30, 0);
        $lead = $this->criarLead([
            'usuario_id' => null,
            'data_primeiro_contato' => null,
            'sla_gestao_notificado_em' => null,
        ]);
        $lead->forceFill(['created_at' => $dataCriacao])->saveQuietly();

        // Terça às 21:00 (fora do horário comercial, relógio parou com 30 min gastos)
        Carbon::setTestNow(Carbon::create(2026, 10, 13, 21, 0, 0));

        $slaService = app(LeadSlaService::class);
        $total = $slaService->verificarENotificarEstouros();

        $this->assertSame(0, $total);
        $this->assertSame(0, $coordenador->notifications()->count());
    }

    public function test_lead_em_etapa_final_nao_notifica_gestao(): void
    {
        $coordenador = User::factory()->create(['activated_at' => now(), 'deactivated_at' => null]);
        $coordenador->assignRole('coordenador');

        $dataCriacao = Carbon::create(2026, 10, 13, 8, 0, 0);
        $lead = $this->criarLead([
            'status_interessado_id' => $this->statusMatriculado->id,
            'usuario_id' => null,
            'data_primeiro_contato' => null,
            'sla_gestao_notificado_em' => null,
        ]);
        $lead->forceFill(['created_at' => $dataCriacao])->saveQuietly();

        Carbon::setTestNow(Carbon::create(2026, 10, 13, 12, 0, 0));

        $slaService = app(LeadSlaService::class);
        $total = $slaService->verificarENotificarEstouros();

        $this->assertSame(0, $total);
        $this->assertSame(0, $coordenador->notifications()->count());
    }
}
