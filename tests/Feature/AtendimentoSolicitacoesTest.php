<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ResultadoChamado;
use App\Enums\StatusChamado;
use App\Filament\Portal\Pages\Requerimentos;
use App\Filament\Resources\AtendimentoChamados\Pages\ListAtendimentoChamados;
use App\Models\AtendimentoChamado;
use App\Models\AtendimentoRespostaPronta;
use App\Models\AtendimentoSetor;
use App\Models\AtestadoAluno;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\ChamadoParaEquipeNotification;
use App\Notifications\ChamadoRespondidoNotification;
use App\Services\AtendimentoService;
use App\Services\Pendencias\Fontes\ChamadosComPrazoEstourado;
use App\Services\Pendencias\Fontes\RequerimentosParaAtender;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * P2, parte do atendimento (Ondas 83, 80 e 34): aviso, prazo, assumir, encerramento por inatividade, requerimentos aplicados só
 * depois da conferência e o pedido restrito do titular da LGPD.
 *
 * "Hoje" é quarta-feira, 21/10/2026, 09:00.
 */
class AtendimentoSolicitacoesTest extends TestCase
{
    use RefreshDatabase;

    private AtendimentoSetor $setor;

    private User $secretaria;

    private User $encarregado;

    private User $familia;

    private Pessoa $responsavel;

    private Matricula $matricula;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00:00'));
        Storage::fake('local');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['super_admin', 'admin', 'secretaria', 'responsavel'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        $this->conceder('secretaria', ['ViewAny:AtendimentoChamado', 'View:AtendimentoChamado', 'Update:AtendimentoChamado', 'Aplicar:AtendimentoChamado']);
        $this->conceder('admin', ['ViewAny:AtendimentoChamado', 'View:AtendimentoChamado', 'Update:AtendimentoChamado', 'Aplicar:AtendimentoChamado', 'VerRestritos:AtendimentoChamado']);

        $this->setor = AtendimentoSetor::create(['nome' => 'Secretaria', 'email_notificacao' => 'secretaria@escola.test', 'ativo' => true, 'recebe_requerimentos' => true, 'ordem' => 1]);

        $this->secretaria = $this->usuario('secretaria');
        $this->encarregado = $this->usuario('admin');

        $this->responsavel = Pessoa::factory()->create(['nome' => 'Carlos Responsável', 'telefone' => '11 90000-0000', 'email' => 'antigo@email.test']);
        $this->familia = $this->usuario('responsavel');
        $this->responsavel->users()->attach($this->familia);

        $this->matricula = Matricula::factory()->create([
            'pessoa_id' => Pessoa::factory()->create(['nome' => 'Ana Lima'])->id,
            'turma_id' => Turma::factory()->create()->id,
        ]);
    }

    private function conceder(string $papel, array $permissoes): void
    {
        foreach ($permissoes as $nome) {
            Role::findByName($papel)->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }
    }

    private function usuario(string $papel): User
    {
        $usuario = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $usuario->assignRole($papel);

        return $usuario;
    }

    /** As permissões valem pelo papel ativo da sessão, que é global no teste. */
    private function como(User $usuario): User
    {
        session(['active_role' => $usuario->getRoleNames()->first()]);

        return $usuario;
    }

    private function servico(): AtendimentoService
    {
        return app(AtendimentoService::class);
    }

    private function abrirChamado(array $extra = []): AtendimentoChamado
    {
        return $this->servico()->abrir($extra + [
            'setor_id' => $this->setor->id,
            'solicitante_id' => $this->responsavel->id,
            'matricula_id' => $this->matricula->id,
            'assunto' => 'Segunda via de boleto',
            'mensagem' => 'Preciso da segunda via do boleto de outubro.',
        ]);
    }

    // --- Aviso e prazo (Onda 83) --------------------------------------------------------------------------------------

