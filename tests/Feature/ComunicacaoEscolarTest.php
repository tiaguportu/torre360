<?php

namespace Tests\Feature;

use App\Enums\PrioridadeChamado;
use App\Enums\StatusChamado;
use App\Enums\StatusRsvp;
use App\Enums\TipoEventoEscolar;
use App\Filament\Portal\Pages\CentralAtendimento;
use App\Filament\Portal\Pages\EventosEscolares;
use App\Models\AtendimentoChamado;
use App\Models\AtendimentoMensagem;
use App\Models\AtendimentoSetor;
use App\Models\EventoConfirmacao;
use App\Models\EventoEscolar;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ComunicacaoEscolarTest extends TestCase
{
    use RefreshDatabase;

    public function test_criacao_de_evento_escolar_e_confirmacao_de_rsvp(): void
    {
        $aluno = Pessoa::create([
            'nome' => 'Mateus Silva',
            'cpf' => '11122233344',
            'data_nascimento' => '2016-03-10',
        ]);

        $responsavel = Pessoa::create([
            'nome' => 'Mariana Silva (Mãe)',
            'cpf' => '55566677788',
        ]);

        $periodo = PeriodoLetivo::create([
            'nome' => '2026',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-15',
        ]);

        $turma = Turma::create([
            'nome' => '3º Ano B',
            'periodo_letivo_id' => $periodo->id,
        ]);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        $evento = EventoEscolar::create([
            'titulo' => 'Passeio ao Planetário',
            'tipo' => TipoEventoEscolar::PasseioCultural,
            'local' => 'Parque da Cidade',
            'data_inicio' => now()->addDays(5),
            'data_fim' => now()->addDays(5)->addHours(4),
            'limite_vagas' => 50,
            'exige_autorizacao' => true,
            'termo_autorizacao' => 'Autorizo meu filho a participar do passeio.',
            'publico_alvo' => 'todos',
            'ativo' => true,
        ]);

        $confirmacao = EventoConfirmacao::create([
            'evento_escolar_id' => $evento->id,
            'matricula_id' => $matricula->id,
            'responsavel_id' => $responsavel->id,
            'status' => StatusRsvp::Confirmado,
            'quantidade_acompanhantes' => 2,
            'autorizado' => true,
            'data_resposta' => now(),
            'ip_resposta' => '127.0.0.1',
        ]);

        $this->assertEquals(3, $evento->fresh()->total_confirmados); // 1 aluno + 2 acompanhantes
        $this->assertEquals(47, $evento->fresh()->vagas_restantes); // 50 - 3
        $this->assertTrue($confirmacao->autorizado);
    }

    public function test_abertura_e_resposta_de_chamado_na_central_de_atendimento(): void
    {
        $responsavel = Pessoa::create([
            'nome' => 'Carlos Ferreira',
            'cpf' => '99988877766',
        ]);

        $atendente = User::create([
            'name' => 'Secretária Joana',
            'email' => 'joana@escola.com.br',
            'password' => bcrypt('password'),
        ]);

        $setor = AtendimentoSetor::create([
            'nome' => 'Secretaria Geral',
            'ativo' => true,
            'ordem' => 1,
        ]);

        $chamado = AtendimentoChamado::create([
            'setor_id' => $setor->id,
            'solicitante_id' => $responsavel->id,
            'assunto' => 'Segunda via de histórico escolar',
            'prioridade' => PrioridadeChamado::Normal,
            'status' => StatusChamado::Aberto,
        ]);

        AtendimentoMensagem::create([
            'chamado_id' => $chamado->id,
            'pessoa_id' => $responsavel->id,
            'mensagem' => 'Gostaria de saber o prazo para emissão da 2ª via.',
        ]);

        $this->assertStringStartsWith('ATD-', $chamado->protocolo);
        $this->assertEquals(1, $chamado->mensagens()->count());

        // Resposta da escola
        AtendimentoMensagem::create([
            'chamado_id' => $chamado->id,
            'user_id' => $atendente->id,
            'mensagem' => 'O prazo é de até 48 horas úteis.',
        ]);

        $chamado->update([
            'status' => StatusChamado::Resolvido,
            'responsavel_atendimento_id' => $atendente->id,
        ]);

        $this->assertEquals(2, $chamado->mensagens()->count());
        $this->assertEquals(StatusChamado::Resolvido, $chamado->fresh()->status);

        // Família avalia com 5 estrelas
        $chamado->update([
            'avaliacao_nota' => 5,
            'avaliacao_comentario' => 'Atendimento muito ágil e atencioso!',
            'status' => StatusChamado::Fechado,
            'fechado_em' => now(),
        ]);

        $this->assertEquals(5, $chamado->fresh()->avaliacao_nota);
        $this->assertEquals(StatusChamado::Fechado, $chamado->fresh()->status);
    }

    public function test_portal_familia_interage_com_eventos_e_central(): void
    {
        $user = User::create([
            'name' => 'Pai Teste',
            'email' => 'pai@teste.com',
            'password' => bcrypt('password'),
        ]);

        $responsavel = Pessoa::create([
            'nome' => 'Pai Teste',
            'cpf' => '12312312399',
            'user_id' => $user->id,
        ]);

        $aluno = Pessoa::create([
            'nome' => 'Filho Teste',
            'cpf' => '45645645699',
            'data_nascimento' => '2017-01-15',
        ]);

        $user->pessoas()->attach($responsavel);
        $responsavel->alunos()->attach($aluno);

        $periodo = PeriodoLetivo::create([
            'nome' => '2026',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-15',
        ]);

        $turma = Turma::create([
            'nome' => '1º Ano A',
            'periodo_letivo_id' => $periodo->id,
        ]);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        $evento = EventoEscolar::create([
            'titulo' => 'Festa da Primavera',
            'tipo' => TipoEventoEscolar::FestaComemorativa,
            'data_inicio' => now()->addDays(10),
            'publico_alvo' => 'todos',
            'ativo' => true,
        ]);

        $setor = AtendimentoSetor::create([
            'nome' => 'Coordenação',
            'ativo' => true,
        ]);

        $this->actingAs($user);

        // Teste de RSVP no Portal
        Livewire::test(EventosEscolares::class)
            ->call('abrirModalRsvp', $evento->id, $matricula->id, 'confirmado')
            ->set('quantidadeAcompanhantes', 1)
            ->call('salvarRsvp')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('evento_confirmacoes', [
            'evento_escolar_id' => $evento->id,
            'matricula_id' => $matricula->id,
            'status' => 'confirmado',
            'quantidade_acompanhantes' => 1,
        ]);

        // Teste de Abertura de Chamado no Portal
        Livewire::test(CentralAtendimento::class)
            ->call('abrirNovoChamadoModal')
            ->set('novoSetorId', $setor->id)
            ->set('novoMatriculaId', $matricula->id)
            ->set('novoAssunto', 'Reunião com professor')
            ->set('novaMensagemInicial', 'Gostaria de agendar uma reunião sobre o rendimento.')
            ->call('criarChamado')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('atendimento_chamados', [
            'setor_id' => $setor->id,
            'matricula_id' => $matricula->id,
            'assunto' => 'Reunião com professor',
            'status' => 'aberto',
        ]);
    }
}
