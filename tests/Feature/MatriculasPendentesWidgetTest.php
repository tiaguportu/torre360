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
    public function deve_contar_corretamente_pendencias_das_matriculas()
    {
        // Cria aluno sem responsável (pendência de responsável)
        $aluno1 = Pessoa::factory()->create(['cpf' => '12345678901', 'data_nascimento' => '2010-01-01']);
        Matricula::factory()->create([
            'pessoa_id' => $aluno1->id,
            'situacao' => 'ativa',
        ]);

        // Cria aluno com responsável (sem pendência de responsável)
        $aluno2 = Pessoa::factory()->create(['cpf' => '98765432109', 'data_nascimento' => '2011-02-02']);
        $responsavel = Pessoa::factory()->create();
        $aluno2->responsaveis()->attach($responsavel->id);
        Matricula::factory()->create([
            'pessoa_id' => $aluno2->id,
            'situacao' => 'ativa',
        ]);

        // Cria aluno sem CPF (pendência de cadastro)
        $alunoSemCpf = Pessoa::factory()->create(['cpf' => null, 'data_nascimento' => '2012-03-03']);
        $alunoSemCpf->responsaveis()->attach($responsavel->id);
        Matricula::factory()->create([
            'pessoa_id' => $alunoSemCpf->id,
            'situacao' => 'ativa',
        ]);

        $widget = new MatriculasPendentesWidget;

        $reflection = new \ReflectionClass(MatriculasPendentesWidget::class);
        $method = $reflection->getMethod('getStats');
        $method->setAccessible(true);
        $stats = $method->invoke($widget);

        // A primeira estatística deve ser "Pendência de Responsáveis" com valor 1
        $this->assertEquals(1, $stats[0]->getValue());

        // A terceira estatística deve ser "Pendência de Cadastro" com valor 1
        $this->assertEquals(1, $stats[2]->getValue());
    }

    /** @test */
    public function deve_gerar_url_com_filtro_correto_no_widget()
    {
        $widget = new MatriculasPendentesWidget;

        $reflection = new \ReflectionClass(MatriculasPendentesWidget::class);
        $method = $reflection->getMethod('getStats');
        $method->setAccessible(true);
        $stats = $method->invoke($widget);

        // Verifica que a URL do primeiro card contém o filtro 'sem_responsavel' e o de situação ativa
        $url0 = urldecode($stats[0]->getUrl());
        $this->assertStringContainsString('sem_responsavel', $url0);
        $this->assertStringContainsString('filters[situacao][value]=ativa', $url0);

        // Verifica que a URL do segundo card contém o filtro 'documentos_pendentes' e o de situação ativa
        $url1 = urldecode($stats[1]->getUrl());
        $this->assertStringContainsString('documentos_pendentes', $url1);
        $this->assertStringContainsString('filters[situacao][value]=ativa', $url1);

        // Verifica que a URL do terceiro card contém o filtro 'dados_pendentes' e o de situação ativa
        $url2 = urldecode($stats[2]->getUrl());
        $this->assertStringContainsString('dados_pendentes', $url2);
        $this->assertStringContainsString('filters[situacao][value]=ativa', $url2);
    }
}
