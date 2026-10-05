<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Services\AlertaLeadsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * `crm:notificar-pendentes`: um aviso por lead a cada intervalo (não todo dia), escalonamento à gestão e
 * resumo diário de leads sem consultor.
 */
class AlertaLeadsTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private StatusInteressado $ativo;

    private StatusInteressado $matriculado;

    private OrigemInteressado $origem;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->ativo = StatusInteressado::create(['nome' => 'Em Atendimento', 'cor' => 'warning', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $this->matriculado = StatusInteressado::create(['nome' => 'Matriculado', 'cor' => 'success', 'ordem' => 2, 'is_final' => true, 'is_ganho' => true]);
        $this->origem = OrigemInteressado::create(['nome' => 'Site']);
    }

    private function consultor(): User
    {
        return User::factory()->create(['activated_at' => now()->subMonth()]);
    }

    private function gestor(bool $ativo = true): User
    {
        $gestor = User::factory()->create(['activated_at' => $ativo ? now()->subMonth() : null]);
        $gestor->assignRole('admin');

        return $gestor;
    }

    private function lead(array $atributos = [], int $diasDeCriacao = 0): Interessado
    {
        $lead = Interessado::create($atributos + [
            'pessoa_id' => Pessoa::factory()->create()->id,
            'status_interessado_id' => $this->ativo->id,
            'origem_interessado_id' => $this->origem->id,
        ]);

        if ($diasDeCriacao > 0) {
            $lead->forceFill(['created_at' => now()->subDays($diasDeCriacao)])->saveQuietly();
        }

        return $lead->fresh();
    }

    /**
     * @return list<string>
     */
    private function titulos(User $usuario): array
    {
        return $usuario->notifications()->get()->pluck('data.title')->all();
    }

    private function executar(): array
    {
        return app(AlertaLeadsService::class)->executar();
    }

    public function test_lead_atrasado_avisa_o_consultor_uma_vez_e_so_de_novo_depois_do_intervalo(): void
    {
        $consultor = $this->consultor();
        $lead = $this->lead(['usuario_id' => $consultor->id, 'data_proximo_contato' => now()->subDay()], diasDeCriacao: 2);

        $this->assertSame(1, $this->executar()['atrasados']);
        $this->assertSame(['Follow-up Pendente'], $this->titulos($consultor));
        $this->assertNotNull(DB::table('interessado')->where('id', $lead->id)->value('ultimo_alerta_em'));

        // Mesmo lead, ainda atrasado, no dia seguinte: não repete o aviso.
        $this->assertSame(0, $this->executar()['atrasados']);
        $this->assertSame(1, $consultor->notifications()->count());

        // Passado o intervalo configurado, avisa de novo.
        DB::table('interessado')->where('id', $lead->id)->update(['ultimo_alerta_em' => now()->subDays(4)]);
        $this->assertSame(1, $this->executar()['atrasados']);
        $this->assertSame(2, $consultor->notifications()->count());
    }

    public function test_intervalo_entre_avisos_e_configuravel(): void
    {
        config(['crm.alertas.intervalo_dias' => 1]);

        $consultor = $this->consultor();
        $lead = $this->lead(['usuario_id' => $consultor->id, 'data_proximo_contato' => now()->subDay()], diasDeCriacao: 2);
        DB::table('interessado')->where('id', $lead->id)->update(['ultimo_alerta_em' => now()->subHours(30)]);

        $this->assertSame(1, $this->executar()['atrasados']);
    }

    public function test_lead_estagnado_avisa_o_consultor(): void
    {
        $consultor = $this->consultor();
        $this->lead(['usuario_id' => $consultor->id], diasDeCriacao: 10);

        $resultado = $this->executar();

        $this->assertSame(1, $resultado['estagnados']);
        $this->assertSame(0, $resultado['atrasados']);
        $this->assertSame(['Lead Estagnado'], $this->titulos($consultor));
    }

    public function test_lead_atrasado_e_estagnado_gera_um_unico_aviso(): void
    {
        $consultor = $this->consultor();
        $this->lead(['usuario_id' => $consultor->id, 'data_proximo_contato' => now()->subDay()], diasDeCriacao: 10);

        $resultado = $this->executar();

        $this->assertSame(1, $resultado['atrasados']);
        $this->assertSame(0, $resultado['estagnados']);
        $this->assertSame(1, $consultor->notifications()->count());
    }

    public function test_registro_automatico_da_regua_nao_esconde_o_lead_estagnado(): void
    {
        $consultor = $this->consultor();
        $lead = $this->lead(['usuario_id' => $consultor->id], diasDeCriacao: 10);

        HistoricoContato::create([
            'interessado_id' => $lead->id,
            'tipo_contato_interessado_id' => TipoContatoInteressado::create(['nome' => 'E-mail'])->id,
            'relato' => 'E-mail automático enviado pela Régua',
            'data_contato' => now()->subDay(),
            'automatico' => true,
        ]);

        $this->assertSame(1, $this->executar()['estagnados']);
    }

    public function test_leads_finalizados_e_com_retorno_futuro_nao_geram_alerta(): void
    {
        $consultor = $this->consultor();

        $this->lead(['usuario_id' => $consultor->id, 'status_interessado_id' => $this->matriculado->id, 'data_proximo_contato' => now()->subDays(9)], diasDeCriacao: 20);
        $this->lead(['usuario_id' => $consultor->id, 'data_proximo_contato' => now()->addDays(2)]);

        $resultado = $this->executar();

        $this->assertSame(0, $resultado['atrasados'] + $resultado['estagnados']);
        $this->assertSame(0, $consultor->notifications()->count());
    }

    public function test_alertar_nao_altera_updated_at_do_lead(): void
    {
        $consultor = $this->consultor();
        $lead = $this->lead(['usuario_id' => $consultor->id, 'data_proximo_contato' => now()->subDay()], diasDeCriacao: 2);
        $atualizadoAntes = $lead->updated_at->timestamp;

        $this->travel(2)->hours();
        $this->executar();

        $this->assertSame($atualizadoAntes, $lead->fresh()->updated_at->timestamp);
    }

    public function test_lead_sem_consultor_entra_no_resumo_diario_da_gestao(): void
    {
        $gestor = $this->gestor();
        $gestorInativo = $this->gestor(ativo: false);

        $this->lead(diasDeCriacao: 2);
        $this->lead(diasDeCriacao: 3);
        $this->lead(); // recém-chegado: ainda dentro do prazo de atribuição

        $resultado = $this->executar();

        $this->assertSame(2, $resultado['sem_consultor']);
        $this->assertContains('Leads sem consultor responsável', $this->titulos($gestor));
        $this->assertSame([], $this->titulos($gestorInativo), 'Conta desativada não recebe alertas.');

        // Rodar de novo no mesmo dia não duplica o resumo.
        $this->executar();
        $this->assertSame(1, collect($this->titulos($gestor))->filter(fn ($t) => $t === 'Leads sem consultor responsável')->count());
    }

    public function test_resumo_da_gestao_aponta_para_a_lista_filtrada_de_leads_sem_consultor(): void
    {
        $gestor = $this->gestor();
        $this->lead(diasDeCriacao: 2);

        $this->executar();

        $acao = $gestor->notifications()->first()->data['actions'][0] ?? [];
        $this->assertStringContainsString('sem_consultor', urldecode($acao['url'] ?? ''));
    }

    public function test_lead_muito_atrasado_e_levado_a_gestao_em_um_unico_resumo(): void
    {
        $gestor = $this->gestor();
        $consultor = $this->consultor();

        $this->lead(['usuario_id' => $consultor->id, 'data_proximo_contato' => now()->subDays(9)], diasDeCriacao: 12);
        $this->lead(['usuario_id' => $consultor->id, 'data_proximo_contato' => now()->subDays(10)], diasDeCriacao: 12);
        $this->lead(['usuario_id' => $consultor->id, 'data_proximo_contato' => now()->subDays(2)], diasDeCriacao: 12);

        $resultado = $this->executar();

        $this->assertSame(3, $resultado['atrasados']);
        $this->assertSame(2, $resultado['escalonados']);
        $this->assertSame(['Leads parados exigem atenção da gestão'], $this->titulos($gestor));
        $this->assertStringContainsString('2 lead(s)', $gestor->notifications()->first()->data['body']);
    }

    public function test_sem_atrasos_nem_leads_sem_consultor_a_gestao_nao_e_incomodada(): void
    {
        $gestor = $this->gestor();
        $consultor = $this->consultor();

        $this->lead(['usuario_id' => $consultor->id, 'data_proximo_contato' => now()->subDay()], diasDeCriacao: 2);

        $this->executar();

        $this->assertSame([], $this->titulos($gestor));
    }

    public function test_comando_resume_o_que_foi_enviado(): void
    {
        $this->gestor();
        $consultor = $this->consultor();
        $this->lead(['usuario_id' => $consultor->id, 'data_proximo_contato' => now()->subDay()], diasDeCriacao: 2);
        $this->lead(diasDeCriacao: 2);

        $this->artisan('crm:notificar-pendentes')
            ->expectsOutputToContain('Notificações enviadas para 1 lead(s) (1 atrasado(s), 0 estagnado(s)).')
            ->expectsOutputToContain('1 lead(s) ativo(s) sem consultor responsável')
            ->assertSuccessful();
    }

    public function test_comando_sem_pendencias_informa_que_nao_ha_nada_a_fazer(): void
    {
        $this->artisan('crm:notificar-pendentes')
            ->expectsOutputToContain('Nenhum lead pendente de contato, estagnado ou sem consultor encontrado.')
            ->assertSuccessful();
    }
}
