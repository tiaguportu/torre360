<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Services\ContadoresCrm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * As seis abas da listagem de Interessados (11 `count()`, vários com `whereHas` aninhado) e o selo "Novo" do
 * menu eram recalculados a cada render. Agora ficam em cache e são descartados quando os dados mudam.
 */
class ContadoresCrmTest extends TestCase
{
    use RefreshDatabase;

    private StatusInteressado $novo;

    private StatusInteressado $matriculado;

    private OrigemInteressado $origem;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['crm.contadores.cache_segundos' => 60]);

        $this->novo = StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $this->matriculado = StatusInteressado::create(['nome' => 'Matriculado', 'cor' => 'success', 'ordem' => 2, 'is_final' => true, 'is_ganho' => true]);
        $this->origem = OrigemInteressado::create(['nome' => 'Site']);
    }

    private function lead(array $atributos = []): Interessado
    {
        return Interessado::create($atributos + [
            'pessoa_id' => Pessoa::factory()->create()->id,
            'status_interessado_id' => $this->novo->id,
            'origem_interessado_id' => $this->origem->id,
        ]);
    }

    private function abas(): array
    {
        return ContadoresCrm::abas(fn (): Builder => Interessado::query(), 1);
    }

    public function test_abas_contam_cada_grupo_da_listagem(): void
    {
        $this->lead();
        $this->lead(['data_proximo_contato' => now()->subDays(2)]);
        $this->lead(['temperatura' => 'quente']);
        $this->lead(['status_interessado_id' => $this->matriculado->id]);

        $abas = $this->abas();

        $this->assertSame(4, $abas['todos']);
        $this->assertSame(3, $abas['ativos']);
        $this->assertSame(1, $abas['precisa_contato']);
        $this->assertSame(1, $abas['quentes']);
        $this->assertSame(1, $abas['finalizados']);
        $this->assertArrayHasKey('estagnados', $abas);
    }

    public function test_resultado_fica_em_cache_ate_alguem_mudar_os_dados(): void
    {
        $this->lead();
        $this->assertSame(1, $this->abas()['todos']);

        // Escrita que não passa pelo Eloquent (ex.: importação direta no banco) não invalida: vale o TTL.
        DB::table('interessado')->insert([
            'pessoa_id' => Pessoa::factory()->create()->id,
            'status_interessado_id' => $this->novo->id,
            'origem_interessado_id' => $this->origem->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertSame(1, $this->abas()['todos']);

        // Gravar um lead pelo modelo descarta o cache.
        $this->lead();
        $this->assertSame(3, $this->abas()['todos']);
    }

    public function test_segunda_leitura_nao_consulta_o_banco(): void
    {
        $this->lead();
        $this->abas();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->abas();
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(0, $consultas);
    }

    public function test_cada_usuario_tem_o_seu_contador(): void
    {
        $this->lead();

        $this->assertSame(1, ContadoresCrm::abas(fn (): Builder => Interessado::query(), 1)['todos']);
        // Outro usuário vê um recorte diferente (aqui, nenhum lead) e não herda o valor do primeiro.
        $this->assertSame(0, ContadoresCrm::abas(fn (): Builder => Interessado::query()->whereRaw('1 = 0'), 2)['todos']);
        $this->assertSame(1, ContadoresCrm::abas(fn (): Builder => Interessado::query()->whereRaw('1 = 0'), 1)['todos']);
    }

    public function test_excluir_lead_atualiza_os_contadores(): void
    {
        $lead = $this->lead();
        $this->lead();
        $this->assertSame(2, $this->abas()['todos']);

        $lead->delete();

        $this->assertSame(1, $this->abas()['todos']);
    }

    public function test_mover_lead_para_etapa_final_atualiza_as_abas(): void
    {
        $lead = $this->lead();
        $this->assertSame(1, $this->abas()['ativos']);
        $this->assertSame(0, $this->abas()['finalizados']);

        $lead->update(['status_interessado_id' => $this->matriculado->id]);

        $abas = $this->abas();
        $this->assertSame(0, $abas['ativos']);
        $this->assertSame(1, $abas['finalizados']);
    }

    public function test_registrar_contato_tira_o_lead_da_aba_de_estagnados(): void
    {
        $lead = $this->lead();
        DB::table('interessado')->where('id', $lead->id)->update(['created_at' => now()->subDays(30)]);
        ContadoresCrm::invalidar();

        $this->assertSame(1, $this->abas()['estagnados']);

        HistoricoContato::create([
            'interessado_id' => $lead->id,
            'tipo_contato_interessado_id' => TipoContatoInteressado::firstOrCreate(['nome' => 'Telefone'])->id,
            'relato' => 'Falei com a família',
            'data_contato' => now(),
        ]);

        $this->assertSame(0, $this->abas()['estagnados']);
    }

    public function test_selo_novos_conta_a_etapa_inicial_e_acompanha_novos_leads(): void
    {
        $this->assertSame(0, ContadoresCrm::novos());

        $this->lead();
        $this->lead(['status_interessado_id' => $this->matriculado->id]);

        $this->assertSame(1, ContadoresCrm::novos());

        $this->lead();

        $this->assertSame(2, ContadoresCrm::novos());
    }

    public function test_alterar_etapa_do_funil_descarta_os_contadores(): void
    {
        $this->lead();
        $this->assertSame(1, ContadoresCrm::novos());

        // "Novo" renomeada: a etapa inicial passa a ser a primeira ativa do funil, e o selo precisa refletir isso.
        $this->novo->update(['nome' => 'Primeiro contato']);
        $this->assertSame(1, ContadoresCrm::novos());

        // Sem nenhuma etapa ativa, não há etapa inicial e o selo some.
        $this->novo->update(['is_final' => true]);
        $this->assertSame(0, ContadoresCrm::novos());
    }
}
