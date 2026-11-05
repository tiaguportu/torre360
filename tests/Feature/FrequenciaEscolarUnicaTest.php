<?php

namespace Tests\Feature;

use App\Filament\Resources\CronogramaAulas\Pages\LancarFrequencia;
use App\Models\CronogramaAula;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Um aluno tem no máximo um registro de frequência por aula: o banco garante, a migration limpa as duplicatas antigas
 * e a abertura da chamada não cria linha em dobro nem para quem não estava matriculado na data.
 */
class FrequenciaEscolarUnicaTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_11_05_120100_add_unique_matricula_aula_to_frequencia_escolar_table.php';

    private const INDICE = 'frequencia_escolar_matricula_aula_unique';

    private User $admin;

    private Turma $turma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['activated_at' => now(), 'email_verified_at' => now()]);
        $this->admin->assignRole('super_admin');

        $this->turma = Turma::factory()->create(['periodo_letivo_id' => PeriodoLetivo::factory()->create()->id]);
    }

    private function aula(string $data = '2026-03-10'): CronogramaAula
    {
        return CronogramaAula::factory()->create([
            'turma_id' => $this->turma->id,
            'pessoa_id' => Pessoa::factory()->create()->id,
            'data' => $data,
        ]);
    }

    private function matricula(array $atributos = []): Matricula
    {
        return Matricula::factory()->create(array_merge([
            'pessoa_id' => Pessoa::factory()->create()->id,
            'turma_id' => $this->turma->id,
            'situacao' => 'ativa',
        ], $atributos));
    }

    private function frequencias(CronogramaAula $aula)
    {
        return FrequenciaEscolar::query()->where('cronograma_aula_id', $aula->id);
    }

    public function test_o_banco_recusa_duas_linhas_para_o_mesmo_aluno_e_a_mesma_aula(): void
    {
        $aula = $this->aula();
        $matricula = $this->matricula();

        FrequenciaEscolar::create(['matricula_id' => $matricula->id, 'cronograma_aula_id' => $aula->id, 'situacao' => 'presente']);

        $this->expectException(UniqueConstraintViolationException::class);

        FrequenciaEscolar::create(['matricula_id' => $matricula->id, 'cronograma_aula_id' => $aula->id, 'situacao' => 'ausente']);
    }

    public function test_o_mesmo_aluno_tem_uma_linha_em_cada_aula_e_a_aula_uma_linha_por_aluno(): void
    {
        $aulaA = $this->aula('2026-03-10');
        $aulaB = $this->aula('2026-03-11');
        $maria = $this->matricula();
        $joao = $this->matricula();

        foreach ([$aulaA, $aulaB] as $aula) {
            foreach ([$maria, $joao] as $matricula) {
                FrequenciaEscolar::create(['matricula_id' => $matricula->id, 'cronograma_aula_id' => $aula->id, 'situacao' => 'presente']);
            }
        }

        $this->assertSame(4, FrequenciaEscolar::count());
    }

    public function test_a_migration_apaga_as_duplicatas_e_fica_com_a_linha_mais_informativa(): void
    {
        $matricula = $this->matricula();
        [$a1, $a2, $a3, $a4] = [$this->aula('2026-03-10'), $this->aula('2026-03-11'), $this->aula('2026-03-12'), $this->aula('2026-03-13')];

        // O índice já existe (as migrations rodaram); sai para reproduzir o banco antes da correção.
        Schema::table('frequencia_escolar', fn (Blueprint $t) => $t->dropUnique(self::INDICE));

        $linha = fn (CronogramaAula $aula, ?string $situacao, string $atualizadaEm, array $extra = []): int => DB::table('frequencia_escolar')->insertGetId(array_merge([
            'matricula_id' => $matricula->id,
            'cronograma_aula_id' => $aula->id,
            'situacao' => $situacao,
            'created_at' => $atualizadaEm,
            'updated_at' => $atualizadaEm,
        ], $extra));

        // Aula 1: a linha sem situação (mais nova) perde para a que tem presença lançada.
        $semSituacao = $linha($a1, null, '2026-03-10 12:00:00');
        $comPresenca = $linha($a1, 'presente', '2026-03-10 09:00:00');

        // Aula 2: a falta justificada (mais antiga) ganha da falta comum, mais nova.
        $faltaComum = $linha($a2, 'ausente', '2026-03-11 12:00:00');
        $faltaJustificada = $linha($a2, 'ausente', '2026-03-11 09:00:00', ['justificada' => true]);

        // Aula 3: sem diferença de conteúdo, fica a atualizada por último.
        $antiga = $linha($a3, 'presente', '2026-03-12 09:00:00');
        $recente = $linha($a3, 'presente', '2026-03-12 12:00:00');

        // Aula 4: linha única, não é tocada.
        $unica = $linha($a4, 'presente', '2026-03-13 09:00:00');

        (require base_path(self::MIGRATION))->up();

        $this->assertEqualsCanonicalizing(
            [$comPresenca, $faltaJustificada, $recente, $unica],
            DB::table('frequencia_escolar')->pluck('id')->all()
        );
        $this->assertNotContains($semSituacao, DB::table('frequencia_escolar')->pluck('id')->all());
        $this->assertNotContains($faltaComum, DB::table('frequencia_escolar')->pluck('id')->all());
        $this->assertNotContains($antiga, DB::table('frequencia_escolar')->pluck('id')->all());

        // E o índice voltou a valer.
        $this->expectException(UniqueConstraintViolationException::class);
        FrequenciaEscolar::create(['matricula_id' => $matricula->id, 'cronograma_aula_id' => $a4->id, 'situacao' => 'ausente']);
    }

    public function test_a_chamada_cria_uma_linha_por_aluno_e_nao_duplica_ao_abrir_de_novo(): void
    {
        $aula = $this->aula();
        $this->matricula();
        $this->matricula();

        Livewire::actingAs($this->admin)->test(LancarFrequencia::class, ['record' => $aula->getKey()])->assertHasNoErrors();
        Livewire::actingAs($this->admin)->test(LancarFrequencia::class, ['record' => $aula->getKey()])->assertHasNoErrors();

        $this->assertSame(2, $this->frequencias($aula)->count());
        $this->assertSame(2, $this->frequencias($aula)->distinct('matricula_id')->count('matricula_id'));
    }

    public function test_a_chamada_nao_falha_nem_duplica_quando_outra_aba_cria_a_linha_no_meio(): void
    {
        $aula = $this->aula();
        $matricula = $this->matricula();
        $concorrente = false;

        // Logo depois de a página ler quem ainda não tem linha e antes de gravar, "outra aba" grava a mesma linha. Tem de ser
        // fora da gravação da página: dentro do savepoint do createOrFirst a linha concorrente seria desfeita junto.
        DB::listen(function ($consulta) use (&$concorrente, $aula, $matricula): void {
            if ($concorrente || ! str_starts_with($consulta->sql, 'select "id" from "matricula"')) {
                return;
            }

            $concorrente = true;
            DB::table('frequencia_escolar')->insert([
                'matricula_id' => $matricula->id,
                'cronograma_aula_id' => $aula->id,
                'situacao' => 'presente',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Livewire::actingAs($this->admin)->test(LancarFrequencia::class, ['record' => $aula->getKey()])->assertHasNoErrors();

        $this->assertTrue($concorrente, 'a gravação concorrente não foi simulada');
        $this->assertSame(1, $this->frequencias($aula)->count());
        $this->assertSame('presente', $this->frequencias($aula)->value('situacao'));
    }

    public function test_a_chamada_so_lista_quem_estava_matriculado_na_data_da_aula(): void
    {
        $aula = $this->aula('2026-03-10');

        $vigente = $this->matricula(['data_ativacao' => '2026-02-01', 'data_desativacao' => null]);
        $semDatas = $this->matricula(['data_ativacao' => null, 'data_desativacao' => null]);
        $saiuAntes = $this->matricula(['data_ativacao' => '2026-02-01', 'data_desativacao' => '2026-03-01']);
        $entrouDepois = $this->matricula(['data_ativacao' => '2026-03-20', 'data_desativacao' => null]);

        Livewire::actingAs($this->admin)->test(LancarFrequencia::class, ['record' => $aula->getKey()])->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(
            [$vigente->id, $semDatas->id],
            $this->frequencias($aula)->pluck('matricula_id')->all()
        );
        $this->assertNotContains($saiuAntes->id, $this->frequencias($aula)->pluck('matricula_id')->all());
        $this->assertNotContains($entrouDepois->id, $this->frequencias($aula)->pluck('matricula_id')->all());
    }

    public function test_a_lista_de_nomes_da_chamada_e_lida_uma_vez_so_e_nao_uma_por_aluno(): void
    {
        $aula = $this->aula();
        foreach (range(1, 4) as $_) {
            $this->matricula();
        }

        // Abre a página uma vez para criar as linhas, depois mede a leitura da turma na abertura seguinte.
        Livewire::actingAs($this->admin)->test(LancarFrequencia::class, ['record' => $aula->getKey()]);

        DB::enableQueryLog();
        Livewire::actingAs($this->admin)->test(LancarFrequencia::class, ['record' => $aula->getKey()]);
        $consultas = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $leiturasDaTurma = $consultas->filter(fn (string $sql): bool => str_starts_with($sql, 'select * from "matricula" where "turma_id" = ?'))->count();

        $this->assertLessThanOrEqual(1, $leiturasDaTurma, "a turma foi lida {$leiturasDaTurma} vezes");
    }

    public function test_escopo_matriculadas_em_trata_as_pontas_e_as_datas_em_aberto(): void
    {
        $naAtivacao = $this->matricula(['data_ativacao' => '2026-03-10', 'data_desativacao' => null]);
        $naDesativacao = $this->matricula(['data_ativacao' => null, 'data_desativacao' => '2026-03-10']);
        $abertaNasDuas = $this->matricula(['data_ativacao' => null, 'data_desativacao' => null]);

        $ids = Matricula::query()->matriculadasEm('2026-03-10')->pluck('id')->all();

        // Entrou no dia: conta. Saiu no dia: já não conta (a desativação vale a partir dela, como em Turma::matriculasAtivasCount).
        $this->assertContains($naAtivacao->id, $ids);
        $this->assertNotContains($naDesativacao->id, $ids);
        $this->assertContains($abertaNasDuas->id, $ids);
    }
}
