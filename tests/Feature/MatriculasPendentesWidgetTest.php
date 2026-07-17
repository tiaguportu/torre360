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

    /** @test */
    public function deve_gerar_url_com_filtro_correto_no_widget()
    {
        $widget = new MatriculasPendentesWidget;

        $reflection = new \ReflectionClass(MatriculasPendentesWidget::class);
        $method = $reflection->getMethod('getStats');
        $method->setAccessible(true);
        $stats = $method->invoke($widget);

        // Verifica que a URL do primeiro card contém o filtro 'sem_responsavel'
        $url0 = urldecode($stats[0]->getUrl());
        $this->assertStringContainsString('sem_responsavel', $url0);

        // Verifica que a URL do segundo card contém o filtro 'documentos_pendentes'
        $url1 = urldecode($stats[1]->getUrl());
        $this->assertStringContainsString('documentos_pendentes', $url1);
    }
}