    public function test_chamado_novo_calcula_prazos_em_horas_uteis_e_avisa_o_setor_e_a_equipe(): void
    {
        Notification::fake();

        $chamado = $this->abrirChamado();

        // 24 h úteis a partir de quarta 09:00: 9 h na quarta, 10 h na quinta e 5 h na sexta (até 13h); 72 h úteis terminam na sexta da outra semana.
        $this->assertSame('2026-10-23 13:00', $chamado->prazo_primeira_resposta_em->format('Y-m-d H:i'));
        $this->assertSame('2026-10-30 11:00', $chamado->prazo_resolucao_em->format('Y-m-d H:i'));
        $this->assertNull($chamado->primeira_resposta_em);

        Notification::assertSentTo($this->secretaria, ChamadoParaEquipeNotification::class);
        Notification::assertSentOnDemand(ChamadoParaEquipeNotification::class, fn ($n, $canais, $notifiable) => $notifiable->routes['mail'] === 'secretaria@escola.test');
        Notification::assertNotSentTo($this->familia, ChamadoParaEquipeNotification::class);
    }

    public function test_setor_sem_prazo_nao_cobra_prazo_do_chamado(): void
    {
        $this->setor->update(['prazo_primeira_resposta_horas' => null, 'prazo_resolucao_horas' => null]);

        // Sem o prazo do setor, valem os padrões de config/atendimento.php; zerados, não há prazo.
        config(['atendimento.prazo_primeira_resposta_horas' => 0, 'atendimento.prazo_resolucao_horas' => 0]);

        $chamado = $this->abrirChamado();

        $this->assertNull($chamado->prazo_primeira_resposta_em);
        $this->assertNull($chamado->prazo_resolucao_em);
    }

    public function test_resposta_da_equipe_marca_a_primeira_resposta_e_avisa_a_familia_sem_o_texto(): void
    {
        $chamado = $this->abrirChamado();
        Notification::fake();

        $this->servico()->responderEquipe($chamado, $this->secretaria, 'O boleto está anexo; o valor é R$ 1.234,56 (dado sigiloso).');

        $chamado->refresh();
        $this->assertNotNull($chamado->primeira_resposta_em);
        $this->assertSame(StatusChamado::EmAndamento, $chamado->status);
        $this->assertSame($this->secretaria->id, $chamado->responsavel_atendimento_id);

        Notification::assertSentTo($this->familia, ChamadoRespondidoNotification::class, function (ChamadoRespondidoNotification $n) {
            $textos = json_encode([$n->toMail($this->familia)->introLines, $n->toDatabase($this->familia), $n->toPush($this->familia)]);

            return ! str_contains($textos, '1.234,56') && ! str_contains($textos, 'boleto está anexo');
        });
    }

    public function test_resposta_da_familia_reabre_o_chamado_e_avisa_quem_atende(): void
    {
        $chamado = $this->abrirChamado();
        $this->servico()->responderEquipe($chamado, $this->secretaria, 'Pode enviar o comprovante?', null, StatusChamado::AguardandoSolicitante);
        $this->assertNotNull($chamado->refresh()->aguardando_solicitante_desde);

        Notification::fake();
        $this->servico()->responderFamilia($chamado, $this->responsavel->id, 'Segue o comprovante.');

        $chamado->refresh();
        $this->assertSame(StatusChamado::EmAndamento, $chamado->status);
        $this->assertNull($chamado->aguardando_solicitante_desde);
        Notification::assertSentTo($this->secretaria, ChamadoParaEquipeNotification::class);
    }

    public function test_assumir_e_atomico_so_o_primeiro_fica_com_o_chamado(): void
    {
        $chamado = $this->abrirChamado();
        $outra = $this->usuario('secretaria');

        $this->assertTrue($this->servico()->assumir($chamado, $this->secretaria));
        $this->assertFalse($this->servico()->assumir($chamado, $outra));
        $this->assertSame($this->secretaria->id, $chamado->refresh()->responsavel_atendimento_id);
    }

