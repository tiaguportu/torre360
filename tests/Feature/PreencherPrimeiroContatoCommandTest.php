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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Backfill de `data_primeiro_contato` dos leads com contato lançado pela Linha do Tempo/Histórico antes de
 * esses caminhos passarem a preenchê-la (`LeadFunilService::registrarPrimeiroContato`).
 */
class PreencherPrimeiroContatoCommandTest extends TestCase
{
    use RefreshDatabase;

    private StatusInteressado $status;

    private OrigemInteressado $origem;

    private TipoContatoInteressado $ligacao;

    private User $consultor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->status = StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $this->origem = OrigemInteressado::create(['nome' => 'Site']);
        $this->ligacao = TipoContatoInteressado::create(['nome' => 'Ligação']);
        $this->consultor = User::factory()->create();
    }

    private function lead(array $atributos = []): Interessado
    {
        return Interessado::create($atributos + [
            'pessoa_id' => Pessoa::factory()->create()->id,
            'status_interessado_id' => $this->status->id,
            'origem_interessado_id' => $this->origem->id,
        ]);
    }

    private function contato(Interessado $lead, string $quando, array $extra = []): HistoricoContato
    {
        return $lead->historicos()->create($extra + [
            'tipo_contato_interessado_id' => $this->ligacao->id,
            'usuario_id' => $this->consultor->id,
            'relato' => 'Contato de teste.',
            'data_contato' => $quando,
        ]);
    }

    /** @return array<string, Interessado> */
    private function cenario(): array
    {
        $lead = [];

        $lead['humano'] = $this->lead();
        $this->contato($lead['humano'], '2026-10-02 10:00:00');
        $this->contato($lead['humano'], '2026-10-01 09:30:00');

        $lead['automatico'] = $this->lead();
        $this->contato($lead['automatico'], '2026-10-01 09:00:00', ['automatico' => true]);

        $lead['funil'] = $this->lead();
        $this->contato($lead['funil'], '2026-10-01 09:00:00', [
            'tipo_contato_interessado_id' => TipoContatoInteressado::porNome(TipoContatoInteressado::FUNIL)->id,
        ]);

        $lead['familia'] = $this->lead();
        $this->contato($lead['familia'], '2026-10-01 09:00:00', ['usuario_id' => null]);

        $lead['ja_preenchido'] = $this->lead(['data_primeiro_contato' => '2026-09-20 08:00:00']);
        $this->contato($lead['ja_preenchido'], '2026-10-01 09:00:00');

        $lead['sem_historico'] = $this->lead();

        return $lead;
    }

    private function dataDe(Interessado $lead): ?string
    {
        return Interessado::query()->whereKey($lead->id)->toBase()->value('data_primeiro_contato');
    }

    public function test_simulacao_nao_altera_nada_e_informa_quantos_leads_seriam_atualizados(): void
    {
        $lead = $this->cenario();

        $this->artisan('crm:preencher-primeiro-contato')
            ->expectsOutputToContain('Simulação: 1 lead(s)')
            ->assertSuccessful();

        $this->assertNull($this->dataDe($lead['humano']));
    }

    public function test_aplicar_preenche_so_o_lead_com_contato_humano_usando_o_registro_mais_antigo(): void
    {
        $lead = $this->cenario();
        $atualizadoEm = Interessado::query()->whereKey($lead['humano']->id)->toBase()->value('updated_at');

        $this->artisan('crm:preencher-primeiro-contato', ['--aplicar' => true])
            ->expectsOutputToContain('preenchida em 1 de 1 lead(s)')
            ->assertSuccessful();

        $this->assertSame('2026-10-01 09:30:00', $this->dataDe($lead['humano']));
        $this->assertNull($this->dataDe($lead['automatico']), 'Registro automático não é contato da escola.');
        $this->assertNull($this->dataDe($lead['funil']), 'Movimentação no funil não é contato da escola.');
        $this->assertNull($this->dataDe($lead['familia']), 'Registro sem usuário (ação da família) não é contato da escola.');
        $this->assertNull($this->dataDe($lead['sem_historico']));
        $this->assertSame('2026-09-20 08:00:00', $this->dataDe($lead['ja_preenchido']), 'Quem já tem a data não é sobrescrito.');
        $this->assertSame($atualizadoEm, Interessado::query()->whereKey($lead['humano']->id)->toBase()->value('updated_at'), 'Correção de dado não mexe em updated_at.');
    }

    public function test_segunda_execucao_nao_encontra_mais_nada(): void
    {
        $this->cenario();

        $this->artisan('crm:preencher-primeiro-contato', ['--aplicar' => true])->assertSuccessful();

        $this->artisan('crm:preencher-primeiro-contato', ['--aplicar' => true])
            ->expectsOutputToContain('Nenhum lead com contato lançado')
            ->assertSuccessful();
    }
}
