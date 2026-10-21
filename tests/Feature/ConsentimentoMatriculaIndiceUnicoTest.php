<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ConsentimentoMatricula;
use App\Models\Matricula;
use App\Models\TipoConsentimento;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * O índice único de `consentimento_matriculas` tinha nome gerado de 66 caracteres, acima do limite de 64 do
 * MySQL/MariaDB, e a falha deixava a tabela sem o índice. Estes testes garantem o nome curto, a unicidade e que a
 * migration de garantia repõe o índice em ambientes que ficaram sem ele.
 */
class ConsentimentoMatriculaIndiceUnicoTest extends TestCase
{
    use RefreshDatabase;

    private const TABELA = 'consentimento_matriculas';

    private const COLUNAS = ['matricula_id', 'tipo_consentimento_id'];

    private const INDICE = 'uq_consentimento_matricula_tipo';

    private function garantirIndice(): void
    {
        (require base_path('database/migrations/2026_10_21_100000_ensure_unique_index_on_consentimento_matriculas.php'))->up();
    }

    private function removerIndice(): void
    {
        Schema::table(self::TABELA, fn (Blueprint $table) => $table->dropUnique(self::INDICE));
    }

    public function test_indice_unico_tem_nome_curto_e_nenhum_indice_passa_do_limite_do_mysql(): void
    {
        $this->assertTrue(Schema::hasIndex(self::TABELA, self::COLUNAS, 'unique'));
        $this->assertTrue(Schema::hasIndex(self::TABELA, self::INDICE));

        foreach (Schema::getIndexes(self::TABELA) as $indice) {
            $this->assertLessThanOrEqual(64, strlen($indice['name']), "Índice {$indice['name']} passa do limite de 64 caracteres do MySQL.");
        }
    }

    public function test_o_mesmo_tipo_de_consentimento_nao_se_repete_para_a_mesma_matricula(): void
    {
        $matricula = Matricula::factory()->create();
        $tipo = TipoConsentimento::factory()->create();

        ConsentimentoMatricula::factory()->create(['matricula_id' => $matricula->id, 'tipo_consentimento_id' => $tipo->id]);

        $this->expectException(UniqueConstraintViolationException::class);
        ConsentimentoMatricula::factory()->create(['matricula_id' => $matricula->id, 'tipo_consentimento_id' => $tipo->id]);
    }

    public function test_migration_de_garantia_nao_faz_nada_quando_o_indice_ja_existe(): void
    {
        $this->garantirIndice();
        $this->garantirIndice();

        $this->assertTrue(Schema::hasIndex(self::TABELA, self::COLUNAS, 'unique'));
        $this->assertCount(1, array_filter(Schema::getIndexes(self::TABELA), fn (array $i): bool => $i['unique'] && $i['columns'] === self::COLUNAS));
    }

    public function test_migration_de_garantia_repoe_o_indice_perdido(): void
    {
        $this->removerIndice();
        $this->assertFalse(Schema::hasIndex(self::TABELA, self::COLUNAS, 'unique'));

        $this->garantirIndice();

        $this->assertTrue(Schema::hasIndex(self::TABELA, self::INDICE, 'unique'));

        $matricula = Matricula::factory()->create();
        $tipo = TipoConsentimento::factory()->create();
        ConsentimentoMatricula::factory()->create(['matricula_id' => $matricula->id, 'tipo_consentimento_id' => $tipo->id]);

        $this->expectException(UniqueConstraintViolationException::class);
        ConsentimentoMatricula::factory()->create(['matricula_id' => $matricula->id, 'tipo_consentimento_id' => $tipo->id]);
    }

    public function test_migration_de_garantia_para_com_a_lista_quando_ha_consentimentos_repetidos(): void
    {
        $this->removerIndice();

        $matricula = Matricula::factory()->create();
        $tipo = TipoConsentimento::factory()->create();
        ConsentimentoMatricula::factory()->count(2)->create(['matricula_id' => $matricula->id, 'tipo_consentimento_id' => $tipo->id]);

        try {
            $this->garantirIndice();
            $this->fail('Deveria parar: há consentimentos repetidos.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('repetidos', $e->getMessage());
            $this->assertStringContainsString("matrícula {$matricula->id} / tipo {$tipo->id} (2 linhas)", $e->getMessage());
        }

        $this->assertFalse(Schema::hasIndex(self::TABELA, self::COLUNAS, 'unique'), 'Nada é apagado nem indexado automaticamente.');
        $this->assertSame(2, ConsentimentoMatricula::count(), 'A migration não apaga consentimento.');
    }
}
