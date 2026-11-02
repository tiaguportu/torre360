<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NivelAlcadaComercial;
use App\Enums\StatusBolsa;
use App\Enums\StatusPropostaComercial;
use App\Filament\Pages\EnrollmentWizard;
use App\Filament\Resources\PropostaComercials\Pages\ListPropostaComercials;
use App\Models\BolsaConcedida;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\PropostaComercial;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Notifications\PropostaComercialNotification;
use App\Services\PropostaAceiteService;
use App\Services\Pendencias\Fontes\PropostasAguardandoAprovacao;
use App\Services\Pendencias\Fontes\PropostasAVencer;
use App\Services\RevenueManagementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Proposta comercial com aceite da família (Onda 87): link público, aceite, expiração, renovação e o desconto que chega ao
 * contrato como bolsa. "Hoje" é quarta-feira, 21/10/2026.
 */
class PropostaAceiteTest extends TestCase
{
    use RefreshDatabase;

    private Unidade $unidade;

    private Curso $curso;

    private Serie $serie;

    private User $consultor;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00:00'));
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['admin', 'secretaria', 'coordenador'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        foreach (['ViewAny:PropostaComercial', 'View:PropostaComercial', 'Update:PropostaComercial'] as $nome) {
            $permissao = Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']);
            Role::findByName('admin')->givePermissionTo($permissao);
            Role::findByName('secretaria')->givePermissionTo($permissao);
        }

        $this->consultor = $this->usuario('secretaria');
        $this->admin = $this->usuario('admin');

        $this->unidade = Unidade::create(['nome' => 'Unidade Central', 'situacao_funcionamento' => '1']);
        $this->curso = Curso::create(['unidade_id' => $this->unidade->id, 'nome_externo' => 'Ensino Fundamental', 'nome_interno' => 'Ensino Fundamental']);
        $this->serie = Serie::create(['curso_id' => $this->curso->id, 'nome' => '6º Ano', 'sistema_avaliacao' => 'Nota']);
    }

    private function usuario(string $papel): User
    {
        $usuario = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $usuario->assignRole($papel);

        return $usuario;
    }

    private function como(User $usuario): User
    {
        session(['active_role' => $usuario->getRoleNames()->first()]);
        $this->actingAs($usuario);

        return $usuario;
    }

    private function servico(): PropostaAceiteService
    {
        return app(PropostaAceiteService::class);
    }

    private function proposta(array $extra = []): PropostaComercial
    {
        return PropostaComercial::create($extra + [
            'codigo' => PropostaComercial::gerarCodigo(),
            'responsavel_nome' => 'Maria da Silva',
            'responsavel_email' => 'maria@email.test',
            'aluno_nome' => 'João da Silva',
            'unidade_id' => $this->unidade->id,
            'curso_id' => $this->curso->id,
            'serie_id' => $this->serie->id,
            'quantidade_alunos' => 1,
            'valor_tabela_mensal' => 1000.00,
            'tipo_desconto' => 'percentual',
            'desconto_solicitado' => 10.00,
            'valor_desconto_mensal' => 100.00,
            'valor_liquido_mensal' => 900.00,
            'quantidade_parcelas' => 12,
            'valor_total_anual' => 10800.00,
            'status' => StatusPropostaComercial::Aprovada,
            'nivel_alcada_necessario' => NivelAlcadaComercial::Coordenacao,
            'solicitado_por_user_id' => $this->consultor->id,
            'aprovado_por_user_id' => $this->admin->id,
            'aprovado_em' => now(),
            'validade' => today()->addDays(5),
            'motivo_desconto' => 'MOTIVO-INTERNO-DO-DESCONTO',
        ]);
    }

    // --- Link e página pública ----------------------------------------------------------------------------------------

