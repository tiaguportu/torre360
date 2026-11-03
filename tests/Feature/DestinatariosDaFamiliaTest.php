<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AtendimentoChamado;
use App\Models\AtendimentoSetor;
use App\Models\Contrato;
use App\Models\ListaEsperaMatricula;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use App\Models\ResponsavelFinanceiro;
use App\Models\TipoVinculo;
use App\Models\Turma;
use App\Models\User;
use App\Services\DestinatariosDaFamilia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quem, da família, recebe um aviso: as cinco regras que viviam espalhadas (matrícula, lista de espera, preceptoria, faltas e chamado) agora
 * moram em `DestinatariosDaFamilia`. Cada teste trava uma regra que o sistema já tinha, para a unificação não mudar quem recebe.
 */
class DestinatariosDaFamiliaTest extends TestCase
{
    use RefreshDatabase;

    private Pessoa $aluno;

    private Pessoa $mae;

    private Pessoa $pai;

    private User $contaDoAluno;

    private User $contaDaMae;

    private User $contaDoPai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aluno = Pessoa::factory()->create(['nome' => 'Ana', 'email' => 'ana@aluno.test']);
        $this->mae = Pessoa::factory()->create(['nome' => 'Mãe', 'email' => 'mae@familia.test']);
        $this->pai = Pessoa::factory()->create(['nome' => 'Pai', 'email' => 'pai@familia.test']);

        $this->contaDoAluno = $this->conta('ana@aluno.test', $this->aluno);
        $this->contaDaMae = $this->conta('mae@familia.test', $this->mae);
        $this->contaDoPai = $this->conta('pai@familia.test', $this->pai);

