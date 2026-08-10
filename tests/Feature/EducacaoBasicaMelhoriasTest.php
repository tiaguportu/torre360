<?php

namespace Tests\Feature;

use App\Models\AtendimentoEnfermagem;
use App\Models\CronogramaAula;
use App\Models\FichaMedica;
use App\Models\Habilidade;
use App\Models\Matricula;
use App\Models\OcorrenciaEscolar;
use App\Models\Pessoa;
use App\Models\TipoOcorrencia;
use App\Models\User;
use App\Notifications\OcorrenciaRegistradaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EducacaoBasicaMelhoriasTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_save_cronograma_aula_with_dever_casa_anexos_and_habilidades_bncc(): void
    {
        $cronograma = CronogramaAula::factory()->create([
            'conteudo_ministrado' => 'Leitura e interpretação de texto',
            'dever_casa' => 'Ler capítulos 1 e 2 do livro de literatura.',
            'anexo_material' => ['materiais-aula/slides.pdf', 'materiais-aula/exercicios.pdf'],
        ]);

        $habilidade = Habilidade::factory()->create([
            'codigo' => 'EF15LP01',
            'nome' => 'Identificar a função social de textos que circulam em campos da vida social.',
        ]);

        $cronograma->habilidades()->attach($habilidade->id);

        $this->assertDatabaseHas('cronograma_aula', [
            'id' => $cronograma->id,
            'dever_casa' => 'Ler capítulos 1 e 2 do livro de literatura.',
        ]);

        $this->assertDatabaseHas('cronograma_aula_habilidade', [
            'cronograma_aula_id' => $cronograma->id,
            'habilidade_id' => $habilidade->id,
        ]);

        $this->assertCount(2, $cronograma->fresh()->anexo_material);
    }

    public function test_can_create_ficha_medica_with_alergias_alimentares(): void
    {
        $aluno = Pessoa::factory()->create();

        $ficha = FichaMedica::create([
            'pessoa_id' => $aluno->id,
            'tipo_sanguineo' => 'O+',
            'has_alergia_lactose' => true,
            'has_alergia_gluten' => false,
            'has_alergia_amendoim' => true,
            'outras_alergias_alimentares' => 'Frutos do mar',
            'observacoes_alimentares' => 'Evitar refeições com amendoim ou lactose na cantina.',
        ]);

        $this->assertDatabaseHas('ficha_medicas', [
            'pessoa_id' => $aluno->id,
            'has_alergia_lactose' => true,
            'has_alergia_amendoim' => true,
        ]);

        $this->assertTrue($aluno->fresh()->fichaMedica->has_alergia_lactose);
    }

    public function test_can_record_atendimento_enfermagem(): void
    {
        $aluno = Pessoa::factory()->create();
        $enfermeiro = User::factory()->create();

        $atendimento = AtendimentoEnfermagem::create([
            'pessoa_id' => $aluno->id,
            'atendido_por_user_id' => $enfermeiro->id,
            'data_hora' => now(),
            'sintomas_queixa' => 'Cefaleia e leve estado febril.',
            'procedimento_realizado' => 'Verificação de temperatura (37.8°C) e repouso.',
            'medicamento_ministrado' => 'Dipirona 20 gotas',
            'notificado_responsaveis' => true,
        ]);

        $this->assertDatabaseHas('atendimento_enfermagems', [
            'id' => $atendimento->id,
            'pessoa_id' => $aluno->id,
            'medicamento_ministrado' => 'Dipirona 20 gotas',
        ]);
    }

    public function test_ocorrencia_escolar_triggers_notification_when_enabled(): void
    {
        Notification::fake();

        $aluno = Pessoa::factory()->create();
        $responsavelPessoa = Pessoa::factory()->create();
        $userResponsavel = User::factory()->create();
        $responsavelPessoa->users()->attach($userResponsavel->id);

        $aluno->responsaveis()->attach($responsavelPessoa->id);

        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);
        $tipo = TipoOcorrencia::create([
            'nome' => 'Atraso na Chegada',
            'categoria' => 'operacional',
            'gravidade' => 'leve',
            'notificar_responsaveis_padrao' => true,
        ]);

        $ocorrencia = OcorrenciaEscolar::create([
            'matricula_id' => $matricula->id,
            'tipo_ocorrencia_id' => $tipo->id,
            'data_hora' => now(),
            'descricao' => 'Chegada às 08:20h, 50 minutos após o horário limite.',
            'notificar_responsaveis' => true,
        ]);

        Notification::assertSentTo(
            [$userResponsavel],
            OcorrenciaRegistradaNotification::class
        );
    }

    public function test_ocorrencia_escolar_does_not_trigger_notification_when_disabled(): void
    {
        Notification::fake();

        $aluno = Pessoa::factory()->create();
        $responsavelPessoa = Pessoa::factory()->create();
        $userResponsavel = User::factory()->create();
        $responsavelPessoa->users()->attach($userResponsavel->id);
        $aluno->responsaveis()->attach($responsavelPessoa->id);

        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);
        $tipo = TipoOcorrencia::create([
            'nome' => 'Conversa Interna',
            'categoria' => 'disciplinar',
            'gravidade' => 'leve',
            'notificar_responsaveis_padrao' => false,
        ]);

        $ocorrencia = OcorrenciaEscolar::create([
            'matricula_id' => $matricula->id,
            'tipo_ocorrencia_id' => $tipo->id,
            'data_hora' => now(),
            'descricao' => 'Orientação verbal reservada.',
            'notificar_responsaveis' => false,
        ]);

        Notification::assertNotSentTo(
            [$userResponsavel],
            OcorrenciaRegistradaNotification::class
        );
    }
}
