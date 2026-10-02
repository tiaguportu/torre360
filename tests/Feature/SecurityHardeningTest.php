<?php

namespace Tests\Feature;

use App\Filament\Portal\Pages\CentralAtendimento;
use App\Models\AtendimentoChamado;
use App\Models\AtendimentoMensagem;
use App\Models\AtendimentoSetor;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);
    }

    public function test_rota_de_auto_registro_no_admin_esta_desativada(): void
    {
        $response = $this->get('/admin/register');

        $response->assertNotFound();
    }

    public function test_central_atendimento_bloqueia_upload_de_arquivo_executavel_ou_invalido(): void
    {
        Storage::fake('local');

        $aluno = Pessoa::create(['nome' => 'Filho Teste', 'cpf' => '11122233344']);
        $responsavel = Pessoa::create(['nome' => 'Pai Teste', 'cpf' => '55566677788']);

        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole('responsavel');
        $user->pessoas()->attach([$aluno->id, $responsavel->id]);

        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $turma = Turma::create(['nome' => 'Turma Teste', 'periodo_letivo_id' => $periodo->id]);
        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'periodo_letivo_id' => $periodo->id,
            'situacao' => 'ativa',
        ]);

        $setor = AtendimentoSetor::create(['nome' => 'Secretaria', 'ativo' => true, 'ordem' => 1]);

        $arquivoMalicioso = UploadedFile::fake()->create('malware.exe', 50, 'application/x-msdownload');

        $this->actingAs($user);

        Livewire::test(CentralAtendimento::class)
            ->set('novoSetorId', $setor->id)
            ->set('novoMatriculaId', $matricula->id)
            ->set('novoAssunto', 'Dúvida com documento')
            ->set('novaMensagemInicial', 'Segue o arquivo em anexo')
            ->set('novoAnexo', $arquivoMalicioso)
            ->call('criarChamado')
            ->assertHasErrors(['novoAnexo']);
    }

    public function test_central_atendimento_salva_anexo_valido_no_disco_local(): void
    {
        Storage::fake('local');

        $aluno = Pessoa::create(['nome' => 'Filha Teste', 'cpf' => '22233344455']);
        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole('responsavel');
        $user->pessoas()->attach($aluno->id);

        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $turma = Turma::create(['nome' => 'Turma B', 'periodo_letivo_id' => $periodo->id]);
        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'periodo_letivo_id' => $periodo->id,
            'situacao' => 'ativa',
        ]);

        $setor = AtendimentoSetor::create(['nome' => 'Financeiro', 'ativo' => true, 'ordem' => 1]);
        $arquivoPdf = UploadedFile::fake()->create('comprovante.pdf', 100, 'application/pdf');

        $this->actingAs($user);

        Livewire::test(CentralAtendimento::class)
            ->set('novoSetorId', $setor->id)
            ->set('novoMatriculaId', $matricula->id)
            ->set('novoAssunto', 'Envio de comprovante de pagamento')
            ->set('novaMensagemInicial', 'Segue o comprovante em anexo para conferência.')
            ->set('novoAnexo', $arquivoPdf)
            ->call('criarChamado')
            ->assertHasNoErrors();

        $mensagem = AtendimentoMensagem::latest('id')->first();
        $this->assertNotNull($mensagem->anexo_path);
        Storage::disk('local')->assertExists($mensagem->anexo_path);
    }

    public function test_central_atendimento_impede_idor_em_chamados_de_outros_usuarios(): void
    {
        // Usuário A e seu Chamado
        $pessoaA = Pessoa::create(['nome' => 'Pessoa A', 'cpf' => '33344455566']);
        $userA = User::factory()->create(['activated_at' => now()]);
        $userA->assignRole('responsavel');
        $userA->pessoas()->attach($pessoaA->id);

        $setor = AtendimentoSetor::create(['nome' => 'Coordenação', 'ativo' => true, 'ordem' => 1]);
        $chamadoA = AtendimentoChamado::create([
            'setor_id' => $setor->id,
            'solicitante_id' => $pessoaA->id,
            'assunto' => 'Chamado Sigiloso do Aluno A',
            'status' => 'aberto',
        ]);

        // Usuário B tenta manipular o chamado A
        $pessoaB = Pessoa::create(['nome' => 'Pessoa B', 'cpf' => '77788899900']);
        $userB = User::factory()->create(['activated_at' => now()]);
        $userB->assignRole('responsavel');
        $userB->pessoas()->attach($pessoaB->id);

        $this->actingAs($userB);

        $component = Livewire::test(CentralAtendimento::class)
            ->set('chamadoSelecionadoId', $chamadoA->id)
            ->set('respostaTexto', 'Tentativa de injeção de mensagem indevida')
            ->call('enviarResposta');

        // A mensagem NÃO deve ser adicionada ao chamado A
        $this->assertDatabaseMissing('atendimento_mensagens', [
            'chamado_id' => $chamadoA->id,
            'mensagem' => 'Tentativa de injeção de mensagem indevida',
        ]);
    }
}
