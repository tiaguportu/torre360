<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoAlunoCenso;
use App\Filament\Pages\CensoEscolar;
use App\Models\CensoSituacaoAluno;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\MontaEscolaDoCenso;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Tela do Censo Escolar (Onda 58): acesso, lista com pendências, ajuste de situação, códigos do Inep, importação do
 * retorno e download dos arquivos.
 */
class CensoEscolarTelaTest extends TestCase
{
    use MontaEscolaDoCenso;
    use ProibeLazyLoading;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00:00'));
        $this->montarEscolaDoCenso();
    }

    private function secretaria(array $permissoes = ['GerenciarCensoEscolar:Matricula'], string $papel = 'secretaria'): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $role = Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        foreach ($permissoes as $permissao) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole($papel);
        session(['active_role' => $papel]);

        return $user;
    }

    private function tela(?User $user = null)
    {
        return Livewire::actingAs($user ?? $this->secretaria())->test(CensoEscolar::class);
    }

    public function test_quem_nao_tem_a_permissao_nao_abre_a_tela(): void
    {
        $this->tela($this->secretaria([], 'professor'))->assertForbidden();
    }

    public function test_abre_com_a_escola_e_o_periodo_do_ano_e_lista_os_alunos(): void
    {
        $turma = $this->turma();
        $ana = $this->aluno($turma, 'Ana Lima');
        $this->resultado($ana, 'aprovado');
        $this->aluno($turma, 'Bia Souza');

        $tela = $this->tela()
            ->assertSet('unidadeId', $this->unidade->id)
            ->assertSet('periodoId', $this->periodo->id)
            ->assertActionExists('ajuda')
            ->assertSee('Ana Lima')
            ->assertSee('Bia Souza')
            ->assertSee('12345678')
            ->assertSee('MARIA JOSE GESTORA')
            ->assertSee('27/05/2026')
            ->assertSee('Aprovado')
            ->assertSee('Resultado final não calculado');

        $this->assertSame(2, $tela->instance()->getResumo()['total']);
        $this->assertSame(1, $tela->instance()->getResumo()['prontas']);
    }

    public function test_filtros_mostram_so_as_prontas_ou_so_as_com_pendencia(): void
    {
        $turma = $this->turma();
        $this->resultado($this->aluno($turma, 'Ana Lima'), 'aprovado');
        $this->aluno($turma, 'Bia Souza');

        $this->tela()->set('filtro', 'prontas')->assertSee('Ana Lima')->assertDontSee('Bia Souza');
        $this->tela()->set('filtro', 'pendencias')->assertSee('Bia Souza')->assertDontSee('Ana Lima');
    }

    public function test_avisa_quando_a_escola_nao_tem_codigo_inep_nem_gestor(): void
    {
        $this->unidade->update(['codigo_inep' => null]);
        $this->unidade->representantesLegais()->detach();

        $this->tela()->assertSee('Antes de gerar o arquivo')->assertSee('código Inep da escola')->assertSee('gestor');
    }

    public function test_nao_mostra_matricula_de_outra_unidade(): void
    {
        $outra = Unidade::create(['nome' => 'Filial', 'codigo_inep' => '87654321']);
        $curso = Curso::create(['unidade_id' => $outra->id, 'nome_externo' => 'Médio', 'nome_interno' => 'EM']);
        $serie = Serie::create(['nome' => '1ª Série', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
        $turmaOutra = Turma::create(['nome' => 'Turma Filial', 'serie_id' => $serie->id, 'periodo_letivo_id' => $this->periodo->id]);
        $this->aluno($turmaOutra, 'Aluno da Filial');
        $this->aluno($this->turma(), 'Aluno da Sede');

        $this->tela()->set('unidadeId', $this->unidade->id)->assertSee('Aluno da Sede')->assertDontSee('Aluno da Filial');
        $this->tela()->set('unidadeId', $outra->id)->assertSee('Aluno da Filial')->assertDontSee('Aluno da Sede');
    }

    public function test_ajusta_a_situacao_pela_tela_e_depois_volta_ao_calculo(): void
    {
        $matricula = $this->aluno($this->turma());
        $this->resultado($matricula, 'aprovado');

        $this->tela()
            ->callAction(TestAction::make('ajustar')->arguments(['matricula' => $matricula->id]), ['situacao' => SituacaoAlunoCenso::Falecido->value, 'justificativa' => 'Comunicado pela família em 12/08.'])
            ->assertHasNoActionErrors()
            ->assertNotified('Situação ajustada')
            ->assertSee('Falecido')
            ->assertSee('Ajuste manual');

        $this->assertSame(SituacaoAlunoCenso::Falecido, $matricula->fresh()->censoAjuste->situacao);

        $this->tela()
            ->callAction(TestAction::make('removerAjuste')->arguments(['matricula' => $matricula->id]))
            ->assertNotified('Ajuste removido');

        $this->assertSame(0, CensoSituacaoAluno::count());
    }

    public function test_ajuste_fora_das_regras_de_etapa_e_recusado_com_o_motivo(): void
    {
        $matricula = $this->aluno($this->turma('Maternal', '2'));

        $this->tela()
            ->callAction(TestAction::make('ajustar')->arguments(['matricula' => $matricula->id]), ['situacao' => SituacaoAlunoCenso::Aprovado->value, 'justificativa' => 'Tentativa de aprovar criança.'])
            ->assertNotified('Ajuste não aceito');

        $this->assertSame(0, CensoSituacaoAluno::count());
    }

    public function test_ajuste_nao_alcanca_matricula_de_outra_escola(): void
    {
        $outra = Unidade::create(['nome' => 'Filial', 'codigo_inep' => '87654321']);
        $curso = Curso::create(['unidade_id' => $outra->id, 'nome_externo' => 'Médio', 'nome_interno' => 'EM']);
        $serie = Serie::create(['nome' => '1ª Série', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
        $turmaOutra = Turma::create(['nome' => 'Turma Filial', 'serie_id' => $serie->id, 'periodo_letivo_id' => $this->periodo->id]);
        $estranha = $this->aluno($turmaOutra, 'Aluno da Filial');
        $this->aluno($this->turma(), 'Aluno da Sede');

        $this->tela()
            ->set('unidadeId', $this->unidade->id)
            ->callAction(TestAction::make('ajustar')->arguments(['matricula' => $estranha->id]), ['situacao' => SituacaoAlunoCenso::Falecido->value, 'justificativa' => 'Tentativa por id de outra escola.']);

        $this->tela()
            ->set('unidadeId', $this->unidade->id)
            ->callAction(TestAction::make('codigos')->arguments(['matricula' => $estranha->id]), ['id_inep' => '999999999999', 'codigo_turma' => '1', 'codigo_matricula' => '1']);

        $this->assertSame(0, CensoSituacaoAluno::count());
        $this->assertNotSame('999999999999', $estranha->pessoa->fresh()->codigo_inep);
    }

    public function test_informa_os_codigos_do_inep_pela_tela(): void
    {
        $turma = $this->turma(codigoInep: null);
        $matricula = $this->aluno($turma, 'Ana Lima', pessoa: ['codigo_inep' => null], matricula: ['codigo_matricula_inep' => null]);

        $this->tela()
            ->callAction(TestAction::make('codigos')->arguments(['matricula' => $matricula->id]), ['id_inep' => '123456789012', 'codigo_turma' => '4455', 'codigo_matricula' => '987654321098'])
            ->assertHasNoActionErrors()
            ->assertNotified('Códigos gravados');

        $this->assertSame('123456789012', $matricula->pessoa->fresh()->codigo_inep);
        $this->assertSame('4455', $turma->fresh()->codigo_inep);
        $this->assertSame('987654321098', $matricula->fresh()->codigo_matricula_inep);
    }

    public function test_codigos_com_formato_errado_sao_recusados_no_formulario(): void
    {
        $matricula = $this->aluno($this->turma(), pessoa: ['codigo_inep' => null]);

        $this->tela()
            ->callAction(TestAction::make('codigos')->arguments(['matricula' => $matricula->id]), ['id_inep' => '123', 'codigo_turma' => 'abc', 'codigo_matricula' => '12'])
            ->assertHasActionErrors(['id_inep', 'codigo_turma']);

        $this->assertNull($matricula->pessoa->fresh()->codigo_inep);
    }

    public function test_importa_o_retorno_da_matricula_inicial_pela_tela(): void
    {
        $turma = $this->turma(codigoInep: null);
        $matricula = $this->aluno($turma, 'Ana Lima', pessoa: ['codigo_inep' => null], matricula: ['codigo_matricula_inep' => null]);
        $conteudo = implode("\r\n", [
            "20|12345678|T101|4455|TURMA 101|1|",
            "30|12345678|{$matricula->pessoa_id}|123456789012|11144477735|ANA LIMA|15/03/2014|",
            "60|12345678|{$matricula->pessoa_id}|123456789012|T101|4455|987654321098|0|",
        ]);

        $this->tela()
            ->callAction('importarRetorno', ['arquivo' => UploadedFile::fake()->createWithContent('retorno.txt', $conteudo)])
            ->assertHasNoActionErrors()
            ->assertNotified('Importação concluída');

        $this->assertSame('123456789012', $matricula->pessoa->fresh()->codigo_inep);
        $this->assertSame('4455', $turma->fresh()->codigo_inep);
        $this->assertSame('987654321098', $matricula->fresh()->codigo_matricula_inep);
    }

    public function test_arquivo_que_nao_e_do_educacenso_mostra_o_erro_sem_gravar(): void
    {
        $matricula = $this->aluno($this->turma(), pessoa: ['codigo_inep' => null]);

        $this->tela()
            ->callAction('importarRetorno', ['arquivo' => UploadedFile::fake()->createWithContent('lixo.txt', "isto não é\nde jeito nenhum um retorno")])
            ->assertNotified('Arquivo não reconhecido');

        $this->assertNull($matricula->pessoa->fresh()->codigo_inep);
    }

    public function test_importa_a_identificacao_pela_tela(): void
    {
        $matricula = $this->aluno($this->turma(), 'Ana Lima', pessoa: ['codigo_inep' => null]);
        $conteudo = "{$matricula->pessoa_id}|11144477735||ANA LIMA|15/03/2014|JOANA LIMA|PEDRO LIMA|3304557|424242424242";

        $this->tela()
            ->callAction('importarIdentificacao', ['arquivo' => UploadedFile::fake()->createWithContent('RESULTADO_CERTO.txt', $conteudo)])
            ->assertNotified('Importação concluída');

        $this->assertSame('424242424242', $matricula->pessoa->fresh()->codigo_inep);
    }

    public function test_baixa_o_arquivo_da_situacao_do_aluno(): void
    {
        $this->resultado($this->aluno($this->turma(), 'Ana Lima'), 'aprovado');

        $this->tela()
            ->callAction('baixarSituacao')
            ->assertFileDownloaded('SA_12345678_2026.txt');
    }

    public function test_download_da_situacao_sem_matricula_pronta_avisa_o_motivo(): void
    {
        $this->aluno($this->turma(), 'Ana Lima'); // sem resultado final

        $this->tela()
            ->callAction('baixarSituacao')
            ->assertNotified('Não foi possível gerar o arquivo');
    }

    public function test_avisa_quem_ficou_de_fora_do_arquivo(): void
    {
        $turma = $this->turma();
        $this->resultado($this->aluno($turma, 'Ana Lima'), 'aprovado');
        $this->aluno($turma, 'Bia Souza');

        $this->tela()
            ->callAction('baixarSituacao')
            ->assertNotified('1 aluno(s) ficaram de fora do arquivo');
    }

    public function test_download_da_identificacao_so_para_quem_tem_dados(): void
    {
        $this->aluno($this->turma(), 'Ana Lima', pessoa: ['codigo_inep' => null]); // sem nascimento nem município

        $this->tela()
            ->callAction('baixarIdentificacao')
            ->assertNotified('Não foi possível gerar o arquivo');
    }

    public function test_a_tela_so_funciona_para_quem_tem_a_permissao_nas_acoes(): void
    {
        $matricula = $this->aluno($this->turma());
        $this->resultado($matricula, 'aprovado');

        $this->tela($this->secretaria([], 'professor'))->assertForbidden();

        $this->assertSame(0, Matricula::query()->whereNotNull('codigo_matricula_inep')->where('id', 0)->count());
        $this->assertSame(0, CensoSituacaoAluno::count());
    }

    public function test_periodo_sem_alunos_mostra_a_lista_vazia(): void
    {
        $vazio = PeriodoLetivo::create(['nome' => '2025', 'data_inicio' => '2025-02-01', 'data_fim' => '2025-12-15']);

        $this->tela()->set('periodoId', $vazio->id)->assertSee('Nenhuma matrícula neste filtro');
    }
}