    public function test_chamado_esperando_a_familia_ha_mais_de_sete_dias_fica_resolvido_e_reabre_se_ela_responder(): void
    {
        $velho = $this->abrirChamado();
        $this->servico()->responderEquipe($velho, $this->secretaria, 'Falta o documento.', null, StatusChamado::AguardandoSolicitante);
        $velho->forceFill(['aguardando_solicitante_desde' => now()->subDays(8)])->save();

        $recente = $this->abrirChamado();
        $this->servico()->responderEquipe($recente, $this->secretaria, 'Falta o documento.', null, StatusChamado::AguardandoSolicitante);
        $recente->forceFill(['aguardando_solicitante_desde' => now()->subDays(3)])->save();

        Notification::fake();
        $this->assertSame(1, $this->servico()->resolverAguardandoVencidos());

        $this->assertSame(StatusChamado::Resolvido, $velho->refresh()->status);
        $this->assertSame(StatusChamado::AguardandoSolicitante, $recente->refresh()->status);
        Notification::assertSentTo($this->familia, ChamadoRespondidoNotification::class);

        $this->servico()->responderFamilia($velho, $this->responsavel->id, 'Ainda preciso, segue o documento.');
        $this->assertSame(StatusChamado::EmAndamento, $velho->refresh()->status);
    }

    public function test_comando_agendado_resolve_os_chamados_parados(): void
    {
        $chamado = $this->abrirChamado();
        $this->servico()->responderEquipe($chamado, $this->secretaria, 'Falta o documento.', null, StatusChamado::AguardandoSolicitante);
        $chamado->forceFill(['aguardando_solicitante_desde' => now()->subDays(9)])->save();

        $this->artisan('atendimento:resolver-aguardando')->assertSuccessful();

        $this->assertSame(StatusChamado::Resolvido, $chamado->refresh()->status);
    }

    public function test_resposta_pronta_preenche_as_variaveis_do_chamado(): void
    {
        $chamado = $this->abrirChamado();
        $pronta = AtendimentoRespostaPronta::create(['setor_id' => $this->setor->id, 'titulo' => 'Boleto', 'texto' => 'Olá, {{ SOLICITANTE }}! Sobre {{ ALUNO }} (protocolo {{ PROTOCOLO }}).']);
        $outroSetor = AtendimentoSetor::create(['nome' => 'Financeiro', 'ativo' => true, 'ordem' => 2]);
        AtendimentoRespostaPronta::create(['setor_id' => $outroSetor->id, 'titulo' => 'Só do financeiro', 'texto' => 'x']);
        AtendimentoRespostaPronta::create(['setor_id' => null, 'titulo' => 'Geral', 'texto' => 'y']);

        $this->assertSame("Olá, Carlos Responsável! Sobre Ana Lima (protocolo {$chamado->protocolo}).", $pronta->textoPara($chamado));
        $this->assertEqualsCanonicalizing(['Boleto', 'Geral'], AtendimentoRespostaPronta::query()->doSetor($this->setor->id)->pluck('titulo')->all());
    }

    // --- Requerimentos (Onda 80) --------------------------------------------------------------------------------------

    public function test_requerimento_valida_os_campos_do_tipo(): void
    {
        $this->expectException(ValidationException::class);

        $this->servico()->abrirRequerimento('atualizacao_cadastral', $this->responsavel->id, null, []);
    }

