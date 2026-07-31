<?php

namespace Tests\Feature;

use App\Enums\CorRaca;
use App\Models\Pessoa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CorRacaCastTest extends TestCase
{
    use RefreshDatabase;

    public function test_converte_valores_legados_e_codigos_inep_para_enum_cor_raca(): void
    {
        // 1. Inserção direta no banco simulando dados legados (ex: 'amarela', 'branca', 'parda')
        DB::table('pessoa')->insert([
            'id' => 999,
            'nome' => 'Pessoa Teste Amarela',
            'cor_raca' => 'amarela',
        ]);

        $pessoa = Pessoa::find(999);
        $this->assertInstanceOf(CorRaca::class, $pessoa->cor_raca);
        $this->assertEquals(CorRaca::AMARELA, $pessoa->cor_raca);
        $this->assertEquals('4', $pessoa->cor_raca->value);

        // 2. Testando atribuição do enum e salvamento no banco
        $pessoa->cor_raca = CorRaca::BRANCA;
        $pessoa->save();

        $rawCorRaca = DB::table('pessoa')->where('id', 999)->value('cor_raca');
        $this->assertEquals('1', $rawCorRaca);
    }
}
