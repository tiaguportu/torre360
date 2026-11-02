<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CategoriaDenuncia;
use App\Enums\StatusDenuncia;
use App\Enums\TipoMovimentacaoDenuncia;
use App\Filament\Resources\Denuncias\Pages\ListDenuncias;
use App\Filament\Resources\Denuncias\Pages\ViewDenuncia;
use App\Models\Denuncia;
use App\Models\DenunciaComite;
use App\Models\User;
use App\Notifications\DenunciaRecebidaNotification;
use App\Services\OuvidoriaService;
use App\Services\Pendencias\Fontes\DenunciasParaTratar;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Ouvidoria e convivência escolar (Ondas 35 e 63): anonimato real, código de acompanhamento que só existe uma vez, comitê por
 * categoria e fila restrita.
 */
class OuvidoriaTest extends TestCase
{
    use RefreshDatabase;

    private User $direcao;

    private User $membroBullying;

    private User $membroTodas;

    private User $foraDoComite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00:00'));
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        RateLimiter::clear('ouvidoria');

        foreach (['admin', 'coordenador', 'secretaria'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        foreach (['ViewAny:Denuncia', 'View:Denuncia', 'Tratar:Denuncia', 'VerTodas:Denuncia'] as $nome) {
            Role::findByName('admin')->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        foreach (['ViewAny:Denuncia', 'View:Denuncia', 'Tratar:Denuncia'] as $nome) {
            Role::findByName('coordenador')->givePermissionTo(Permission::findByName($nome));
        }

        // A secretaria tem a permissão de ler, mas não está em nenhum comitê.
        foreach (['ViewAny:Denuncia', 'View:Denuncia'] as $nome) {
            Role::findByName('secretaria')->givePermissionTo(Permission::findByName($nome));
        }

        $this->direcao = $this->usuario('admin');
        $this->membroBullying = $this->usuario('coordenador');
        $this->membroTodas = $this->usuario('coordenador');
        $this->foraDoComite = $this->usuario('secretaria');

        DenunciaComite::create(['user_id' => $this->membroBullying->id, 'categoria' => CategoriaDenuncia::Bullying]);
        DenunciaComite::create(['user_id' => $this->membroTodas->id, 'categoria' => null]);
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

    private function servico(): OuvidoriaService
    {
        return app(OuvidoriaService::class);
    }

    /**
     * @return array{denuncia: Denuncia, codigo: string}
     */
    private function registrar(CategoriaDenuncia $categoria = CategoriaDenuncia::Bullying, bool $anonima = true): array
    {
        return $this->servico()->registrar([
            'categoria' => $categoria->value,
            'descricao' => 'Um colega vem chamando outro de apelidos ofensivos no pátio, todos os dias.',
            'local_data' => 'Pátio, intervalo',
            'envolvidos' => [['nome' => 'Aluno X', 'papel' => 'autor']],
        ], $anonima, $anonima ? null : ['nome' => 'Maria', 'contato' => 'maria@email.test']);
    }

    private function dadosDoFormulario(array $extra = []): array
    {
        return $extra + [
            'categoria' => 'bullying',
            'descricao' => 'Um colega vem chamando outro de apelidos ofensivos no pátio, todos os dias.',
            'anonima' => '1',
        ];
    }

    // --- Canal público e anonimato ------------------------------------------------------------------------------------

    public function test_formulario_publico_abre_sem_login_e_nao_e_indexavel(): void
    {
        $this->get('/ouvidoria')
            ->assertOk()
            ->assertSee('Ouvidoria e convivência escolar')
            ->assertSee('noindex', false);
    }

    public function test_envio_anonimo_nao_guarda_usuario_identificacao_ip_nem_navegador(): void
    {
        $this->post('/ouvidoria', $this->dadosDoFormulario(), ['User-Agent' => 'NavegadorSecreto/9'])
            ->assertRedirect(route('ouvidoria.enviada'));

        $denuncia = Denuncia::firstOrFail();
        $this->assertTrue($denuncia->anonima);
        $this->assertNull($denuncia->user_id);
        $this->assertNull($denuncia->identificacao);
        foreach (['denuncias', 'denuncia_movimentacoes'] as $tabela) {
            $this->assertFalse(Schema::hasColumn($tabela, 'ip_address') || Schema::hasColumn($tabela, 'ip') || Schema::hasColumn($tabela, 'user_agent'), "{$tabela} não tem coluna de IP nem de navegador.");
        }
        $this->assertStringNotContainsString('NavegadorSecreto', json_encode(DB::table('denuncias')->get()).json_encode(DB::table('denuncia_movimentacoes')->get()));
    }

    public function test_envio_anonimo_de_usuario_logado_tambem_nao_guarda_o_usuario(): void
    {
        $this->actingAs($this->foraDoComite)
            ->post('/ouvidoria', $this->dadosDoFormulario());

        $this->assertNull(Denuncia::firstOrFail()->user_id);
    }

    public function test_envio_identificado_guarda_contato_criptografado_e_o_usuario_logado(): void
    {
        $this->actingAs($this->foraDoComite)
            ->post('/ouvidoria', $this->dadosDoFormulario(['anonima' => '0', 'nome' => 'Maria Souza', 'contato' => 'maria@email.test']))
            ->assertRedirect(route('ouvidoria.enviada'));

        $denuncia = Denuncia::firstOrFail();
        $this->assertFalse($denuncia->anonima);
        $this->assertSame($this->foraDoComite->id, $denuncia->user_id);
        $this->assertSame('Maria Souza', $denuncia->identificacao['nome']);
        $this->assertStringNotContainsString('maria@email.test', (string) DB::table('denuncias')->value('identificacao'));
    }

    public function test_texto_do_relato_fica_criptografado_no_banco(): void
    {
        $this->registrar();

        $bruto = (string) DB::table('denuncias')->value('descricao');

        $this->assertStringNotContainsString('apelidos', $bruto);
        $this->assertStringContainsString('apelidos', Denuncia::firstOrFail()->descricao);
    }

    public function test_envio_identificado_sem_nome_e_contato_e_recusado(): void
    {
        $this->post('/ouvidoria', $this->dadosDoFormulario(['anonima' => '0']))->assertSessionHasErrors('nome');

        $this->assertSame(0, Denuncia::count());
    }

    public function test_relato_muito_curto_e_recusado(): void
    {
        $this->post('/ouvidoria', $this->dadosDoFormulario(['descricao' => 'curto']))->assertSessionHasErrors('descricao');
    }

    public function test_campo_armadilha_preenchido_nao_grava_nada(): void
    {
        $this->post('/ouvidoria', $this->dadosDoFormulario(['website' => 'http://spam.test']))->assertRedirect(route('ouvidoria.formulario'));

        $this->assertSame(0, Denuncia::count());
    }

    public function test_limite_de_envios_por_origem(): void
    {
        config(['ouvidoria.max_envios' => 2]);

        $this->post('/ouvidoria', $this->dadosDoFormulario())->assertRedirect(route('ouvidoria.enviada'));
        $this->post('/ouvidoria', $this->dadosDoFormulario())->assertRedirect(route('ouvidoria.enviada'));
        $this->post('/ouvidoria', $this->dadosDoFormulario())->assertSessionHasErrors('descricao');

        $this->assertSame(2, Denuncia::count());
    }

    public function test_tela_de_confirmacao_mostra_protocolo_e_codigo_uma_unica_vez(): void
    {
        $this->post('/ouvidoria', $this->dadosDoFormulario());
        $denuncia = Denuncia::firstOrFail();

        $primeira = $this->get(route('ouvidoria.enviada'))->assertOk()->assertSee($denuncia->protocolo);
        $this->assertMatchesRegularExpression('/[A-Z2-9]{5}-[A-Z2-9]{5}/', $primeira->getContent());

        $this->get(route('ouvidoria.enviada'))->assertRedirect(route('ouvidoria.formulario'));
    }

    public function test_so_o_hash_do_codigo_fica_guardado(): void
    {
        ['denuncia' => $denuncia, 'codigo' => $codigo] = $this->registrar();

        $this->assertNotSame($codigo, $denuncia->codigo_hash);
        $this->assertSame(64, strlen($denuncia->codigo_hash));
        $this->assertTrue($denuncia->codigoConfere($codigo));
        $this->assertTrue($denuncia->codigoConfere('  '.strtolower($codigo).' '), 'Maiúscula, minúscula e espaço não importam.');
        $this->assertFalse($denuncia->codigoConfere('AAAAA-AAAAA'));
        $this->assertArrayNotHasKey('codigo_hash', $denuncia->toArray());
    }

    // --- Acompanhamento sem login -------------------------------------------------------------------------------------

    public function test_acompanhamento_com_protocolo_e_codigo_corretos_mostra_so_o_que_o_autor_pode_ver(): void
    {
        ['denuncia' => $denuncia, 'codigo' => $codigo] = $this->registrar();
        $this->servico()->movimentar($denuncia, $this->direcao, TipoMovimentacaoDenuncia::ParecerInterno, 'Parecer: reunir os pais do aluno X.');
        $this->servico()->movimentar($denuncia, $this->direcao, TipoMovimentacaoDenuncia::Devolutiva, 'Estamos conversando com as famílias.');

        $this->post('/ouvidoria/acompanhar', ['protocolo' => strtolower($denuncia->protocolo), 'codigo' => $codigo])
            ->assertOk()
            ->assertSee('Estamos conversando com as famílias.')
            ->assertSee('Manifestação recebida pela ouvidoria.')
            ->assertDontSee('reunir os pais');
    }

    public function test_acompanhamento_recusa_codigo_errado_sem_dizer_qual_dado_falhou(): void
    {
        ['denuncia' => $denuncia] = $this->registrar();

        $this->post('/ouvidoria/acompanhar', ['protocolo' => $denuncia->protocolo, 'codigo' => 'AAAAA-AAAAA'])
            ->assertOk()->assertSee('Protocolo ou código não conferem.');

        $this->post('/ouvidoria/acompanhar', ['protocolo' => 'OUV-2026-ZZZZZZ', 'codigo' => 'AAAAA-AAAAA'])
            ->assertOk()->assertSee('Protocolo ou código não conferem.');
    }

    public function test_acompanhamento_limita_tentativas_de_adivinhar_o_codigo(): void
    {
        config(['ouvidoria.max_consultas' => 3]);
        ['denuncia' => $denuncia, 'codigo' => $codigo] = $this->registrar();

        foreach (range(1, 3) as $i) {
            $this->post('/ouvidoria/acompanhar', ['protocolo' => $denuncia->protocolo, 'codigo' => 'AAAAA-AAAA'.$i]);
        }

        $this->post('/ouvidoria/acompanhar', ['protocolo' => $denuncia->protocolo, 'codigo' => $codigo])
            ->assertOk()->assertSee('Muitas tentativas');
    }

    public function test_autor_acrescenta_informacao_pelo_codigo_e_a_equipe_ve_no_historico(): void
    {
        ['denuncia' => $denuncia, 'codigo' => $codigo] = $this->registrar();

        $this->post('/ouvidoria/acompanhar/complemento', ['protocolo' => $denuncia->protocolo, 'codigo' => $codigo, 'texto' => 'Aconteceu de novo ontem.'])
            ->assertOk()->assertSee('Aconteceu de novo ontem.');

        $this->assertTrue($denuncia->movimentacoes()->where('tipo', TipoMovimentacaoDenuncia::ComplementoDoAutor->value)->exists());
    }

    public function test_manifestacao_encerrada_nao_recebe_complemento(): void
    {
        ['denuncia' => $denuncia, 'codigo' => $codigo] = $this->registrar();
        $this->servico()->encerrar($denuncia, $this->direcao, StatusDenuncia::Concluida, 'Medidas aplicadas.');

        $this->post('/ouvidoria/acompanhar/complemento', ['protocolo' => $denuncia->protocolo, 'codigo' => $codigo, 'texto' => 'Mais uma informação.'])
            ->assertSee('já foi encerrada');
    }

    // --- Prazo, comitê e aviso ----------------------------------------------------------------------------------------

    public function test_suspeita_de_violencia_tem_prazo_curto_e_marca_a_denuncia(): void
    {
        ['denuncia' => $comum] = $this->registrar(CategoriaDenuncia::Bullying);
        ['denuncia' => $grave] = $this->registrar(CategoriaDenuncia::SuspeitaViolencia);

        $this->assertFalse($comum->suspeita_violencia);
        $this->assertSame('2026-10-31', $comum->prazo_em->format('Y-m-d'));
        $this->assertTrue($grave->suspeita_violencia);
        $this->assertSame('2026-10-23', $grave->prazo_em->format('Y-m-d'));
    }

    public function test_aviso_vai_so_ao_comite_da_categoria_e_sem_o_relato(): void
    {
        Notification::fake();

        ['denuncia' => $denuncia] = $this->registrar(CategoriaDenuncia::Assedio);

        Notification::assertSentTo($this->membroTodas, DenunciaRecebidaNotification::class, function (DenunciaRecebidaNotification $n) {
            $texto = json_encode([$n->toMail($this->membroTodas)->introLines, $n->toDatabase($this->membroTodas)], JSON_UNESCAPED_UNICODE);

            return ! str_contains($texto, 'apelidos') && str_contains($texto, 'Assédio');
        });
        Notification::assertNotSentTo($this->membroBullying, DenunciaRecebidaNotification::class);
        Notification::assertNotSentTo($this->foraDoComite, DenunciaRecebidaNotification::class);
        $this->assertSame(CategoriaDenuncia::Assedio, $denuncia->categoria);
    }

    public function test_cada_um_ve_so_as_manifestacoes_do_seu_comite(): void
    {
        ['denuncia' => $bullying] = $this->registrar(CategoriaDenuncia::Bullying);
        ['denuncia' => $assedio] = $this->registrar(CategoriaDenuncia::Assedio);

        $ids = fn (User $u) => Denuncia::query()->visiveisPara($u)->pluck('id')->sort()->values()->all();

        $this->assertSame([$bullying->id, $assedio->id], $ids($this->direcao), 'VerTodas enxerga tudo.');
        $this->assertSame([$bullying->id, $assedio->id], $ids($this->membroTodas), 'Comitê sem categoria enxerga tudo.');
        $this->assertSame([$bullying->id], $ids($this->membroBullying));
        $this->assertSame([], $ids($this->foraDoComite), 'Ter a permissão sem estar no comitê não basta.');
    }

    public function test_tela_do_painel_respeita_o_comite(): void
    {
        ['denuncia' => $bullying] = $this->registrar(CategoriaDenuncia::Bullying);
        ['denuncia' => $assedio] = $this->registrar(CategoriaDenuncia::Assedio);

        $this->como($this->membroBullying);
        Livewire::test(ListDenuncias::class)->assertCanSeeTableRecords([$bullying])->assertCanNotSeeTableRecords([$assedio]);
        $this->assertTrue($this->membroBullying->can('view', $bullying));
        $this->assertFalse($this->membroBullying->can('view', $assedio));

        $this->como($this->foraDoComite);
        Livewire::test(ListDenuncias::class)->assertCanNotSeeTableRecords([$bullying, $assedio]);
        $this->assertFalse($this->foraDoComite->can('view', $bullying));
    }

    public function test_ninguem_cria_edita_ou_apaga_a_manifestacao_pelo_painel(): void
    {
        ['denuncia' => $denuncia] = $this->registrar();

        foreach ([$this->direcao, $this->membroTodas] as $usuario) {
            $this->assertFalse($usuario->can('create', Denuncia::class));
            $this->assertFalse($usuario->can('update', $denuncia));
            $this->assertFalse($usuario->can('delete', $denuncia));
        }
    }

    // --- Tratamento ---------------------------------------------------------------------------------------------------

    public function test_assumir_inicia_a_apuracao_uma_so_vez(): void
    {
        ['denuncia' => $denuncia] = $this->registrar();

        $this->assertTrue($this->servico()->assumir($denuncia, $this->membroTodas));
        $this->assertFalse($this->servico()->assumir($denuncia, $this->direcao));
        $this->assertSame(StatusDenuncia::EmApuracao, $denuncia->refresh()->status);
        $this->assertSame($this->membroTodas->id, $denuncia->responsavel_user_id);
    }

    public function test_encerrar_exige_conclusao_e_a_devolutiva_chega_ao_autor(): void
    {
        ['denuncia' => $denuncia, 'codigo' => $codigo] = $this->registrar();

        try {
            $this->servico()->encerrar($denuncia, $this->direcao, StatusDenuncia::Concluida, '  ');
            $this->fail('Deveria exigir a conclusão.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('conclusão', $e->getMessage());
        }

        $this->servico()->encerrar($denuncia, $this->direcao, StatusDenuncia::Concluida, 'Medidas aplicadas com as famílias.');

        $this->assertNotNull($denuncia->refresh()->encerrada_em);
        $this->post('/ouvidoria/acompanhar', ['protocolo' => $denuncia->protocolo, 'codigo' => $codigo])
            ->assertSee('Medidas aplicadas com as famílias.')->assertSee('Concluída');
    }

    public function test_comite_trata_pela_tela_registra_andamento_e_encerra(): void
    {
        ['denuncia' => $denuncia] = $this->registrar();
        $this->como($this->membroBullying);

        Livewire::test(ViewDenuncia::class, ['record' => $denuncia->getKey()])
            ->mountAction('registrar')
            ->fillForm(['tipo' => TipoMovimentacaoDenuncia::ConselhoTutelar->value, 'texto' => 'Ofício enviado ao Conselho Tutelar.'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $registro = $denuncia->movimentacoes()->where('tipo', TipoMovimentacaoDenuncia::ConselhoTutelar->value)->firstOrFail();
        $this->assertFalse($registro->visivel_ao_autor, 'A comunicação ao Conselho Tutelar nunca aparece ao autor.');

        Livewire::test(ViewDenuncia::class, ['record' => $denuncia->getKey()])
            ->mountAction('encerrar')
            ->fillForm(['status' => StatusDenuncia::Arquivada->value, 'conclusao' => 'Sem elementos para apurar.', 'confirmo' => true])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(StatusDenuncia::Arquivada, $denuncia->refresh()->status);
    }

    public function test_quem_nao_e_do_comite_nao_abre_a_manifestacao_pelo_link(): void
    {
        ['denuncia' => $denuncia] = $this->registrar();
        $this->como($this->foraDoComite);

        $this->get('/admin/ouvidoria/'.$denuncia->getKey())->assertNotFound();
    }

    public function test_fila_da_mesa_conta_so_o_que_o_usuario_pode_ler(): void
    {
        $this->registrar(CategoriaDenuncia::Bullying);
        ['denuncia' => $atrasada] = $this->registrar(CategoriaDenuncia::Assedio);
        $atrasada->forceFill(['prazo_em' => now()->subDay()])->save();
        ['denuncia' => $encerrada] = $this->registrar(CategoriaDenuncia::Bullying);
        $this->servico()->encerrar($encerrada, $this->direcao, StatusDenuncia::Arquivada, 'Sem elementos.');

        $fila = new DenunciasParaTratar;

        $this->assertSame(2, $fila->contar($this->direcao));
        $this->assertSame(1, $fila->atrasados($this->direcao));
        $this->assertSame(1, $fila->contar($this->membroBullying));
        $this->assertSame(0, $fila->atrasados($this->membroBullying));
        $this->assertSame(0, $fila->contar($this->foraDoComite));
        $this->assertStringNotContainsString('apelidos', json_encode($fila->itens($this->direcao, 5)), 'A fila mostra o protocolo e o assunto, nunca o relato.');
    }
}
