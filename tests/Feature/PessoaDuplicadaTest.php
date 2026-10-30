<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\PessoasDuplicadas;
use App\Models\Pessoa;
use App\Models\PessoaNaoDuplicada;
use App\Models\User;
use App\Services\PessoaDuplicadaDetectorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Cadastros duplicados de pessoa (Onda 81): o que o detector sugere, o que ele NUNCA sugere (falso positivo é o que estraga a fila de
 * conferência) e a tela que lista os pares e marca "não é duplicata".
 */
class PessoaDuplicadaTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private PessoaDuplicadaDetectorService $detector;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->detector = app(PessoaDuplicadaDetectorService::class);
    }

    /**
     * Pessoa isolada: sem CPF, e-mail, telefone nem nascimento, a menos que o teste peça. A factory sorteia esses dados e um acaso
     * poderia formar um par que o teste não espera.
     *
     * @param  array<string, mixed>  $atributos
     */
    private function pessoa(string $nome, array $atributos = []): Pessoa
    {
        return Pessoa::factory()->create(array_merge([
            'nome' => $nome,
            'cpf' => null,
            'email' => null,
            'telefone' => null,
            'data_nascimento' => null,
        ], $atributos));
    }

    /** Grava o CPF como veio de importações antigas, com máscara (o `Pessoa` só guarda dígitos quando passa pelo model). */
    private function cpfLegado(Pessoa $pessoa, string $cpf): void
    {
        DB::table('pessoa')->where('id', $pessoa->id)->update(['cpf' => $cpf]);
    }

    /**
     * As chaves "menor-maior" dos pares sugeridos, recalculadas (sem o cache).
     *
     * @return list<string>
     */
    private function chaves(): array
    {
        $this->detector->esquecerCache();

        return $this->detector->pares()->pluck('chave')->all();
    }

    private function chave(Pessoa $a, Pessoa $b): string
    {
        return min($a->id, $b->id).'-'.max($a->id, $b->id);
    }

    // ─── O que é sugerido ────────────────────────────────────────────

    public function test_cpf_igual_com_e_sem_mascara_forma_par(): void
    {
        $a = $this->pessoa('Carlos Alberto Souza');
        $b = $this->pessoa('Carlos A. de Souza');
        $this->cpfLegado($a, '529.982.247-25');
        $this->cpfLegado($b, '52998224725');

        $this->detector->esquecerCache();
        $pares = $this->detector->pares();

        $this->assertCount(1, $pares);
        $this->assertSame($this->chave($a, $b), $pares->first()['chave']);
        $this->assertSame([PessoaDuplicadaDetectorService::MOTIVO_CPF], $pares->first()['motivos']);
    }

    public function test_mesmo_nome_e_nascimento_forma_par_sem_ligar_para_acento_caixa_e_pontuacao(): void
    {
        $a = $this->pessoa('José da Silva', ['data_nascimento' => '2015-03-12']);
        $b = $this->pessoa('  JOSE  DA  SILVA. ', ['data_nascimento' => '2015-03-12']);

        $this->assertSame([$this->chave($a, $b)], $this->chaves());
        $this->assertSame([PessoaDuplicadaDetectorService::MOTIVO_NOME_NASCIMENTO], $this->detector->pares()->first()['motivos']);
    }

    public function test_mesmo_nome_e_nascimento_forma_par_mesmo_com_cpf_diferente_por_erro_de_digitacao(): void
    {
        $a = $this->pessoa('Ana Beatriz Lima', ['data_nascimento' => '2014-07-01', 'cpf' => '52998224725']);
        $b = $this->pessoa('Ana Beatriz Lima', ['data_nascimento' => '2014-07-01', 'cpf' => '52998224726']);

        $this->assertSame([$this->chave($a, $b)], $this->chaves());
    }

    public function test_mesmo_nome_e_mesmo_email_forma_par_quando_nada_se_contradiz(): void
    {
        $a = $this->pessoa('Marta Oliveira Costa', ['email' => 'Marta@Exemplo.com ']);
        $b = $this->pessoa('Marta Oliveira Costa', ['email' => 'marta@exemplo.com']);

        $this->assertSame([$this->chave($a, $b)], $this->chaves());
        $this->assertSame([PessoaDuplicadaDetectorService::MOTIVO_NOME_CONTATO], $this->detector->pares()->first()['motivos']);
    }

    public function test_telefone_com_mascara_e_codigo_do_pais_e_o_mesmo_telefone(): void
    {
        $a = $this->pessoa('Paulo Ricardo Nunes', ['telefone' => '(11) 99876-5432']);
        $b = $this->pessoa('Paulo Ricardo Nunes', ['telefone' => '+55 11 998765432']);

        $this->assertSame([$this->chave($a, $b)], $this->chaves());
    }

    public function test_cpf_aparece_primeiro_e_acumula_os_motivos_do_par(): void
    {
        $fraco = [$this->pessoa('Rita Maria Alves', ['email' => 'rita@exemplo.com']), $this->pessoa('Rita Maria Alves', ['email' => 'rita@exemplo.com'])];
        $forteA = $this->pessoa('Sérgio Luiz Prado', ['data_nascimento' => '2010-01-05']);
        $forteB = $this->pessoa('Sergio Luiz Prado', ['data_nascimento' => '2010-01-05']);
        $this->cpfLegado($forteA, '529.982.247-25');
        $this->cpfLegado($forteB, '52998224725');

        $this->detector->esquecerCache();
        $pares = $this->detector->pares();

        $this->assertSame([$this->chave($forteA, $forteB), $this->chave($fraco[0], $fraco[1])], $pares->pluck('chave')->all());
        $this->assertSame(
            [PessoaDuplicadaDetectorService::MOTIVO_CPF, PessoaDuplicadaDetectorService::MOTIVO_NOME_NASCIMENTO],
            $pares->first()['motivos'],
        );
    }

    // ─── O que NUNCA é sugerido (falso positivo) ─────────────────────

    public function test_gemeos_com_o_mesmo_nascimento_e_o_mesmo_contato_nao_formam_par(): void
    {
        $this->pessoa('Lucas Pereira Matos', ['data_nascimento' => '2016-05-20', 'email' => 'pais@exemplo.com', 'telefone' => '11998765432']);
        $this->pessoa('Mateus Pereira Matos', ['data_nascimento' => '2016-05-20', 'email' => 'pais@exemplo.com', 'telefone' => '11998765432']);

        $this->assertSame([], $this->chaves());
    }

    public function test_so_o_contato_igual_nao_forma_par_porque_a_familia_compartilha(): void
    {
        $this->pessoa('Helena Duarte Rocha', ['email' => 'familia@exemplo.com', 'telefone' => '1133334444']);
        $this->pessoa('Roberto Duarte Rocha', ['email' => 'familia@exemplo.com', 'telefone' => '1133334444']);

        $this->assertSame([], $this->chaves());
    }

    public function test_pai_e_filho_com_o_mesmo_nome_e_nascimentos_diferentes_nao_formam_par(): void
    {
        $this->pessoa('Antonio Carlos Ferreira', ['data_nascimento' => '1980-02-10', 'telefone' => '11998765432']);
        $this->pessoa('Antonio Carlos Ferreira', ['data_nascimento' => '2012-09-30', 'telefone' => '11998765432']);

        $this->assertSame([], $this->chaves());
    }

    public function test_mesmo_nome_e_contato_com_cpfs_diferentes_nao_forma_par(): void
    {
        $this->pessoa('Juliana Mendes Prado', ['cpf' => '52998224725', 'email' => 'ju@exemplo.com']);
        $this->pessoa('Juliana Mendes Prado', ['cpf' => '11144477735', 'email' => 'ju@exemplo.com']);

        $this->assertSame([], $this->chaves());
    }

    public function test_nome_de_uma_palavra_so_nao_basta_nem_com_o_mesmo_nascimento(): void
    {
        $this->pessoa('Lucas', ['data_nascimento' => '2016-05-20']);
        $this->pessoa('Lucas', ['data_nascimento' => '2016-05-20']);

        $this->assertSame([], $this->chaves());
    }

    public function test_cpf_de_marcador_de_lugar_nao_identifica_ninguem(): void
    {
        $a = $this->pessoa('Fulano de Tal Um');
        $b = $this->pessoa('Beltrano de Tal Dois');
        $this->cpfLegado($a, '000.000.000-00');
        $this->cpfLegado($b, '00000000000');

        $this->assertSame([], $this->chaves());
    }

    public function test_telefone_curto_demais_nao_conta_como_contato(): void
    {
        $this->pessoa('Camila Torres Reis', ['telefone' => '99876-5432']);
        $this->pessoa('Camila Torres Reis', ['telefone' => '99876-5432']);

        $this->assertSame([], $this->chaves());
    }

    public function test_responsavel_e_aluno_ligados_diretamente_nao_formam_par(): void
    {
        $responsavel = $this->pessoa('Ricardo Nogueira Lopes', ['data_nascimento' => '1985-04-02']);
        $aluno = $this->pessoa('Ricardo Nogueira Lopes', ['data_nascimento' => '1985-04-02']);
        $responsavel->alunos()->attach($aluno->id);

        $this->assertSame([], $this->chaves());
    }

    // ─── Não é duplicata ─────────────────────────────────────────────

    public function test_par_marcado_como_diferente_nao_volta_em_qualquer_ordem(): void
    {
        $a = $this->pessoa('Gabriel Santos Lima', ['data_nascimento' => '2016-05-20']);
        $b = $this->pessoa('Gabriel Santos Lima', ['data_nascimento' => '2016-05-20']);
        $this->assertSame([$this->chave($a, $b)], $this->chaves());

        $this->detector->marcarComoDiferentes($b, $a);

        $this->assertSame([], $this->chaves());
        $this->assertDatabaseHas('pessoa_nao_duplicada', ['pessoa_menor_id' => min($a->id, $b->id), 'pessoa_maior_id' => max($a->id, $b->id)]);
    }

    public function test_marcar_duas_vezes_nao_duplica_o_registro_e_o_mesmo_cadastro_nao_se_compara_com_ele_mesmo(): void
    {
        $a = $this->pessoa('Beatriz Gomes Lima');
        $b = $this->pessoa('Beatriz Gomes Lima');

        $this->detector->marcarComoDiferentes($a, $b);
        $this->detector->marcarComoDiferentes($b->id, $a->id);

        $this->assertSame(1, PessoaNaoDuplicada::count());

        $this->expectException(InvalidArgumentException::class);
        $this->detector->marcarComoDiferentes($a, $a);
    }

    // ─── A tela ──────────────────────────────────────────────────────

    /**
     * @param  list<string>  $acoes
     */
    private function usuarioComPapel(string $papel, array $acoes): User
    {
        $role = Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);

        foreach ($acoes as $acao) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => "{$acao}:Pessoa", 'guard_name' => 'web']));
        }

        $usuario = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $usuario->assignRole($papel);
        session(['active_role' => $papel]);

        return $usuario;
    }

    public function test_a_tela_so_abre_para_quem_pode_ver_pessoas(): void
    {
        $semPermissao = $this->usuarioComPapel('sem_pessoa', []);

        Livewire::actingAs($semPermissao)->test(PessoasDuplicadas::class)->assertForbidden();
    }

    public function test_a_tela_lista_o_par_e_quem_so_consulta_nao_ve_o_botao_de_marcar(): void
    {
        $a = $this->pessoa('Fernanda Cardoso Melo', ['data_nascimento' => '2013-08-09', 'cpf' => '52998224725']);
        $b = $this->pessoa('Fernanda Cardoso Melo', ['data_nascimento' => '2013-08-09']);
        $consulta = $this->usuarioComPapel('consulta_pessoa', ['ViewAny']);

        Livewire::actingAs($consulta)->test(PessoasDuplicadas::class)
            ->assertOk()
            ->assertSee('Fernanda Cardoso Melo')
            ->assertSee('Mesmo nome e nascimento')
            ->assertSee('***.982.247-**')
            ->assertDontSee('52998224725')
            ->assertTableActionHidden('nao_e_duplicata', $this->chave($a, $b));
    }

    public function test_quem_edita_pessoas_marca_o_par_como_diferente_e_ele_sai_da_lista(): void
    {
        $a = $this->pessoa('Igor Menezes Barros', ['data_nascimento' => '2011-11-11']);
        $b = $this->pessoa('Igor Menezes Barros', ['data_nascimento' => '2011-11-11']);
        $secretaria = $this->usuarioComPapel('secretaria_pessoa', ['ViewAny', 'Update']);

        Livewire::actingAs($secretaria)->test(PessoasDuplicadas::class)
            ->assertSee('Igor Menezes Barros')
            ->callTableAction('nao_e_duplicata', $this->chave($a, $b))
            ->assertNotified('Registrado: são pessoas diferentes.')
            ->assertDontSee('Igor Menezes Barros');

        $this->assertDatabaseHas('pessoa_nao_duplicada', [
            'pessoa_menor_id' => min($a->id, $b->id),
            'pessoa_maior_id' => max($a->id, $b->id),
            'user_id' => $secretaria->id,
        ]);
    }

    public function test_recalcular_traz_o_par_cadastrado_depois_de_a_lista_ser_guardada(): void
    {
        $secretaria = $this->usuarioComPapel('secretaria_pessoa', ['ViewAny', 'Update']);

        $tela = Livewire::actingAs($secretaria)->test(PessoasDuplicadas::class)
            ->assertSee('Nenhum cadastro duplicado à vista');

        $this->pessoa('Viviane Prado Castro', ['data_nascimento' => '2012-02-02']);
        $this->pessoa('Viviane Prado Castro', ['data_nascimento' => '2012-02-02']);

        $tela->callAction('recalcular')->assertSee('Viviane Prado Castro');
    }

    public function test_sem_nenhum_par_a_tela_mostra_o_estado_vazio(): void
    {
        $this->pessoa('Pessoa Unica Um', ['data_nascimento' => '2010-01-01']);
        $secretaria = $this->usuarioComPapel('secretaria_pessoa', ['ViewAny', 'Update']);

        Livewire::actingAs($secretaria)->test(PessoasDuplicadas::class)
            ->assertSee('Nenhum cadastro duplicado à vista');
    }
}
