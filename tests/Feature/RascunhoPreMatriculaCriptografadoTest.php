<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Services\InteressadoMatriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Rascunho de pré-matrícula (CPF, endereço, dados da família) guardado cifrado e apagado quando abandonado
 * (item 6 da auditoria do CRM).
 */
class RascunhoPreMatriculaCriptografadoTest extends TestCase
{
    use RefreshDatabase;

    private const CPF = '52998224725';

    private StatusInteressado $ativo;

    protected function setUp(): void
    {
        parent::setUp();

        config(['crm.lgpd.retencao_rascunho_dias' => 90]);
        $this->ativo = StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
    }

    private function lead(array $atributos = []): Interessado
    {
        return Interessado::create($atributos + [
            'pessoa_id' => Pessoa::factory()->create()->id,
            'status_interessado_id' => $this->ativo->id,
            'origem_interessado_id' => OrigemInteressado::firstOrCreate(['nome' => 'Site'])->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function rascunho(): array
    {
        return ['responsaveis' => [['nome' => 'Ana Paula', 'cpf' => self::CPF, 'endereco' => 'Rua das Flores, 10']]];
    }

    private function bruto(Interessado $lead): ?string
    {
        return DB::table('interessado')->where('id', $lead->id)->value('dados_pre_matricula');
    }

    // ─── Criptografia ───────────────────────────────────────────

    public function test_rascunho_vai_cifrado_para_o_banco_e_volta_igual(): void
    {
        $lead = $this->lead(['dados_pre_matricula' => $this->rascunho()]);

        $bruto = $this->bruto($lead);
        $this->assertStringNotContainsString(self::CPF, (string) $bruto, 'O CPF não pode estar legível na coluna.');
        $this->assertStringNotContainsString('Rua das Flores', (string) $bruto);
        $this->assertJson(Crypt::decryptString((string) $bruto));

        $this->assertSame($this->rascunho(), $lead->fresh()->dados_pre_matricula);
        $this->assertSame(self::CPF, $lead->fresh()->dados_pre_matricula['responsaveis'][0]['cpf']);
    }

    public function test_rascunho_nulo_continua_nulo(): void
    {
        $lead = $this->lead(['dados_pre_matricula' => $this->rascunho()]);
        $lead->update(['dados_pre_matricula' => null]);

        $this->assertNull($this->bruto($lead));
        $this->assertNull($lead->fresh()->dados_pre_matricula);
    }

    public function test_json_puro_gravado_antes_da_criptografia_ainda_e_lido(): void
    {
        $lead = $this->lead();
        DB::table('interessado')->where('id', $lead->id)->update(['dados_pre_matricula' => json_encode($this->rascunho())]);

        $this->assertSame($this->rascunho(), $lead->fresh()->dados_pre_matricula, 'Código novo com dado antigo (migration ainda não rodou) não pode quebrar a ficha.');
    }

    public function test_migration_cifra_os_rascunhos_existentes_e_pode_rodar_de_novo(): void
    {
        $lead = $this->lead();
        DB::table('interessado')->where('id', $lead->id)->update([
            'dados_pre_matricula' => json_encode($this->rascunho()),
            'dados_pre_matricula_em' => null,
            'updated_at' => '2026-03-01 10:00:00',
        ]);
        $migration = require database_path('migrations/2026_10_09_100100_criptografar_dados_pre_matricula_do_interessado.php');

        $migration->up();
        $cifrado = $this->bruto($lead);
        $migration->up();

        $this->assertStringNotContainsString(self::CPF, (string) $cifrado);
        $this->assertSame($cifrado, $this->bruto($lead), 'Segunda execução não pode cifrar de novo.');
        $this->assertSame($this->rascunho(), $lead->fresh()->dados_pre_matricula);
        $this->assertSame('2026-03-01', $lead->fresh()->dados_pre_matricula_em->toDateString(), 'A data do rascunho antigo vem da atualização do lead.');
    }

    public function test_reverter_a_migration_devolve_json_legivel(): void
    {
        $lead = $this->lead(['dados_pre_matricula' => $this->rascunho()]);
        $migration = require database_path('migrations/2026_10_09_100100_criptografar_dados_pre_matricula_do_interessado.php');

        $migration->down();

        $this->assertSame($this->rascunho(), json_decode((string) $this->bruto($lead), true));

        $migration->up(); // restaura o estado esperado pelos demais testes
        $this->assertSame($this->rascunho(), $lead->fresh()->dados_pre_matricula);
    }

    // ─── Retenção ───────────────────────────────────────────────

    private function rascunhoParadoHaDias(Interessado $lead, int $dias): void
    {
        DB::table('interessado')->where('id', $lead->id)->update([
            'dados_pre_matricula_em' => now()->subDays($dias),
            'updated_at' => now()->subDays($dias),
        ]);
    }

    public function test_expurgo_apaga_so_o_rascunho_abandonado(): void
    {
        $abandonado = $this->lead(['dados_pre_matricula' => $this->rascunho()]);
        $this->rascunhoParadoHaDias($abandonado, 120);

        $recente = $this->lead(['dados_pre_matricula' => $this->rascunho()]);
        $this->rascunhoParadoHaDias($recente, 30);

        $convertido = $this->lead(['dados_pre_matricula' => $this->rascunho(), 'data_conversao' => now()->subDays(100)]);
        $this->rascunhoParadoHaDias($convertido, 120);

        $semRascunho = $this->lead();

        $this->artisan('crm:expurgar-rascunhos-pre-matricula')
            ->expectsOutputToContain('1 rascunho(s) de pré-matrícula apagado(s)')
            ->assertSuccessful();

        $this->assertNull($this->bruto($abandonado));
        $this->assertNull($abandonado->fresh()->dados_pre_matricula_em);
        $this->assertNotNull($this->bruto($recente));
        $this->assertNotNull($this->bruto($convertido), 'Lead convertido não é selecionado (a conversão já limpa o rascunho).');
        $this->assertNull($this->bruto($semRascunho));
        $this->assertSame(1, Interessado::whereNull('dados_pre_matricula')->whereKey($abandonado->id)->count());
    }

    public function test_expurgo_preserva_o_rascunho_de_quem_teve_atividade_recente_mas_nao_a_automatica(): void
    {
        $tipo = TipoContatoInteressado::firstOrCreate(['nome' => 'Telefone']);

        $emAndamento = $this->lead(['dados_pre_matricula' => $this->rascunho()]);
        $this->rascunhoParadoHaDias($emAndamento, 120);
        HistoricoContato::create(['interessado_id' => $emAndamento->id, 'tipo_contato_interessado_id' => $tipo->id, 'relato' => 'Falei com a mãe.', 'data_contato' => now()->subDays(10)]);

        $soAutomatico = $this->lead(['dados_pre_matricula' => $this->rascunho()]);
        $this->rascunhoParadoHaDias($soAutomatico, 120);
        HistoricoContato::create(['interessado_id' => $soAutomatico->id, 'tipo_contato_interessado_id' => $tipo->id, 'relato' => 'E-mail da régua.', 'data_contato' => now()->subDays(5), 'automatico' => true]);

        $this->artisan('crm:expurgar-rascunhos-pre-matricula')->assertSuccessful();

        $this->assertNotNull($this->bruto($emAndamento));
        $this->assertNull($this->bruto($soAutomatico), 'E-mail automático não é atividade: o rascunho abandonado some.');
    }

    public function test_expurgo_nao_mexe_na_data_de_atualizacao_do_lead_e_o_dry_run_nao_apaga(): void
    {
        $lead = $this->lead(['dados_pre_matricula' => $this->rascunho()]);
        $this->rascunhoParadoHaDias($lead, 120);
        $atualizadoEm = $lead->fresh()->updated_at->toDateTimeString();

        $this->artisan('crm:expurgar-rascunhos-pre-matricula', ['--dry-run' => true])
            ->expectsOutputToContain('1 rascunho(s) de pré-matrícula seriam apagados')
            ->assertSuccessful();
        $this->assertNotNull($this->bruto($lead));

        $this->artisan('crm:expurgar-rascunhos-pre-matricula')->assertSuccessful();
        $this->assertNull($this->bruto($lead));
        $this->assertSame($atualizadoEm, $lead->fresh()->updated_at->toDateTimeString());
    }

    public function test_rascunho_sem_data_conta_pela_atualizacao_do_lead(): void
    {
        $lead = $this->lead(['dados_pre_matricula' => $this->rascunho()]);
        DB::table('interessado')->where('id', $lead->id)->update(['dados_pre_matricula_em' => null, 'updated_at' => now()->subDays(200)]);

        $this->artisan('crm:expurgar-rascunhos-pre-matricula')->assertSuccessful();

        $this->assertNull($this->bruto($lead));
    }

    public function test_sem_rascunhos_parados_o_comando_informa_e_termina_bem(): void
    {
        $this->lead(['dados_pre_matricula' => $this->rascunho()]);

        $this->artisan('crm:expurgar-rascunhos-pre-matricula')
            ->expectsOutputToContain('Nenhum rascunho de pré-matrícula parado há mais de 90 dias.')
            ->assertSuccessful();
    }

    public function test_conversao_limpa_a_data_do_rascunho_junto_com_o_rascunho(): void
    {
        $lead = $this->lead(['dados_pre_matricula' => $this->rascunho(), 'dados_pre_matricula_em' => now()]);
        StatusInteressado::create(['nome' => 'Matriculado', 'cor' => 'success', 'ordem' => 2, 'is_final' => true, 'is_ganho' => true]);

        InteressadoMatriculaService::registrarConversao($lead->fresh());

        $lead->refresh();
        $this->assertNull($lead->dados_pre_matricula);
        $this->assertNull($lead->dados_pre_matricula_em);
    }
}
