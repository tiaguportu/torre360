<?php

namespace Tests\Feature;

use App\Filament\Widgets\MatriculasPendentesWidget;
use App\Models\Matricula;
use App\Models\Pessoa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatriculasPendentesWidgetTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function deve_contar_corretamente_matriculas_sem_responsavel()
    {
        // Cria aluno sem responsável
        $aluno1 = Pessoa::factory()->create();
        Matricula::factory()->create([
            'pessoa_id' => $aluno1->id,
            'situacao' => 'ativa',
        ]);

        // Cria aluno com responsável
        $aluno2 = Pessoa::factory()->create();
        $responsavel = Pessoa::factory()->create();
        $aluno2->responsaveis()->attach($responsavel->id);
        Matricula::factory()->create([
            'pessoa_id' => $aluno2->id,
            'situacao' => 'ativa',
        ]);

        $widget = new MatriculasPendentesWidget;

        $reflection = new \ReflectionClass(MatriculasPendentesWidget::class);
        $method = $reflection->getMethod('getStats');
        $method->setAccessible(true);
        $stats = $method->invoke($widget);

        // A primeira estatística deve ser "Pendência de Responsáveis" com valor 1
        $this->assertEquals(1, $stats[0]->getValue());
    }
}
