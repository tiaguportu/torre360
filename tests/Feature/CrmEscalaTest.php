<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\IndicacaoInteressados\Pages\CreateIndicacaoInteressado;
use App\Filament\Resources\Interessados\Pages\ListInteressados;
use App\Filament\Widgets\CrmFollowUpCalendarWidget;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Models\VisitaInteressado;
use App\Services\LeadScoreService;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Itens de escala do CRM (Lote C): busca de pessoas, índices do banco, recálculo de score em lote e janela de
 * datas do calendário.
 */
class CrmEscalaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private StatusInteressado $novo;

    private OrigemInteressado $origem;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['activated_at' => now()]);
        $this->admin->assignRole('super_admin');

        $this->novo = StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $this->origem = OrigemInteressado::create(['nome' => 'Site']);
    }

    private function lead(array $pessoa = [], array $atributos = []): Interessado
    {
        return Interessado::create($atributos + [
            'pessoa_id' => Pessoa::factory()->create($pessoa)->id,
            'status_interessado_id' => $this->novo->id,
            'origem_interessado_id' => $this->origem->id,
        ]);
    }

    // ─── Busca de pessoas ───────────────────────────────────────

    public function test_busca_acha_por_nome_email_telefone_com_ou_sem_mascara_e_cpf(): void
    {
        $maria = Pessoa::factory()->create(['nome' => 'Maria Aparecida', 'email' => 'maria@exemplo.com', 'telefone' => '(11) 99876-5432', 'cpf' => '12345678909']);
        Pessoa::factory()->create(['nome' => 'João Pedro', 'email' => 'joao@exemplo.com', 'telefone' => '(21) 91111-2222', 'cpf' => '98765432100']);

        $achar = fn (string $termo): array => Pessoa::query()->busca($termo)->pluck('id')->all();

        $this->assertSame([$maria->id], $achar('aparecida'));
        $this->assertSame([$maria->id], $achar('maria@exemplo'));
        $this->assertSame([$maria->id], $achar('(11) 99876-5432'));
        $this->assertSame([$maria->id], $achar('11998765432'), 'Telefone digitado sem máscara deve achar o número gravado com máscara.');
        $this->assertSame([$maria->id], $achar('99876'));
        $this->assertSame([$maria->id], $achar('123.456.789-09'));
        $this->assertSame([], $achar('inexistente'));
    }

    public function test_busca_ignora_digitos_curtos_em_telefone_e_cpf(): void
    {
        Pessoa::factory()->create(['nome' => 'Ana', 'telefone' => '(11) 99111-2222', 'cpf' => '11122233344']);

        // "11" casaria com quase todo telefone/CPF; só vale como texto no nome/e-mail.
        $this->assertSame([], Pessoa::query()->busca('11')->pluck('id')->all());
    }

    public function test_busca_nao_confunde_termo_com_curingas_de_sql(): void
    {
        Pessoa::factory()->create(['nome' => 'Carlos', 'email' => 'carlos@exemplo.com']);

        $this->assertSame([], Pessoa::query()->busca("' OR 1=1 --")->pluck('id')->all());
    }

    public function test_listagem_de_interessados_acha_lead_pelo_telefone_digitado_sem_mascara(): void
    {
        $alvo = $this->lead(['nome' => 'Família Alvo', 'telefone' => '(31) 98888-7777']);
        $outro = $this->lead(['nome' => 'Família Outra', 'telefone' => '(41) 97777-6666']);

        Livewire::actingAs($this->admin)
            ->test(ListInteressados::class)
            ->searchTable('31988887777')
            ->assertCanSeeTableRecords([$alvo])
            ->assertCanNotSeeTableRecords([$outro]);
    }

    public function test_selects_de_pessoa_e_lead_buscam_no_servidor_com_limite(): void
    {
        $lead = $this->lead(['nome' => 'Beatriz Souza', 'email' => 'bia@exemplo.com'], ['lead_score' => 42]);
        $this->lead(['nome' => 'Outro Nome']);

        // 60 pessoas com o mesmo prefixo: a busca nunca devolve mais que 50.
        Pessoa::factory()->count(60)->create(['nome' => 'Silva Repetido']);

        Livewire::actingAs($this->admin)
            ->test(CreateIndicacaoInteressado::class)
            ->assertFormFieldExists('indicador_pessoa_id', function (Select $campo) use ($lead): bool {
                $porNome = $campo->getSearchResults('beatriz');
                $limitado = $campo->getSearchResults('silva repetido');

                return array_keys($porNome) === [$lead->pessoa_id]
                    && count($limitado) === 50
                    && ! $campo->isPreloaded();
            })
            ->assertFormFieldExists('interessado_id', function (Select $campo) use ($lead): bool {
                $resultados = $campo->getSearchResults('bia@exemplo');

                return array_keys($resultados) === [$lead->id]
                    && str_contains($resultados[$lead->id], 'Beatriz Souza')
                    && str_contains($resultados[$lead->id], 'Score: 42')
                    && ! $campo->isPreloaded();
            });
    }

    // ─── Índices ────────────────────────────────────────────────

    public function test_migration_de_indices_cria_os_indices_do_crm(): void
    {
        foreach ([
            'interessado' => ['interessado_proximo_contato_idx', 'interessado_status_proximo_idx', 'interessado_status_atualizado_idx', 'interessado_usuario_status_idx', 'interessado_lead_score_idx', 'interessado_data_conversao_idx', 'interessado_created_at_idx'],
            'pessoa' => ['pessoa_email_idx'],
            'visita_interessado' => ['visita_interessado_lead_status_idx'],
        ] as $tabela => $indices) {
            foreach ($indices as $indice) {
                $this->assertTrue(Schema::hasIndex($tabela, $indice), "Falta o índice {$indice} em {$tabela}.");
            }
        }
    }

    public function test_migration_de_indices_pode_rodar_de_novo_sem_erro(): void
    {
        $migration = require database_path('migrations/2026_10_05_090000_add_indices_de_desempenho_ao_crm.php');

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasIndex('interessado', 'interessado_status_proximo_idx'));

        $migration->down();
        $this->assertFalse(Schema::hasIndex('interessado', 'interessado_status_proximo_idx'));
        $this->assertFalse(Schema::hasIndex('pessoa', 'pessoa_email_idx'));

        // `down()` também é seguro quando os índices já não existem.
        $migration->down();
        $migration->up();
        $this->assertTrue(Schema::hasIndex('pessoa', 'pessoa_email_idx'));
    }

    // ─── Score em lote ──────────────────────────────────────────

    public function test_recalculo_em_lote_grava_o_mesmo_score_do_recalculo_individual(): void
    {
        $tipo = TipoContatoInteressado::firstOrCreate(['nome' => 'Telefone']);
        $perto = $this->lead([], ['faixa_distancia_escola' => 'ate_2km', 'temperatura' => 'quente', 'valor_estimado' => 2500]);
        $longe = $this->lead([], ['faixa_distancia_escola' => 'mais_de_10km', 'temperatura' => 'frio']);
        HistoricoContato::create(['interessado_id' => $perto->id, 'tipo_contato_interessado_id' => $tipo->id, 'relato' => 'Ligação', 'data_contato' => now()]);

        $esperado = [
            $perto->id => LeadScoreService::calcular($perto->fresh()),
            $longe->id => LeadScoreService::calcular($longe->fresh()),
        ];
        $this->assertNotSame($esperado[$perto->id], $esperado[$longe->id]);

        DB::table('interessado')->update(['lead_score' => null, 'lead_score_atualizado_em' => null]);

        $recalculados = LeadScoreService::recalcularLote(Interessado::query()->get());

        $this->assertSame(2, $recalculados);
        foreach ($esperado as $id => $score) {
            $linha = DB::table('interessado')->where('id', $id)->first();
            $this->assertSame($score, (int) $linha->lead_score);
            $this->assertNotNull($linha->lead_score_atualizado_em);
        }
    }

    public function test_recalculo_em_lote_nao_faz_consultas_por_lead(): void
    {
        $this->lead();
        $contar = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            LeadScoreService::recalcularLote(Interessado::query()->get());
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        $contar();
        $umLead = $contar();

        foreach (range(1, 9) as $_) {
            $this->lead();
        }

        // 10 leads: as relações vêm uma vez para o lote todo; só o UPDATE de cada lead se repete.
        $dezLeads = $contar();

        $this->assertSame($umLead + 9, $dezLeads, 'O recálculo em lote voltou a consultar relações lead a lead.');
    }

    public function test_recalculo_em_lote_de_colecao_vazia_nao_faz_nada(): void
    {
        $this->assertSame(0, LeadScoreService::recalcularLote(new Collection));
    }

    public function test_comando_de_recalculo_processa_apenas_leads_ativos_em_lotes(): void
    {
        $finalizada = StatusInteressado::create(['nome' => 'Perdido', 'cor' => 'danger', 'ordem' => 9, 'is_final' => true, 'is_ganho' => false]);
        $ativo = $this->lead([], ['temperatura' => 'quente']);
        $perdido = $this->lead([], ['status_interessado_id' => $finalizada->id]);

        DB::table('interessado')->update(['lead_score' => null]);

        $this->artisan('crm:recalcular-lead-score')
            ->expectsOutput('Lead Score recalculado para 1 lead(s) ativo(s).')
            ->assertSuccessful();

        $this->assertNotNull(DB::table('interessado')->where('id', $ativo->id)->value('lead_score'));
        $this->assertNull(DB::table('interessado')->where('id', $perdido->id)->value('lead_score'));
    }

    // ─── Calendário ─────────────────────────────────────────────

    public function test_calendario_so_carrega_follow_ups_e_visitas_dentro_da_janela(): void
    {
        config(['crm.calendario.janela_passado_dias' => 90, 'crm.calendario.janela_futuro_dias' => 180]);

        $dentro = $this->lead([], ['data_proximo_contato' => now()->addDays(10)]);
        $atrasadoRecente = $this->lead([], ['data_proximo_contato' => now()->subDays(30)]);
        $antigo = $this->lead([], ['data_proximo_contato' => now()->subDays(400)]);
        $distante = $this->lead([], ['data_proximo_contato' => now()->addDays(500)]);

        $visitaDentro = VisitaInteressado::factory()->create(['interessado_id' => $dentro->id, 'data_hora' => now()->addDays(5)]);
        $visitaDistante = VisitaInteressado::factory()->create(['interessado_id' => $dentro->id, 'data_hora' => now()->addDays(400)]);

        $ids = collect(
            Livewire::actingAs($this->admin)->test(CrmFollowUpCalendarWidget::class)->instance()->getEvents()
        )->pluck('id');

        $this->assertTrue($ids->contains((string) $dentro->id));
        $this->assertTrue($ids->contains((string) $atrasadoRecente->id));
        $this->assertFalse($ids->contains((string) $antigo->id));
        $this->assertFalse($ids->contains((string) $distante->id));
        $this->assertTrue($ids->contains('visita-'.$visitaDentro->id));
        $this->assertFalse($ids->contains('visita-'.$visitaDistante->id));
    }

    public function test_janela_do_calendario_e_configuravel(): void
    {
        config(['crm.calendario.janela_passado_dias' => 500]);

        $antigo = $this->lead([], ['data_proximo_contato' => now()->subDays(400)]);

        $ids = collect(
            Livewire::actingAs($this->admin)->test(CrmFollowUpCalendarWidget::class)->instance()->getEvents()
        )->pluck('id');

        $this->assertTrue($ids->contains((string) $antigo->id));
    }
}