    public function test_so_proposta_aprovada_tem_link_e_o_token_e_estavel(): void
    {
        $rascunho = $this->proposta(['status' => StatusPropostaComercial::AguardandoAprovacao]);

        try {
            $this->servico()->gerarLink($rascunho);
            $this->fail('Proposta em aprovação não tem link.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('aprovada', $e->getMessage());
        }

        $proposta = $this->proposta();
        $url = $this->servico()->gerarLink($proposta);

        $this->assertSame(48, strlen($proposta->refresh()->token_publico));
        $this->assertSame($url, $this->servico()->gerarLink($proposta), 'Gerar de novo devolve o mesmo link.');
        $this->assertStringContainsString($proposta->token_publico, app(RevenueManagementService::class)->gerarTextoWhatsapp($proposta));
    }

    public function test_pagina_publica_mostra_as_condicoes_sem_dados_internos_e_nao_e_indexavel(): void
    {
        $proposta = $this->proposta();
        $this->servico()->gerarLink($proposta);

        $this->get(route('proposta.publica.show', $proposta->token_publico))
            ->assertOk()
            ->assertSee('noindex', false)
            ->assertSee('João da Silva')
            ->assertSee('R$ 900,00')
            ->assertSee('não substitui o contrato')
            ->assertDontSee('MOTIVO-INTERNO-DO-DESCONTO')
            ->assertDontSee('Coordenação');

        $this->assertSame(1, $proposta->refresh()->visualizacoes);
        $this->assertNotNull($proposta->visualizada_em);
    }

    public function test_proposta_que_nao_esta_aprovada_responde_404_como_link_inexistente(): void
    {
        foreach ([StatusPropostaComercial::Rascunho, StatusPropostaComercial::AguardandoAprovacao, StatusPropostaComercial::Recusada, StatusPropostaComercial::Cancelada] as $status) {
            $proposta = $this->proposta(['status' => $status]);
            $proposta->forceFill(['token_publico' => Str::random(48)])->save();

            $this->get(route('proposta.publica.show', $proposta->token_publico))->assertNotFound();
        }

        $this->get(route('proposta.publica.show', 'token-que-nao-existe'))->assertNotFound();
    }

    // --- Aceite -------------------------------------------------------------------------------------------------------

    public function test_familia_aceita_e_o_consultor_e_avisado(): void
    {
        $proposta = $this->proposta();
        $this->servico()->gerarLink($proposta);
        Notification::fake();

        $this->post(route('proposta.publica.aceitar', $proposta->token_publico), ['nome' => 'Maria da Silva', 'concordo' => '1'], ['REMOTE_ADDR' => '203.0.113.9'])
            ->assertRedirect(route('proposta.publica.show', $proposta->token_publico));

        $proposta->refresh();
        $this->assertSame(StatusPropostaComercial::AceitaPelaFamilia, $proposta->status);
        $this->assertSame('Maria da Silva', $proposta->aceite_nome);
        $this->assertNotNull($proposta->aceita_em);
        $this->assertNotEmpty($proposta->aceite_ip);
        Notification::assertSentTo($this->consultor, PropostaComercialNotification::class, fn ($n) => $n->toMail($this->consultor)->subject === "A família aceitou a proposta {$proposta->codigo}");

        $this->get(route('proposta.publica.show', $proposta->token_publico))->assertSee('Proposta aceita por Maria da Silva');
    }

    public function test_aceitar_duas_vezes_nao_duplica_o_aviso(): void
    {
        $proposta = $this->proposta();
        $this->servico()->gerarLink($proposta);
        Notification::fake();

        $this->servico()->aceitar($proposta, 'Maria da Silva', '203.0.113.9');
        $this->servico()->aceitar($proposta, 'Maria da Silva', '203.0.113.9');

        Notification::assertSentToTimes($this->consultor, PropostaComercialNotification::class, 1);
    }

    public function test_aceite_exige_nome_completo_e_a_confirmacao(): void
    {
        $proposta = $this->proposta();
        $this->servico()->gerarLink($proposta);

        $this->post(route('proposta.publica.aceitar', $proposta->token_publico), ['nome' => 'Ma', 'concordo' => '1'])->assertSessionHasErrors('nome');
        $this->post(route('proposta.publica.aceitar', $proposta->token_publico), ['nome' => 'Maria da Silva'])->assertSessionHasErrors('concordo');

        $this->assertSame(StatusPropostaComercial::Aprovada, $proposta->refresh()->status);
    }

    public function test_proposta_vencida_nao_pode_ser_aceita(): void
    {
        $proposta = $this->proposta(['validade' => today()->subDay()]);
        $this->servico()->gerarLink($proposta);

        $this->post(route('proposta.publica.aceitar', $proposta->token_publico), ['nome' => 'Maria da Silva', 'concordo' => '1'])->assertSessionHasErrors('nome');

        $this->assertSame(StatusPropostaComercial::Aprovada, $proposta->refresh()->status);
        $this->get(route('proposta.publica.show', $proposta->token_publico))->assertSee('passou da validade');
    }

    public function test_condicao_aceita_nao_muda_mais(): void
    {
        $proposta = $this->proposta();
        $this->servico()->aceitar($proposta, 'Maria da Silva', null);

        $this->expectException(\DomainException::class);

        app(RevenueManagementService::class)->atualizar($proposta->refresh(), ['desconto_solicitado' => 30.00], $this->admin);
    }

    // --- Expiração, aviso e renovação ---------------------------------------------------------------------------------

    public function test_comando_expira_a_aprovada_vencida_e_poupa_a_aceita(): void
    {
        $vencida = $this->proposta(['validade' => today()->subDay()]);
        $emDia = $this->proposta(['validade' => today()->addDay()]);
        $aceita = $this->proposta(['validade' => today()->subDays(3), 'status' => StatusPropostaComercial::AceitaPelaFamilia]);
        Notification::fake();

        $this->artisan('crm:expirar-propostas')->assertSuccessful();

        $this->assertSame(StatusPropostaComercial::Expirada, $vencida->refresh()->status);
        $this->assertNotNull($vencida->expirada_em);
        $this->assertSame(StatusPropostaComercial::Aprovada, $emDia->refresh()->status);
        $this->assertSame(StatusPropostaComercial::AceitaPelaFamilia, $aceita->refresh()->status, 'A família já aceitou: a escola cumpre a condição.');
        Notification::assertSentTo($this->consultor, PropostaComercialNotification::class, fn ($n) => str_contains($n->toMail($this->consultor)->subject, 'expirou'));
    }

    public function test_aviso_de_vencimento_sai_uma_vez_aos_3_dias_e_uma_vez_ao_1_dia(): void
    {
        $tres = $this->proposta(['validade' => today()->addDays(3)]);
        $um = $this->proposta(['validade' => today()->addDay()]);
        $this->proposta(['validade' => today()->addDays(10)]);
        Notification::fake();

        $this->assertSame(2, $this->servico()->avisarVencimentos());
        $this->assertSame(0, $this->servico()->avisarVencimentos(), 'Não repete o aviso no mesmo dia.');

        $this->assertNotNull($tres->refresh()->avisada_3d_em);
        $this->assertNotNull($um->refresh()->avisada_1d_em);
        Notification::assertSentToTimes($this->consultor, PropostaComercialNotification::class, 2);
    }

    public function test_renovar_expirada_sem_nova_aprovacao_quando_a_tabela_nao_mudou(): void
    {
        $turma = Turma::factory()->create(['mensalidade_base' => 1000.00]);
        $proposta = $this->proposta(['turma_id' => $turma->id, 'status' => StatusPropostaComercial::Expirada, 'validade' => today()->subDays(2)]);

        $renovada = $this->servico()->renovar($proposta, $this->consultor);

        $this->assertSame(StatusPropostaComercial::Aprovada, $renovada->status);
        $this->assertSame(today()->addDays(5)->toDateString(), $renovada->validade->toDateString());
        $this->assertNull($renovada->expirada_em);
    }

    public function test_nao_renova_quando_a_tabela_da_turma_mudou(): void
    {
        $turma = Turma::factory()->create(['mensalidade_base' => 1100.00]);
        $proposta = $this->proposta(['turma_id' => $turma->id, 'status' => StatusPropostaComercial::Expirada]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('tabela');

        $this->servico()->renovar($proposta, $this->consultor);
    }

    public function test_so_expirada_pode_ser_renovada(): void
    {
        $this->expectException(\DomainException::class);

        $this->servico()->renovar($this->proposta(), $this->consultor);
    }

    // --- Do aceite ao contrato ----------------------------------------------------------------------------------------

    private function matricula(): Matricula
    {
        return Matricula::factory()->create(['pessoa_id' => Pessoa::factory()->create()->id, 'turma_id' => Turma::factory()->create()->id]);
    }

    public function test_desconto_aprovado_vira_bolsa_comercial_e_a_proposta_fica_convertida(): void
    {
        $proposta = $this->proposta();
        $matricula = $this->matricula();

        $resultado = $this->servico()->converter($proposta, $matricula);

        $this->assertNull($resultado['motivo']);
        $this->assertFalse($resultado['arredondada']);
        $bolsa = $resultado['bolsa'];
        $this->assertSame(10, $bolsa->percentual);
        $this->assertSame('Desconto comercial', $bolsa->tipoBolsa->nome);
        $this->assertSame(StatusBolsa::Aprovada, $bolsa->status);
        $this->assertSame($this->admin->id, $bolsa->aprovado_por_user_id);
        $this->assertSame('2027-10-20', $bolsa->data_fim->format('Y-m-d'), 'A bolsa dura as 12 parcelas da proposta, a partir de hoje.');
        $this->assertSame(10.0, (float) BolsaConcedida::percentualAtivoPara($matricula), 'O gerador de faturas já lê esta bolsa.');

        $proposta->refresh();
        $this->assertSame(StatusPropostaComercial::Convertida, $proposta->status);
        $this->assertSame($matricula->id, $proposta->matricula_id);
        $this->assertNotNull($proposta->convertida_em);
    }

    public function test_converter_duas_vezes_a_mesma_matricula_nao_duplica_a_bolsa(): void
    {
        $proposta = $this->proposta();
        $matricula = $this->matricula();

        $this->servico()->converter($proposta, $matricula);
        $this->servico()->converter($proposta->refresh(), $matricula);

        $this->assertSame(1, BolsaConcedida::where('matricula_id', $matricula->id)->count());
    }

    public function test_proposta_para_dois_irmaos_da_bolsa_a_cada_matricula(): void
    {
        $proposta = $this->proposta(['quantidade_alunos' => 2]);
        $primeira = $this->matricula();
        $segunda = $this->matricula();

        $this->servico()->converter($proposta, $primeira);
        $this->servico()->converter($proposta->refresh(), $segunda);

        $this->assertSame(1, BolsaConcedida::where('matricula_id', $primeira->id)->count());
        $this->assertSame(1, BolsaConcedida::where('matricula_id', $segunda->id)->count());
        $this->assertSame($primeira->id, $proposta->refresh()->matricula_id, 'A primeira matrícula fica como referência.');
    }

    public function test_desconto_fixo_e_convertido_em_percentual_inteiro_e_avisa_o_arredondamento(): void
    {
        $proposta = $this->proposta(['tipo_desconto' => 'fixo', 'desconto_solicitado' => 75.00, 'valor_desconto_mensal' => 75.00, 'valor_liquido_mensal' => 925.00]);

        $resultado = $this->servico()->converter($proposta, $this->matricula());

        $this->assertSame(8, $resultado['bolsa']->percentual, '7,5% arredonda para 8%.');
        $this->assertTrue($resultado['arredondada']);
        $this->assertStringContainsString('arredondado', $resultado['bolsa']->observacao);
    }

    public function test_proposta_expirada_nao_leva_desconto_e_a_matricula_segue(): void
    {
        $proposta = $this->proposta(['status' => StatusPropostaComercial::Expirada]);

        $resultado = $this->servico()->converter($proposta, $this->matricula());

        $this->assertNull($resultado['bolsa']);
        $this->assertStringContainsString('não está aprovada', $resultado['motivo']);
        $this->assertSame(StatusPropostaComercial::Expirada, $proposta->refresh()->status);
        $this->assertSame(0, BolsaConcedida::count());
    }

    public function test_proposta_aprovada_mas_vencida_tambem_nao_leva_desconto(): void
    {
        $proposta = $this->proposta(['validade' => today()->subDay()]);

        $resultado = $this->servico()->converter($proposta, $this->matricula());

        $this->assertNull($resultado['bolsa']);
        $this->assertStringContainsString('passou da validade', $resultado['motivo']);
    }

    public function test_proposta_sem_desconto_converte_sem_criar_bolsa(): void
    {
        $proposta = $this->proposta(['desconto_solicitado' => 0, 'valor_desconto_mensal' => 0, 'valor_liquido_mensal' => 1000.00]);

        $resultado = $this->servico()->converter($proposta, $this->matricula());

        $this->assertNull($resultado['bolsa']);
        $this->assertNull($resultado['motivo']);
        $this->assertSame(StatusPropostaComercial::Convertida, $proposta->refresh()->status);
    }

    public function test_assistente_de_matricula_converte_a_proposta_ao_criar_a_matricula(): void
    {
        $proposta = $this->proposta();
        $matricula = $this->matricula();
        $this->como($this->admin);

        $assistente = new EnrollmentWizard;
        $assistente->propostaId = $proposta->id;
        $converter = new \ReflectionMethod($assistente, 'converterPropostaComercial');
        $converter->invoke($assistente, $matricula);

        $this->assertSame(StatusPropostaComercial::Convertida, $proposta->refresh()->status);
        $this->assertSame(10, BolsaConcedida::where('matricula_id', $matricula->id)->firstOrFail()->percentual);

        // Sem proposta de origem, o assistente não faz nada.
        $outra = $this->matricula();
        $semProposta = new EnrollmentWizard;
        $converter->invoke($semProposta, $outra);
        $this->assertSame(0, BolsaConcedida::where('matricula_id', $outra->id)->count());
    }

    // --- Telas e filas ------------------------------------------------------------------------------------------------

    public function test_consultor_gera_o_link_pela_tabela(): void
    {
        $proposta = $this->proposta();
        $this->como($this->admin);

        // O modal não aparece no HTML do teste; o que importa é que abrir a ação gerou o link da proposta.
        Livewire::test(ListPropostaComercials::class)->mountTableAction('link_publico', $proposta);

        $this->assertSame(48, strlen((string) $proposta->refresh()->token_publico));
        $this->assertNotNull($proposta->enviada_em);
    }

    public function test_renovar_aparece_so_para_expirada_aprovada_antes(): void
    {
        $expirada = $this->proposta(['status' => StatusPropostaComercial::Expirada]);
        $normal = $this->proposta();
        $this->como($this->admin);

        Livewire::test(ListPropostaComercials::class)
            ->assertTableActionVisible('renovar', $expirada)
            ->assertTableActionHidden('renovar', $normal);
    }

    public function test_fila_propostas_a_vencer_mostra_ao_consultor_so_as_dele(): void
    {
        $outro = $this->usuario('secretaria');
        $this->proposta(['validade' => today()->addDays(2)]);
        $this->proposta(['validade' => today()->addDays(2), 'solicitado_por_user_id' => $outro->id]);
        $this->proposta(['validade' => today()->addDays(20)]);
        $this->proposta(['status' => StatusPropostaComercial::AceitaPelaFamilia])->forceFill(['aceita_em' => now()->subDays(4)])->save();

        $fila = new PropostasAVencer;

        $this->assertSame(2, $fila->contar($this->consultor), 'A que vence em 2 dias e a aceita parada; a de 20 dias e a do colega ficam de fora.');
        $this->assertSame(1, $fila->atrasados($this->consultor), 'Só a aceita há mais de 3 dias e sem matrícula.');
        $this->assertSame(3, $fila->contar($this->admin), 'A administração vê as dos dois consultores.');
    }

    public function test_fila_propostas_para_aprovar_respeita_a_alcada(): void
    {
        $this->proposta(['status' => StatusPropostaComercial::AguardandoAprovacao, 'nivel_alcada_necessario' => NivelAlcadaComercial::Coordenacao]);
        $this->proposta(['status' => StatusPropostaComercial::AguardandoAprovacao, 'nivel_alcada_necessario' => NivelAlcadaComercial::Diretoria]);

        $fila = new PropostasAguardandoAprovacao;
        $coordenador = $this->usuario('coordenador');

        $this->assertSame(2, $fila->contar($this->admin));
        $this->assertSame(1, $fila->contar($coordenador), 'O coordenador não cobre desconto de diretoria.');
    }
}