    public function test_requerimento_que_exige_aluno_recusa_sem_matricula(): void
    {
        try {
            $this->servico()->abrirRequerimento('autorizacao_retirada', $this->responsavel->id, null, ['nome_autorizado' => 'Tia Rita', 'documento' => '123', 'parentesco' => 'Tia']);
            $this->fail('Deveria exigir o aluno.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('matricula_id', $e->errors());
        }
    }

    public function test_requerimento_abre_com_prazo_do_tipo_e_cai_no_setor_de_requerimentos(): void
    {
        $outro = AtendimentoSetor::create(['nome' => 'Financeiro', 'ativo' => true, 'ordem' => 0]);

        $chamado = $this->servico()->abrirRequerimento('atualizacao_cadastral', $this->responsavel->id, null, ['telefone' => '11 98888-7777']);

        $this->assertSame($this->setor->id, $chamado->setor_id, 'O setor marcado para requerimentos recebe, mesmo que outro tenha ordem menor.');
        $this->assertNotSame($outro->id, $chamado->setor_id);
        $this->assertSame('atualizacao_cadastral', $chamado->tipo_requerimento);
        // 5 dias úteis a partir de quarta 21/10 = quarta 28/10.
        $this->assertSame('2026-10-28', $chamado->prazo_resolucao_em->format('Y-m-d'));
        $this->assertFalse($chamado->restrito);
    }

    public function test_atualizacao_cadastral_so_muda_o_cadastro_quando_a_secretaria_aplica(): void
    {
        $chamado = $this->servico()->abrirRequerimento('atualizacao_cadastral', $this->responsavel->id, null, ['telefone' => '11 98888-7777', 'email' => 'novo@email.test', 'endereco' => 'Rua Nova, 10']);

        $this->assertSame('antigo@email.test', $this->responsavel->refresh()->email, 'Abrir o pedido não muda o cadastro.');

        $diferencas = collect($chamado->tipo()->tratador()->diferencas($chamado))->keyBy('rotulo');
        $this->assertSame('antigo@email.test', $diferencas['E-mail']['antes']);
        $this->assertSame('novo@email.test', $diferencas['E-mail']['depois']);

        Notification::fake();
        $this->como($this->secretaria);
        $this->servico()->concluirRequerimento($chamado, $this->secretaria, ResultadoChamado::Atendido, null, true);

        $this->responsavel->refresh();
        $this->assertSame('novo@email.test', $this->responsavel->email);
        $this->assertSame('11 98888-7777', $this->responsavel->telefone);

        $chamado->refresh();
        $this->assertSame(ResultadoChamado::Atendido, $chamado->resultado);
        $this->assertSame(StatusChamado::Resolvido, $chamado->status);
        $this->assertSame($this->secretaria->id, $chamado->aplicado_por_user_id);
        $this->assertNotNull($chamado->aplicado_em);
        Notification::assertSentTo($this->familia, ChamadoRespondidoNotification::class);
    }

    public function test_aplicar_exige_a_permissao_propria(): void
    {
        $chamado = $this->servico()->abrirRequerimento('atualizacao_cadastral', $this->responsavel->id, null, ['telefone' => '11 98888-7777']);
        $semPermissao = $this->usuario('responsavel');
        $this->como($semPermissao);

        $this->expectException(AuthorizationException::class);

        $this->servico()->concluirRequerimento($chamado, $semPermissao, ResultadoChamado::Atendido, null, true);
    }

    public function test_indeferir_exige_justificativa_e_nao_aplica_nada(): void
    {
        $chamado = $this->servico()->abrirRequerimento('atualizacao_cadastral', $this->responsavel->id, null, ['email' => 'novo@email.test']);
        $this->como($this->secretaria);

        try {
            $this->servico()->concluirRequerimento($chamado, $this->secretaria, ResultadoChamado::Indeferido, '   ');
            $this->fail('Deveria exigir a justificativa.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('motivo', $e->getMessage());
        }

        $this->servico()->concluirRequerimento($chamado, $this->secretaria, ResultadoChamado::Indeferido, 'E-mail já cadastrado em outro responsável.');

        $this->assertSame('antigo@email.test', $this->responsavel->refresh()->email);
        $this->assertSame(ResultadoChamado::Indeferido, $chamado->refresh()->resultado);
        $this->assertNull($chamado->aplicado_em);
    }

    public function test_requerimento_nao_conclui_duas_vezes(): void
    {
        $chamado = $this->servico()->abrirRequerimento('troca_turma_turno', $this->responsavel->id, $this->matricula->id, ['tipo_troca' => 'turno', 'desejado' => 'Tarde', 'motivo' => 'Trabalho']);
        $this->como($this->secretaria);
        $this->servico()->concluirRequerimento($chamado, $this->secretaria, ResultadoChamado::Atendido, 'Há vaga no turno da tarde.');

        $this->expectException(\DomainException::class);

        $this->servico()->concluirRequerimento($chamado->refresh(), $this->secretaria, ResultadoChamado::Atendido, 'de novo');
    }

    public function test_atestado_enviado_pela_familia_vira_atestado_do_aluno_so_na_aplicacao(): void
    {
        $caminho = UploadedFile::fake()->createWithContent('atestado.pdf', "%PDF-1.4\n%%EOF")->store('atendimentos/requerimentos', 'local');

        $chamado = $this->servico()->abrirRequerimento('atestado_aluno', $this->responsavel->id, $this->matricula->id, [
            'inicio' => '2026-10-19', 'fim' => '2026-10-20', 'comprovante' => $caminho, 'observacao' => 'Gripe',
        ]);

        $this->assertSame(0, AtestadoAluno::count(), 'O pedido, sozinho, não registra o atestado.');

        $this->como($this->secretaria);
        $this->servico()->concluirRequerimento($chamado, $this->secretaria, ResultadoChamado::Atendido, null, true, ['abona_faltas' => true]);

        $atestado = AtestadoAluno::firstOrFail();
        $this->assertSame($this->matricula->id, $atestado->matricula_id);
        $this->assertTrue($atestado->abona_faltas);
        $this->assertSame('2026-10-19', $atestado->data_inicio->format('Y-m-d'));
    }

    public function test_atestado_com_periodo_invertido_e_recusado(): void
    {
        $this->expectException(ValidationException::class);

        $this->servico()->abrirRequerimento('atestado_aluno', $this->responsavel->id, $this->matricula->id, ['inicio' => '2026-10-20', 'fim' => '2026-10-19', 'comprovante' => 'x.pdf']);
    }

    // --- Pedido do titular (Onda 34) ----------------------------------------------------------------------------------

    public function test_pedido_do_titular_e_restrito_tem_prazo_de_15_dias_corridos_e_nao_vai_ao_email_do_setor(): void
    {
        Notification::fake();

        $chamado = $this->servico()->abrirRequerimento('pedido_titular', $this->responsavel->id, null, ['tipo_pedido' => 'acesso', 'descricao' => 'Quero saber quais dados a escola tem de mim.']);

        $this->assertTrue($chamado->restrito);
        $this->assertSame('2026-11-05', $chamado->prazo_resolucao_em->format('Y-m-d'));
        Notification::assertSentTo($this->encarregado, ChamadoParaEquipeNotification::class);
        Notification::assertNotSentTo($this->secretaria, ChamadoParaEquipeNotification::class);
        Notification::assertSentOnDemandTimes(ChamadoParaEquipeNotification::class, 0);
    }

    public function test_pedido_do_titular_so_aparece_para_quem_tem_a_permissao_de_restritos(): void
    {
        $restrito = $this->servico()->abrirRequerimento('pedido_titular', $this->responsavel->id, null, ['tipo_pedido' => 'eliminacao', 'descricao' => 'Quero apagar meus dados.']);
        $comum = $this->abrirChamado();

        $this->como($this->secretaria);
        $this->actingAs($this->secretaria);
        Livewire::test(ListAtendimentoChamados::class)
            ->assertCanSeeTableRecords([$comum])
            ->assertCanNotSeeTableRecords([$restrito]);
        $this->assertFalse($this->secretaria->can('view', $restrito));

        $this->como($this->encarregado);
        $this->actingAs($this->encarregado);
        Livewire::test(ListAtendimentoChamados::class)->assertCanSeeTableRecords([$comum, $restrito]);
        $this->assertTrue($this->encarregado->can('view', $restrito));
    }

    // --- Tela da secretaria e do Portal -------------------------------------------------------------------------------

    public function test_secretaria_conclui_o_requerimento_pela_tabela_depois_de_conferir(): void
    {
        $chamado = $this->servico()->abrirRequerimento('atualizacao_cadastral', $this->responsavel->id, null, ['telefone' => '11 98888-7777']);

        $this->como($this->secretaria);
        $this->actingAs($this->secretaria);

        Livewire::test(ListAtendimentoChamados::class)
            ->mountTableAction('concluir_requerimento', $chamado)
            ->fillForm(['resultado' => ResultadoChamado::Atendido->value, 'aplicar' => true])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame('11 98888-7777', $this->responsavel->refresh()->telefone);
        $this->assertSame(ResultadoChamado::Atendido, $chamado->refresh()->resultado);
    }

    public function test_responsavel_abre_requerimento_pelo_portal(): void
    {
        $this->como($this->familia);

        Livewire::actingAs($this->familia)
            ->test(Requerimentos::class)
            ->call('escolher', 'atualizacao_cadastral')
            ->set('campos.telefone', '11 97777-6666')
            ->call('enviar')
            ->assertHasNoErrors();

        $chamado = AtendimentoChamado::firstOrFail();
        $this->assertSame('atualizacao_cadastral', $chamado->tipo_requerimento);
        $this->assertSame($this->responsavel->id, $chamado->solicitante_id);
    }

    public function test_portal_nao_aceita_aluno_de_outra_familia(): void
    {
        $estranha = Matricula::factory()->create(['pessoa_id' => Pessoa::factory()->create()->id, 'turma_id' => Turma::factory()->create()->id]);
        $this->como($this->familia);

        Livewire::actingAs($this->familia)
            ->test(Requerimentos::class)
            ->call('escolher', 'autorizacao_retirada')
            ->set('matriculaId', $estranha->id)
            ->set('campos.nome_autorizado', 'Fulano')
            ->set('campos.documento', '123')
            ->set('campos.parentesco', 'Tio')
            ->call('enviar')
            ->assertHasErrors(['matriculaId']);

        $this->assertSame(0, AtendimentoChamado::count());
    }

    // --- Filas da Minha Mesa ------------------------------------------------------------------------------------------

    public function test_filas_da_mesa_contam_prazo_estourado_e_requerimentos_abertos(): void
    {
        $atrasado = $this->abrirChamado();
        $atrasado->forceFill(['prazo_primeira_resposta_em' => now()->subHour()])->save();
        $this->abrirChamado();
        $requerimento = $this->servico()->abrirRequerimento('troca_turma_turno', $this->responsavel->id, $this->matricula->id, ['tipo_troca' => 'turma', 'desejado' => '5º B', 'motivo' => 'Amigos']);
        $requerimento->forceFill(['prazo_resolucao_em' => now()->subDay()])->save();
        $restrito = $this->servico()->abrirRequerimento('pedido_titular', $this->responsavel->id, null, ['tipo_pedido' => 'acesso', 'descricao' => 'Quero meus dados.']);

        $prazos = new ChamadosComPrazoEstourado;
        $this->assertSame(2, $prazos->contar($this->encarregado), 'O chamado atrasado e o requerimento atrasado (a resolução também venceu).');
        $this->assertSame(2, $prazos->atrasados($this->encarregado));

        $requerimentos = new RequerimentosParaAtender;
        $this->assertSame(2, $requerimentos->contar($this->encarregado), 'Troca de turma e pedido restrito.');
        $this->assertSame(1, $requerimentos->contar($this->secretaria), 'A secretaria não conta o pedido restrito do titular.');
        $this->assertSame(1, $requerimentos->atrasados($this->secretaria));
        $this->assertCount(1, $requerimentos->itens($this->secretaria, 5));
        $this->assertStringNotContainsString('Quero meus dados', json_encode($requerimentos->itens($this->encarregado, 5)));
    }
}
