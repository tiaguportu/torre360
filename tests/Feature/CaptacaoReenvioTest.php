<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\AgradecimentoInteresseMail;
use App\Models\Curso;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\Unidade;
use App\Models\User;
use App\Models\VisitaInteressado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Formulário público (`/quero-matricular`): quem reenvia o formulário não pode ter o lead sobrescrito
 * (status, observações do consultor, dependentes) — o reenvio vira um registro na linha do tempo.
 */
class CaptacaoReenvioTest extends TestCase
{
    use RefreshDatabase;

    private StatusInteressado $novo;

    private StatusInteressado $atendimento;

    private StatusInteressado $matriculado;

    private StatusInteressado $perdido;

    private Unidade $unidade;

    private Serie $serie;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.recaptcha.site_key' => null, 'services.recaptcha.secret' => null]);

        $this->novo = StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $this->atendimento = StatusInteressado::create(['nome' => 'Em Atendimento', 'cor' => 'warning', 'ordem' => 2, 'is_final' => false, 'is_ganho' => false]);
        $this->matriculado = StatusInteressado::create(['nome' => 'Matriculado', 'cor' => 'success', 'ordem' => 3, 'is_final' => true, 'is_ganho' => true]);
        $this->perdido = StatusInteressado::create(['nome' => 'Perdido', 'cor' => 'danger', 'ordem' => 4, 'is_final' => true, 'is_ganho' => false]);

