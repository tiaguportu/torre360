<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\ListInteressados;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\User;
use App\Services\LeadSlaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * O selo de SLA de um lead respondido ficava verde ("Respondido em dd/mm HH:mm") mesmo quando o primeiro contato
 * veio depois do limite. Agora `resumoSla()` compara a data do primeiro contato com o limite comercial.
 */
class CrmSlaRespostaNoPrazoOuComAtrasoTest extends TestCase
{
    use RefreshDatabase;

    private StatusInteressado $status;

    private OrigemInteressado $origem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->status = StatusInteressado::create(['nome' => 'Novo', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $this->origem = OrigemInteressado::create(['nome' => 'Site']);

        config([
            'crm.sla.primeira_resposta_minutos_padrao' => 120,
            'crm.sla.horario_comercial.inicio' => '08:00',
            'crm.sla.horario_comercial.fim' => '18:00',
            'crm.sla.horario_comercial.dias_uteis' => [1, 2, 3, 4, 5],
        ]);
    }

    /**
     * Cria o lead na data informada (é o início do prazo), já respondido ou não.
     */
    private function lead(Carbon $criadoEm, ?Carbon $respondidoEm = null, array $extras = []): Interessado
    {
        $this->travelTo($criadoEm);

        $lead = Interessado::create([
            'pessoa_id' => Pessoa::create([
                'nome' => 'Lead '.fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'telefone' => '119'.fake()->numerify('########'),
            ])->id,
            'status_interessado_id' => $this->status->id,
            'origem_interessado_id' => $this->origem->id,
            'data_primeiro_contato' => $respondidoEm,
            ...$extras,
        ]);

        return $lead->load('status');
    }

    private function resumo(Interessado $lead, ?Carbon $agora = null): array
    {
        return app(LeadSlaService::class)->resumoSla($lead, $agora);
    }

    public function test_resposta_antes_do_limite_e_no_prazo_e_verde(): void
    {
        // Terça 13/10/2026 10:00 + 120 min úteis = limite 12:00.
        $lead = $this->lead(Carbon::create(2026, 10, 13, 10, 0), Carbon::create(2026, 10, 13, 11, 30));

        $resumo = $this->resumo($lead);

        $this->assertTrue($resumo['respondido']);
        $this->assertTrue($resumo['no_prazo']);
        $this->assertFalse($resumo['estourado']);
        $this->assertSame('Respondido no prazo (13/10 11:30)', $resumo['texto']);
        $this->assertSame('success', $resumo['cor']);
        $this->assertSame('Primeiro contato dentro do prazo. Limite comercial: 13/10/2026 12:00', $resumo['tooltip']);
    }

    public function test_resposta_exatamente_no_limite_ainda_e_no_prazo(): void
    {
        $lead = $this->lead(Carbon::create(2026, 10, 13, 10, 0), Carbon::create(2026, 10, 13, 12, 0));

        $resumo = $this->resumo($lead);

        $this->assertTrue($resumo['no_prazo']);
        $this->assertSame('success', $resumo['cor']);
    }

    public function test_resposta_depois_do_limite_e_com_atraso_e_amarela(): void
    {
        $lead = $this->lead(Carbon::create(2026, 10, 13, 10, 0), Carbon::create(2026, 10, 13, 12, 1));

        $resumo = $this->resumo($lead);

        $this->assertTrue($resumo['respondido']);
        $this->assertFalse($resumo['no_prazo']);
        $this->assertFalse($resumo['estourado'], 'Estourado é só o prazo vencido sem resposta; o que dispara alerta.');
        $this->assertSame('Respondido com atraso (13/10 12:01)', $resumo['texto']);
        $this->assertSame('warning', $resumo['cor']);
        $this->assertSame('Primeiro contato depois do prazo. Limite comercial: 13/10/2026 12:00', $resumo['tooltip']);
    }

    public function test_lead_de_sexta_a_noite_respondido_logo_na_abertura_de_segunda_e_no_prazo(): void
    {
        // Sexta 16/10 17:00: 60 min na sexta + 60 min na segunda 19/10 = limite 09:00.
        $criado = Carbon::create(2026, 10, 16, 17, 0);

        $cedo = $this->lead($criado, Carbon::create(2026, 10, 19, 8, 30));
        $tarde = $this->lead($criado, Carbon::create(2026, 10, 19, 9, 30));

        $this->assertTrue($this->resumo($cedo)['no_prazo']);
        $this->assertSame('Primeiro contato dentro do prazo. Limite comercial: 19/10/2026 09:00', $this->resumo($cedo)['tooltip']);
        $this->assertFalse($this->resumo($tarde)['no_prazo']);
    }

    public function test_prazo_personalizado_do_lead_muda_o_veredito(): void
    {
        $criado = Carbon::create(2026, 10, 13, 10, 0);
        $resposta = Carbon::create(2026, 10, 13, 10, 40);

        $padrao = $this->lead($criado, $resposta);
        $quente = $this->lead($criado, $resposta, ['sla_primeira_resposta_minutos' => 30]);

        $this->assertTrue($this->resumo($padrao)['no_prazo'], '40 min cabem nos 120 min do padrão.');
        $this->assertFalse($this->resumo($quente)['no_prazo'], '40 min estouram o prazo de 30 min do lead.');
    }

    public function test_sem_resposta_nao_tem_veredito_e_mantem_os_estados_anteriores(): void
    {
        $lead = $this->lead(Carbon::create(2026, 10, 13, 10, 0));

        $emCurso = $this->resumo($lead, Carbon::create(2026, 10, 13, 10, 30));
        $this->assertNull($emCurso['no_prazo']);
        $this->assertFalse($emCurso['respondido']);
        $this->assertSame('SLA: resta 1h 30min útil', $emCurso['texto']);
        $this->assertSame('warning', $emCurso['cor']);
        $this->assertSame('Limite comercial: 13/10/2026 12:00', $emCurso['tooltip']);

        $estourado = $this->resumo($lead, Carbon::create(2026, 10, 13, 12, 35));
        $this->assertNull($estourado['no_prazo']);
        $this->assertTrue($estourado['estourado']);
        $this->assertSame('SLA Estourado há 35 min', $estourado['texto']);
        $this->assertSame('danger', $estourado['cor']);
        $this->assertSame('Limite comercial: 13/10/2026 12:00', $estourado['tooltip']);

        $final = StatusInteressado::create(['nome' => 'Perdido', 'ordem' => 9, 'is_final' => true, 'is_ganho' => false]);
        $lead->update(['status_interessado_id' => $final->id]);

        $finalizado = $this->resumo($lead->fresh('status'));
        $this->assertNull($finalizado['no_prazo']);
        $this->assertSame('Finalizado', $finalizado['texto']);
        $this->assertSame('gray', $finalizado['cor']);
    }

    public function test_tabela_mostra_o_selo_e_o_tooltip_de_cada_caso(): void
    {
        $noPrazo = $this->lead(Carbon::create(2026, 10, 13, 10, 0), Carbon::create(2026, 10, 13, 11, 0));
        $atrasado = $this->lead(Carbon::create(2026, 10, 13, 10, 0), Carbon::create(2026, 10, 13, 15, 0));

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $usuario = User::factory()->create(['activated_at' => now()]);
        $usuario->assignRole('super_admin');

        $tabela = Livewire::actingAs($usuario)->test(ListInteressados::class)
            ->assertCanSeeTableRecords([$noPrazo, $atrasado])
            ->assertTableColumnStateSet('sla_status', 'Respondido no prazo (13/10 11:00)', $noPrazo)
            ->assertTableColumnStateSet('sla_status', 'Respondido com atraso (13/10 15:00)', $atrasado);

        $coluna = $tabela->instance()->getTable()->getColumn('sla_status');

        $this->assertSame('success', $coluna->record($noPrazo)->getColor('x'));
        $this->assertSame('warning', $coluna->record($atrasado)->getColor('x'));
        $this->assertSame('Primeiro contato dentro do prazo. Limite comercial: 13/10/2026 12:00', $coluna->record($noPrazo)->getTooltip('x'));
        $this->assertSame('Primeiro contato depois do prazo. Limite comercial: 13/10/2026 12:00', $coluna->record($atrasado)->getTooltip('x'));
    }
}
