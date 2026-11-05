<?php

namespace Tests\Feature;

use App\Filament\Resources\Matriculas\Pages\EditMatricula;
use App\Filament\Resources\Matriculas\Pages\ListMatriculas;
use App\Models\Contrato;
use App\Models\CronogramaAula;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Excluir uma matrícula apagava, em cascata, frequência, resultados, bolsas, acordos e transferências e deixava o
 * contrato órfão. Quem tem histórico não é mais excluído: o caminho é o Cancelamento de Matrícula.
 */
class MatriculaExclusaoProtegidaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tabelas com chave para `matricula` que NÃO são histórico: somem junto (ou ficam sem vínculo) sem perda. Tabela nova
     * com chave para `matricula` tem de entrar aqui ou em `Matricula::HISTORICO_QUE_IMPEDE_EXCLUSAO`.
     */
    private const DESCARTAVEIS = [
        'atendimento_chamados',          // set null: o chamado sobrevive sem a matrícula
        'documento_inserido',            // arquivos enviados na matrícula
        'evento_confirmacoes',           // confirmação de presença em evento
        'gabarito_folhas',               // folha de gabarito impressa em branco
        'historico_escolar_anos',        // set null: o histórico escolar sobrevive à matrícula
        'matricula_professor_preceptoria', // designação de professor
        'preceptoria',                   // set null
        'proposta_comercials',           // set null
        'retencao_tarefas',              // tarefa operacional
        'reuniao_encaminhamentos',       // set null
        'tipo_documento_matricula',      // exigência de documento
    ];

    private User $admin;

    private Turma $turma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'aluno', 'guard_name' => 'web']); // o formulário da matrícula oferece usuários com este papel
        $this->admin =User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $this->admin->assignRole('super_admin');
        session(['active_role' => 'super_admin']);
        $this->actingAs($this->admin);

        $this->turma = Turma::factory()->create(['periodo_letivo_id' => PeriodoLetivo::factory()->create()->id]);
    }

    private function matricula(): Matricula
    {
        return Matricula::factory()->create([
            'pessoa_id' => Pessoa::factory()->create()->id,
            'turma_id' => $this->turma->id,
            'situacao' => 'ativa',
        ]);
    }

    private function lancarFrequencia(Matricula $matricula): void
    {
        $aula = CronogramaAula::factory()->create([
            'turma_id' => $this->turma->id,
            'pessoa_id' => Pessoa::factory()->create()->id,
            'data' => '2026-03-10',
        ]);

        FrequenciaEscolar::create(['matricula_id' => $matricula->id, 'cronograma_aula_id' => $aula->id, 'situacao' => 'presente']);
    }

    private function criarContrato(Matricula $matricula): Contrato
    {
        return Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => 6000.00, 'data_aceite' => '2026-01-10']);
    }

    public function test_matricula_sem_historico_pode_ser_excluida(): void
    {
        $matricula = $this->matricula();

        $this->assertFalse($matricula->possuiHistorico());
        $this->assertSame([], $matricula->historicoQueImpedeExclusao());

        $matricula->delete();

        $this->assertDatabaseMissing('matricula', ['id' => $matricula->id]);
    }

    public function test_frequencia_lancada_impede_a_exclusao(): void
    {
        $matricula = $this->matricula();
        $this->lancarFrequencia($matricula);

        $this->assertTrue($matricula->possuiHistorico());
        $this->assertSame(['registros de frequência' => 1], $matricula->historicoQueImpedeExclusao());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("A matrícula Nº {$matricula->id} possui histórico (registros de frequência: 1) e não pode ser excluída");

        try {
            $matricula->delete();
        } finally {
            $this->assertDatabaseHas('matricula', ['id' => $matricula->id]);
            $this->assertDatabaseCount('frequencia_escolar', 1);
        }
    }

    public function test_mensagem_lista_tudo_o_que_a_matricula_tem(): void
    {
        $matricula = $this->matricula();
        $this->lancarFrequencia($matricula);
        $this->criarContrato($matricula);

        $historico = $matricula->historicoQueImpedeExclusao();

        $this->assertSame(['registros de frequência' => 1, 'contratos' => 1], $historico);
        $this->assertSame('registros de frequência: 1, contratos: 1', $matricula->descreverHistorico($historico));
    }

    public function test_tela_de_edicao_explica_e_mantem_a_matricula_com_historico(): void
    {
        $matricula = $this->matricula();
        $this->criarContrato($matricula);

        Livewire::test(EditMatricula::class, ['record' => $matricula->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified('Matrícula não pode ser excluída');

        $this->assertDatabaseHas('matricula', ['id' => $matricula->id]);
        $this->assertDatabaseHas('contrato', ['matricula_id' => $matricula->id]);
    }

    public function test_tela_de_edicao_exclui_matricula_sem_historico(): void
    {
        $matricula = $this->matricula();

        Livewire::test(EditMatricula::class, ['record' => $matricula->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified();

        $this->assertDatabaseMissing('matricula', ['id' => $matricula->id]);
    }

    public function test_exclusao_em_lote_mantem_as_matriculas_com_historico(): void
    {
        $livre = $this->matricula();
        $comHistorico = $this->matricula();
        $this->lancarFrequencia($comHistorico);

        Livewire::test(ListMatriculas::class)
            ->callTableBulkAction('delete', [$livre, $comHistorico]);

        $this->assertDatabaseMissing('matricula', ['id' => $livre->id]);
        $this->assertDatabaseHas('matricula', ['id' => $comHistorico->id]);
        $this->assertDatabaseCount('frequencia_escolar', 1);
    }

    public function test_tabelas_e_colunas_do_historico_existem(): void
    {
        foreach (Matricula::HISTORICO_QUE_IMPEDE_EXCLUSAO as $tabela => [$coluna]) {
            $this->assertTrue(Schema::hasTable($tabela), "A tabela {$tabela} não existe mais: ajuste Matricula::HISTORICO_QUE_IMPEDE_EXCLUSAO.");
            $this->assertTrue(Schema::hasColumn($tabela, $coluna), "A coluna {$tabela}.{$coluna} não existe mais: ajuste Matricula::HISTORICO_QUE_IMPEDE_EXCLUSAO.");
        }
    }

    public function test_toda_tabela_que_aponta_para_matricula_esta_classificada(): void
    {
        $classificadas = array_merge(array_keys(Matricula::HISTORICO_QUE_IMPEDE_EXCLUSAO), self::DESCARTAVEIS);
        $naoClassificadas = [];

        foreach (Schema::getTables() as $tabela) {
            $nome = $tabela['name'];

            if ($nome === 'matricula' || in_array($nome, $classificadas, true)) {
                continue;
            }

            $apontaPorChave = collect(Schema::getForeignKeys($nome))->contains(fn (array $fk): bool => $fk['foreign_table'] === 'matricula');
            $apontaPorColuna = collect(Schema::getColumnListing($nome))->contains(fn (string $coluna): bool => (bool) preg_match('/(^|_)matricula(_origem)?_id$/', $coluna));

            if ($apontaPorChave || $apontaPorColuna) {
                $naoClassificadas[] = $nome;
            }
        }

        $this->assertSame([], $naoClassificadas, 'Tabela nova ligada a matricula: decida se é histórico (Matricula::HISTORICO_QUE_IMPEDE_EXCLUSAO) ou descartável (este teste): '.implode(', ', $naoClassificadas));
    }
}