        OrigemInteressado::firstOrCreate(['nome' => 'Site']);
        Permission::firstOrCreate(['name' => 'View:Interessado', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->unidade = Unidade::create(['nome' => 'Unidade Sede', 'flag_ativo' => true]);
        $curso = Curso::create(['nome_externo' => 'Ensino Fundamental', 'nome_interno' => 'EF', 'unidade_id' => $this->unidade->id]);
        $this->serie = Serie::create(['nome' => '1º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);

        Mail::fake();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $extra = [], array $aluno = []): array
    {
        return array_merge([
            'tipo_preenchimento' => 'responsavel',
            'responsavel_nome' => 'Maria Responsável',
            'responsavel_telefone' => '(11) 99999-0000',
            'responsavel_email' => 'maria.reenvio@example.com',
            'consentimento' => '1',
            'alunos' => [array_merge([
                'nome' => 'João Aluno',
                'serie_id' => $this->serie->id,
                'unidade_id' => $this->unidade->id,
                'turno_preferencia' => 'Manhã',
            ], $aluno)],
        ], $extra);
    }

    private function enviar(array $extra = [], array $aluno = []): void
    {
        $this->post('/quero-matricular', $this->payload($extra, $aluno))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('captacao.interessado.sucesso'));
    }

    // ─── Lead novo ──────────────────────────────────────────────

    public function test_lead_novo_entra_na_etapa_inicial_com_unidade_e_turno_estruturados(): void
    {
        $this->enviar();

        $lead = Interessado::with(['status', 'dependentes'])->firstOrFail();

        $this->assertSame($this->novo->id, $lead->status_interessado_id);
        $this->assertNull($lead->data_primeiro_contato, 'O primeiro contato é da escola com a família, não o cadastro.');
        $this->assertNotNull($lead->data_proximo_contato);

        $dependente = $lead->dependentes->firstOrFail();
        $this->assertSame($this->unidade->id, $dependente->unidade_id);
        $this->assertSame('Manhã', $dependente->turno_preferencia);
        $this->assertSame($this->serie->id, $dependente->serie_id);
        $this->assertStringContainsString('Unidade: Unidade Sede', $lead->observacoes);
    }

    public function test_etapa_inicial_renomeada_e_usada_em_vez_de_um_id_fixo(): void
    {
        $this->novo->update(['nome' => 'Lead recebido']);

        $this->enviar();

        $this->assertSame($this->novo->id, Interessado::firstOrFail()->status_interessado_id);
    }

    public function test_formulario_nao_quebra_quando_a_permissao_view_interessado_ainda_nao_existe(): void
    {
        Permission::query()->where('name', 'View:Interessado')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::factory()->create(['activated_at' => now()->subDay()]);
        $admin->assignRole('admin');

        $this->enviar();

        $this->assertSame(1, Interessado::count());
        $this->assertSame(1, $admin->notifications()->count());
    }

    // ─── Reenvio ────────────────────────────────────────────────

    public function test_reenviar_o_formulario_nao_sobrescreve_status_observacoes_nem_consultor(): void
    {
        $this->enviar();
        $lead = Interessado::firstOrFail();
        $consultor = User::factory()->create();
        $lead->update([
            'status_interessado_id' => $this->atendimento->id,
            'usuario_id' => $consultor->id,
            'observacoes' => 'Anotação do consultor: família prefere período integral.',
            'data_proximo_contato' => now()->addHours(5),
        ]);
        $proximo = $lead->fresh()->data_proximo_contato->timestamp;

        $this->enviar(['observacoes' => 'Quero saber o valor.']);

        $lead->refresh();
        $this->assertSame(1, Interessado::count());
        $this->assertSame($this->atendimento->id, $lead->status_interessado_id);
        $this->assertSame($consultor->id, $lead->usuario_id);
        $this->assertSame('Anotação do consultor: família prefere período integral.', $lead->observacoes);
        $this->assertSame($proximo, $lead->data_proximo_contato->timestamp, 'Um retorno já marcado para antes não é adiado.');
    }

    public function test_reenvio_vira_registro_na_linha_do_tempo_e_conta_como_interacao_da_familia(): void
    {
        $this->enviar();

        $this->enviar(['observacoes' => 'Quero saber o valor.'], ['nome' => 'Joãozinho Irmão']);

        $historico = HistoricoContato::with('tipoContato')->firstOrFail();
        $this->assertSame('Formulário do Site', $historico->tipoContato->nome);
        $this->assertFalse($historico->automatico);
        $this->assertNull($historico->usuario_id);
        $this->assertStringContainsString('Quero saber o valor.', $historico->relato);
        $this->assertStringContainsString('Joãozinho Irmão', $historico->relato);
    }

    public function test_reenvio_antecipa_o_proximo_contato_quando_ele_estava_atrasado_ou_distante(): void
    {
        $this->enviar();
        $lead = Interessado::firstOrFail();

        $lead->update(['data_proximo_contato' => now()->subDays(3)]);
        $this->enviar();
        $this->assertTrue($lead->fresh()->data_proximo_contato->isFuture());
        $this->assertTrue($lead->fresh()->data_proximo_contato->lte(now()->addDay()->addMinute()));

        $lead->update(['data_proximo_contato' => now()->addDays(20)]);
        $this->enviar();
        $this->assertTrue($lead->fresh()->data_proximo_contato->lte(now()->addDay()->addMinute()));
    }

    public function test_dependentes_nao_sao_apagados_e_mantem_visitas_vinculadas(): void
    {
        $this->enviar();
        $lead = Interessado::firstOrFail();
        $dependente = $lead->dependentes()->firstOrFail();

        $visita = VisitaInteressado::factory()->create([
            'interessado_id' => $lead->id,
            'interessado_dependente_id' => $dependente->id,
        ]);

        // Mesmo aluno com caixa/acento/espaços diferentes + um segundo aluno.
        $this->post('/quero-matricular', $this->payload(extra: [
            'alunos' => [
                ['nome' => '  joao   ALUNO ', 'serie_id' => $this->serie->id],
                ['nome' => 'Pedro Aluno'],
            ],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2, $lead->dependentes()->count());
        $this->assertTrue($lead->dependentes()->whereKey($dependente->id)->exists(), 'O dependente original continua o mesmo registro.');
        $this->assertSame($dependente->id, $visita->fresh()->interessado_dependente_id);
    }

    public function test_reenvio_so_completa_o_que_estava_vazio_no_dependente(): void
    {
        $this->enviar(aluno: ['serie_id' => null, 'unidade_id' => null, 'turno_preferencia' => null]);
        $dependente = Interessado::firstOrFail()->dependentes()->firstOrFail();
        $this->assertNull($dependente->serie_id);

        $outraSerie = Serie::create(['nome' => '2º Ano', 'curso_id' => $this->serie->curso_id, 'sistema_avaliacao' => 'Nota']);

        $this->enviar(aluno: ['serie_id' => $this->serie->id, 'turno_preferencia' => 'Integral', 'data_nascimento' => '2018-03-10']);
        $dependente->refresh();
        $this->assertSame($this->serie->id, $dependente->serie_id);
        $this->assertSame('Integral', $dependente->turno_preferencia);
        $this->assertSame('2018-03-10', $dependente->data_nascimento->toDateString());

        // O que já estava preenchido não é trocado.
        $this->enviar(aluno: ['serie_id' => $outraSerie->id, 'turno_preferencia' => 'Tarde']);
        $dependente->refresh();
        $this->assertSame($this->serie->id, $dependente->serie_id);
        $this->assertSame('Integral', $dependente->turno_preferencia);
    }

    public function test_mesmo_nome_com_nascimentos_diferentes_sao_alunos_diferentes(): void
    {
        $this->enviar(aluno: ['data_nascimento' => '2016-01-01']);
        $this->enviar(aluno: ['data_nascimento' => '2019-06-06']);

        $this->assertSame(2, InteressadoDependente::count());
    }

    public function test_lead_perdido_que_volta_a_preencher_o_formulario_e_reaberto(): void
    {
        $this->enviar();
        $lead = Interessado::firstOrFail();
        $lead->update(['status_interessado_id' => $this->perdido->id, 'motivo_perda' => 'Preço']);
        $admin = User::factory()->create(['activated_at' => now()->subDay()]);
        $admin->assignRole('admin');
        $admin->notifications()->delete();

        $this->enviar();

        $lead->refresh();
        $this->assertSame($this->novo->id, $lead->status_interessado_id);
        $this->assertNull($lead->motivo_perda);
        $this->assertStringContainsString('lead reaberto', HistoricoContato::firstOrFail()->relato);
        $this->assertSame('Lead perdido voltou a procurar a escola!', $admin->notifications()->first()->data['title']);
    }

    public function test_familia_ja_matriculada_nao_e_reaberta_mas_a_equipe_e_avisada(): void
    {
        $this->enviar();
        $lead = Interessado::firstOrFail();
        $lead->update(['status_interessado_id' => $this->matriculado->id, 'data_conversao' => now()->subMonth(), 'data_proximo_contato' => null]);
        $admin = User::factory()->create(['activated_at' => now()->subDay()]);
        $admin->assignRole('admin');
        $admin->notifications()->delete();

        $this->enviar(aluno: ['nome' => 'Irmão Novo']);

        $lead->refresh();
        $this->assertSame($this->matriculado->id, $lead->status_interessado_id);
        $this->assertNull($lead->data_proximo_contato);
        $this->assertSame(2, $lead->dependentes()->count());
        $this->assertSame('Família matriculada enviou novo interesse', $admin->notifications()->first()->data['title']);
    }

    // ─── Reconhecimento da pessoa ───────────────────────────────

    public function test_mesmo_telefone_com_outra_mascara_e_outro_email_nao_cria_lead_duplicado(): void
    {
        $this->enviar();

        $this->enviar(['responsavel_email' => 'outro.email@example.com', 'responsavel_telefone' => '11 99999 0000']);

        $this->assertSame(1, Interessado::count());
        $this->assertSame(1, Pessoa::count());
    }

    public function test_telefone_igual_ao_de_quem_nao_e_lead_nao_atrela_o_lead_a_essa_pessoa(): void
    {
        $aluno = Pessoa::factory()->create(['nome' => 'Aluno Matriculado', 'telefone' => '(11) 99999-0000', 'email' => 'aluno@escola.test']);

        $this->enviar();

        $lead = Interessado::firstOrFail();
        $this->assertNotSame($aluno->id, $lead->pessoa_id);
        $this->assertSame(2, Pessoa::count());
    }

    public function test_cpf_valido_reconhece_a_pessoa_e_cpf_invalido_e_ignorado(): void
    {
        $existente = Pessoa::factory()->create(['nome' => 'Maria Antiga', 'email' => 'maria.antiga@example.com', 'cpf' => '52998224725', 'telefone' => '(21) 98888-7777']);
        Interessado::create([
            'pessoa_id' => $existente->id,
            'status_interessado_id' => $this->atendimento->id,
            'origem_interessado_id' => OrigemInteressado::firstOrFail()->id,
        ]);

        $this->enviar(['responsavel_cpf' => '529.982.247-25']);
        $this->assertSame(1, Interessado::count(), 'Mesmo CPF, e-mail e telefone diferentes: é a mesma pessoa.');

        $this->enviar(['responsavel_email' => 'nova.familia@example.com', 'responsavel_telefone' => '(31) 97777-6666', 'responsavel_cpf' => '111.111.111-11']);
        $novaPessoa = Pessoa::where('email', 'nova.familia@example.com')->firstOrFail();
        $this->assertNull($novaPessoa->cpf);
    }

    public function test_pessoa_existente_so_tem_campos_vazios_completados(): void
    {
        $existente = Pessoa::factory()->create(['nome' => 'Nome Original', 'email' => 'maria.reenvio@example.com', 'telefone' => null, 'cpf' => null]);

        $this->enviar(['responsavel_nome' => 'Nome Digitado Agora', 'responsavel_telefone' => '(11) 95555-4444']);

        $existente->refresh();
        $this->assertSame('Nome Original', $existente->nome);
        $this->assertSame('(11) 95555-4444', $existente->telefone);

        $this->enviar(['responsavel_telefone' => '(11) 90000-1111']);
        $this->assertSame('(11) 95555-4444', $existente->fresh()->telefone, 'Telefone já cadastrado não é sobrescrito.');
    }

    // ─── Agradecimento por e-mail ───────────────────────────────

    public function test_email_de_agradecimento_e_enviado_uma_vez_por_pessoa_na_janela(): void
    {
        $this->enviar();
        $this->enviar(aluno: ['nome' => 'Segundo Filho']);
        $this->enviar(aluno: ['nome' => 'Terceiro Filho']);

        Mail::assertQueuedCount(1);
        Mail::assertQueued(AgradecimentoInteresseMail::class);
    }

    public function test_pagina_de_agradecimento_mostra_o_nome_digitado(): void
    {
        $this->enviar();

        $this->post('/quero-matricular', $this->payload(['responsavel_nome' => 'Maria Digitada Agora']))
            ->assertSessionHas('nome_responsavel', 'Maria Digitada Agora');
    }
}
