<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\InteressadoQualificacao;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\User;
use App\Services\Pendencias\Fontes\QualificacaoIncompleta;
use App\Services\Pendencias\MesaDePendencias;
use App\Services\Qualificacao\QualificacaoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Fila "Leads com qualificação incompleta" da Minha Mesa (SDR, Lote 1).
 *
 * "Hoje" é segunda-feira, 26/10/2026, 10:00.
 */
class QualificacaoMesaTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private User $gestor;

    private User $ana;

    private StatusInteressado $ativo;

    private StatusInteressado $matriculado;

    private OrigemInteressado $origem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-26 10:00:00'));
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['admin', 'comercial'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        foreach (['ViewAny:Interessado', 'View:Interessado', 'Update:Interessado'] as $nome) {
            $permissao = Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']);
            Role::findByName('comercial')->givePermissionTo($permissao);
            Role::findByName('admin')->givePermissionTo($permissao);
        }

        Role::findByName('admin')->givePermissionTo(Permission::firstOrCreate(['name' => 'ViewAll:Interessado', 'guard_name' => 'web']));

        $this->gestor = User::factory()->create(['activated_at' => now()->subMonth(), 'email_verified_at' => now()]);
        $this->gestor->assignRole('admin');
        $this->ana = User::factory()->create(['activated_at' => now()->subMonth(), 'email_verified_at' => now()]);
        $this->ana->assignRole('comercial');

        $this->ativo = StatusInteressado::create(['nome' => 'Em Atendimento', 'cor' => 'warning', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $this->matriculado = StatusInteressado::create(['nome' => 'Matriculado', 'cor' => 'success', 'ordem' => 2, 'is_final' => true, 'is_ganho' => true]);
        $this->origem = OrigemInteressado::create(['nome' => 'Site']);
    }

    private function como(User $usuario): User
    {
        session(['active_role' => $usuario->getRoleNames()->first()]);

        return $usuario;
    }

    private function lead(?User $dono, ?Carbon $primeiroContato, ?StatusInteressado $status = null): Interessado
    {
        $lead = Interessado::create([
            'pessoa_id' => Pessoa::factory()->create()->id,
            'usuario_id' => $dono?->id,
            'status_interessado_id' => ($status ?? $this->ativo)->id,
            'origem_interessado_id' => $this->origem->id,
        ]);

        $lead->forceFill(['data_primeiro_contato' => $primeiroContato])->save();

        return $lead;
    }

    private function qualificar(Interessado $lead): void
    {
        InteressadoDependente::create([
            'interessado_id' => $lead->id,
            'nome_crianca' => 'Lucas',
            'serie_id' => null,
            'data_nascimento' => '2019-05-10',
            'turno_preferencia' => 'Manhã',
            'situacao_atual' => 'primeira_escola',
        ]);

        $servico = app(QualificacaoService::class);
        // Série pretendida precisa existir para valer; marca como recusada para este teste não depender de série.
        $servico->marcarRecusado($lead->fresh(), 'serie_id');

        foreach (['bairro' => 'Centro', 'motivo_busca' => 'primeira_escola', 'decisor_tipo' => 'sozinho', 'decisor_vai_visita' => 'sim', 'prazo_decisao' => 'este_mes'] as $chave => $valor) {
            $servico->registrar($lead->fresh(), $chave, $valor, InteressadoQualificacao::FONTE_CONSULTOR);
        }
    }

    public function test_lista_so_lead_ja_contatado_e_ainda_nao_qualificado(): void
    {
        $contatado = $this->lead($this->ana, now()->subDays(1));
        $semContato = $this->lead($this->ana, null);
        $qualificado = $this->lead($this->ana, now()->subDays(5));
        $this->qualificar($qualificado);
        $encerrado = $this->lead($this->ana, now()->subDays(5), $this->matriculado);

        $fila = app(QualificacaoIncompleta::class);
        $usuario = $this->como($this->ana);

        $this->assertSame(1, $fila->contar($usuario));

        $itens = $fila->itens($usuario, 10);
        $this->assertCount(1, $itens);
        $this->assertStringContainsString('Falta:', $itens[0]->detalhe);
        $this->assertStringContainsString('Série pretendida', $itens[0]->detalhe);
        $this->assertStringContainsString('e mais ', $itens[0]->detalhe, 'Cita só os primeiros campos que faltam.');
        $this->assertStringContainsString($contatado->id.'', $itens[0]->url);
        $this->assertNotNull($semContato);
        $this->assertNotNull($encerrado);
    }

    public function test_fica_atrasado_depois_do_prazo_configurado(): void
    {
        $this->lead($this->ana, now()->subDays(1));
        $this->lead($this->ana, now()->subDays(4));

        $fila = app(QualificacaoIncompleta::class);
        $usuario = $this->como($this->ana);

        $this->assertSame(2, $fila->contar($usuario));
        $this->assertSame(1, $fila->atrasados($usuario), 'Atrasado a partir de 3 dias (crm.mesa.qualificacao_incompleta_dias).');

        config(['crm.mesa.qualificacao_incompleta_dias' => 1]);
        $this->assertSame(2, $fila->atrasados($usuario));
    }

    public function test_respeita_a_carteira_do_consultor(): void
    {
        $this->lead($this->ana, now()->subDays(2));
        $outroConsultor = User::factory()->create(['activated_at' => now()->subMonth(), 'email_verified_at' => now()]);
        $outroConsultor->assignRole('comercial');
        $this->lead($outroConsultor, now()->subDays(2));

        $fila = app(QualificacaoIncompleta::class);

        $this->assertSame(1, $fila->contar($this->como($this->ana)), 'Consultor vê a própria carteira.');
        $this->assertSame(2, $fila->contar($this->como($this->gestor)), 'Quem tem ViewAll vê tudo.');
    }

    public function test_aparece_na_mesa_de_pendencias_para_quem_pode_ver_leads(): void
    {
        $this->lead($this->ana, now()->subDays(2));

        $filas = app(MesaDePendencias::class)->filas($this->como($this->ana));
        $fila = $filas->first(fn ($f) => $f->fonte->chave() === 'leads_qualificacao_incompleta');

        $this->assertNotNull($fila);
        $this->assertFalse($fila->erro);
        $this->assertSame(1, $fila->total);
        $this->assertSame('CRM / Comercial', $fila->fonte->area());
    }
}