        $vinculo = TipoVinculo::create(['nome' => 'Responsável']);
        $this->aluno->responsaveis()->attach([$this->mae->id => ['tipo_vinculo_id' => $vinculo->id], $this->pai->id => ['tipo_vinculo_id' => $vinculo->id]]);
    }

    private function conta(?string $email, Pessoa $pessoa, array $extra = []): User
    {
        $usuario = User::factory()->create($extra + ['email' => $email, 'activated_at' => now()->subDay()]);
        $pessoa->users()->attach($usuario);

        return $usuario;
    }

    private function resolvedor(): DestinatariosDaFamilia
    {
        return app(DestinatariosDaFamilia::class);
    }

    private function ids($colecao): array
    {
        return collect($colecao)->pluck('id')->sort()->values()->all();
    }

    // --- Responsáveis do aluno (faltas, ocorrências) ------------------------------------------------------------------

    public function test_responsaveis_do_aluno_sao_as_contas_dos_responsaveis_sem_a_do_proprio_aluno(): void
    {
        $contas = $this->resolvedor()->responsaveisDoAluno($this->aluno);

        $this->assertSame($this->ids([$this->contaDaMae, $this->contaDoPai]), $this->ids($contas));
        $this->assertNotContains($this->contaDoAluno->id, $this->ids($contas));
    }

    public function test_responsavel_sem_conta_nao_entra_e_aluno_sem_responsavel_devolve_vazio(): void
    {
        $semConta = Pessoa::factory()->create();
        $semResponsavel = Pessoa::factory()->create();
        $this->aluno->responsaveis()->attach($semConta->id, ['tipo_vinculo_id' => TipoVinculo::first()->id]);

        $this->assertCount(2, $this->resolvedor()->responsaveisDoAluno($this->aluno->refresh()));
        $this->assertCount(0, $this->resolvedor()->responsaveisDoAluno($semResponsavel));
        $this->assertCount(0, $this->resolvedor()->responsaveisDoAluno(null));
    }

    public function test_conta_com_dois_responsaveis_aparece_uma_vez(): void
    {
        $this->pai->users()->syncWithoutDetaching([$this->contaDaMae->id]);

        $this->assertSame(1, $this->resolvedor()->responsaveisDoAluno($this->aluno->refresh())->where('id', $this->contaDaMae->id)->count());
    }

    public function test_apenas_ativos_tira_a_conta_desativada_e_a_ainda_nao_ativada(): void
    {
        $this->contaDaMae->forceFill(['deactivated_at' => now()->subHour()])->save();
        $this->contaDoPai->forceFill(['activated_at' => null])->save();

        $this->assertCount(2, $this->resolvedor()->responsaveisDoAluno($this->aluno), 'Por padrão não filtra: o resumo de faltas sempre avisou todas as contas.');
        $this->assertCount(0, $this->resolvedor()->responsaveisDoAluno($this->aluno->refresh(), apenasAtivos: true));
    }

    // --- Matrícula ----------------------------------------------------------------------------------------------------

    private function matriculaComContrato(): Matricula
    {
        $matricula = Matricula::factory()->create(['pessoa_id' => $this->aluno->id, 'turma_id' => Turma::factory()->create()->id]);
        $contrato = Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => 0, 'data_aceite' => now()]);
        $financeiro = Pessoa::factory()->create(['email' => 'financeiro@familia.test']);
        $contaFinanceira = $this->conta('financeiro@familia.test', $financeiro);
        ResponsavelFinanceiro::create(['contrato_id' => $contrato->id, 'pessoa_id' => $financeiro->id, 'percentual' => 100]);

        $this->financeira = $contaFinanceira;

        return $matricula->refresh();
    }

    private User $financeira;

    public function test_matricula_avisa_o_aluno_os_responsaveis_e_o_responsavel_financeiro(): void
    {
        $matricula = $this->matriculaComContrato();

        $this->assertSame(
            $this->ids([$this->contaDoAluno, $this->contaDaMae, $this->contaDoPai, $this->financeira]),
            $this->ids($this->resolvedor()->daMatricula($matricula)),
        );
        $this->assertSame($this->ids($matricula->getNotificationRecipients()), $this->ids($this->resolvedor()->daMatricula($matricula)), 'O método do model delega ao resolvedor.');
    }

    public function test_matricula_sem_contrato_avisa_o_aluno_e_os_responsaveis(): void
    {
        $matricula = Matricula::factory()->create(['pessoa_id' => $this->aluno->id, 'turma_id' => Turma::factory()->create()->id]);

        $this->assertSame(
            $this->ids([$this->contaDoAluno, $this->contaDaMae, $this->contaDoPai]),
            $this->ids($this->resolvedor()->daMatricula($matricula)),
        );
    }

    // --- Lista de espera ----------------------------------------------------------------------------------------------

    public function test_lista_de_espera_avisa_aluno_responsaveis_e_quem_registrou_a_entrada(): void
    {
        $secretaria = User::factory()->create(['activated_at' => now()->subDay()]);
        $entrada = ListaEsperaMatricula::factory()->create(['pessoa_id' => $this->aluno->id, 'criado_por_user_id' => $secretaria->id]);

        $this->assertSame(
            $this->ids([$this->contaDoAluno, $this->contaDaMae, $this->contaDoPai, $secretaria]),
            $this->ids($entrada->getNotificationRecipients()),
        );
    }

    // --- Preceptoria: pessoa com e-mail e conta, uma vez por e-mail -----------------------------------------------------

    public function test_notificaveis_trazem_a_pessoa_sem_conta_e_preferem_a_conta_quando_o_email_repete(): void
    {
        $semConta = Pessoa::factory()->create(['email' => 'tio@familia.test']);
        $semEmail = Pessoa::factory()->create(['email' => null]);

        $notificaveis = $this->resolvedor()->notificaveisDasPessoas(collect([$this->mae, $semConta, $semEmail]));

        $this->assertCount(2, $notificaveis, 'A mãe (uma vez só) e o tio sem conta; quem não tem e-mail nem conta fica de fora.');
        $this->assertInstanceOf(User::class, $notificaveis->firstWhere('email', 'mae@familia.test'), 'Pessoa e conta com o mesmo e-mail: fica a conta, que recebe push e sino.');
        $this->assertInstanceOf(Pessoa::class, $notificaveis->firstWhere('email', 'tio@familia.test'));
    }

    public function test_o_helper_estatico_da_preceptoria_delega_ao_resolvedor(): void
    {
        $pessoas = collect([$this->aluno, $this->mae]);

        $this->assertEquals(
            $this->resolvedor()->notificaveisDasPessoas($pessoas)->pluck('email')->sort()->values()->all(),
            Preceptoria::destinatariosDasPessoas($pessoas)->pluck('email')->sort()->values()->all(),
        );
    }

    // --- Chamado ------------------------------------------------------------------------------------------------------

    private function chamado(?Pessoa $solicitante, ?Matricula $matricula = null): AtendimentoChamado
    {
        $setor = AtendimentoSetor::create(['nome' => 'Secretaria', 'ativo' => true, 'ordem' => 1]);

        return AtendimentoChamado::create([
            'setor_id' => $setor->id,
            'solicitante_id' => $solicitante?->id,
            'matricula_id' => $matricula?->id,
            'assunto' => 'Teste',
            'status' => 'aberto',
        ]);
    }

    public function test_chamado_avisa_a_conta_de_quem_abriu_e_so_as_ativas(): void
    {
        $this->contaDaMae->forceFill(['deactivated_at' => now()->subHour()])->save();
        $contaNova = $this->conta('mae2@familia.test', $this->mae);

        $this->assertSame([$contaNova->id], $this->ids($this->resolvedor()->doChamado($this->chamado($this->mae->refresh()))));
    }

    public function test_chamado_de_quem_nao_tem_conta_cai_nos_responsaveis_do_aluno(): void
    {
        $semConta = Pessoa::factory()->create();
        $matricula = Matricula::factory()->create(['pessoa_id' => $this->aluno->id, 'turma_id' => Turma::factory()->create()->id]);

        $this->assertSame(
            $this->ids([$this->contaDaMae, $this->contaDoPai]),
            $this->ids($this->resolvedor()->doChamado($this->chamado($semConta, $matricula))),
        );
    }
}
