<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\AtendimentoEnfermagemResource;
use App\Filament\Resources\EmailLogs\EmailLogResource;
use App\Filament\Resources\FichaMedicaResource;
use App\Filament\Resources\MaterialAulas\MaterialAulaResource;
use App\Filament\Resources\OcorrenciaEscolarResource;
use App\Filament\Resources\PlanoAulas\PlanoAulaResource;
use App\Filament\Resources\ResponsavelFinanceiros\ResponsavelFinanceiroResource;
use App\Filament\Resources\TransacaoBancarias\TransacaoBancariaResource;
use App\Filament\Resources\Turmas\TurmaResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceEagerLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ocorrencia_escolar_resource_aplica_eager_loading_para_evitar_n_plus_one(): void
    {
        $query = OcorrenciaEscolarResource::getEloquentQuery();
        $eagerLoads = array_keys($query->getEagerLoads());

        $this->assertContains('matricula.pessoa', $eagerLoads);
        $this->assertContains('matricula.turma', $eagerLoads);
        $this->assertContains('tipoOcorrencia', $eagerLoads);
        $this->assertContains('registradoPor', $eagerLoads);
    }

    public function test_atendimento_enfermagem_resource_aplica_eager_loading_para_evitar_n_plus_one(): void
    {
        $query = AtendimentoEnfermagemResource::getEloquentQuery();
        $eagerLoads = array_keys($query->getEagerLoads());

        $this->assertContains('pessoa', $eagerLoads);
        $this->assertContains('atendidoPor', $eagerLoads);
    }

    public function test_ficha_medica_resource_aplica_eager_loading_para_evitar_n_plus_one(): void
    {
        $query = FichaMedicaResource::getEloquentQuery();
        $eagerLoads = array_keys($query->getEagerLoads());

        $this->assertContains('pessoa', $eagerLoads);
    }

    public function test_email_log_resource_aplica_eager_loading_para_evitar_n_plus_one(): void
    {
        $query = EmailLogResource::getEloquentQuery();
        $eagerLoads = array_keys($query->getEagerLoads());

        $this->assertContains('user', $eagerLoads);
    }

    public function test_transacao_bancaria_resource_aplica_eager_loading_para_evitar_n_plus_one(): void
    {
        $query = TransacaoBancariaResource::getEloquentQuery();
        $eagerLoads = array_keys($query->getEagerLoads());

        $this->assertContains('banco', $eagerLoads);
        $this->assertContains('fatura.contrato.matricula.pessoa', $eagerLoads);
        $this->assertContains('planoConta', $eagerLoads);
        $this->assertContains('centroCusto', $eagerLoads);
        $this->assertContains('fornecedor', $eagerLoads);
    }

    public function test_turma_resource_aplica_eager_loading_para_evitar_n_plus_one(): void
    {
        $query = TurmaResource::getEloquentQuery();
        $eagerLoads = array_keys($query->getEagerLoads());

        $this->assertContains('serie.curso', $eagerLoads);
        $this->assertContains('turno', $eagerLoads);
        $this->assertContains('periodoLetivo', $eagerLoads);
        $this->assertContains('professorConselheiro', $eagerLoads);
        $this->assertContains('etapaEnsinoAgregada', $eagerLoads);
        $this->assertContains('etapaEnsino', $eagerLoads);
    }

    public function test_responsavel_financeiro_resource_aplica_eager_loading_para_evitar_n_plus_one(): void
    {
        $query = ResponsavelFinanceiroResource::getEloquentQuery();
        $eagerLoads = array_keys($query->getEagerLoads());

        $this->assertContains('contrato.matricula.pessoa', $eagerLoads);
        $this->assertContains('pessoa', $eagerLoads);
    }

    public function test_material_aula_resource_aplica_eager_loading_para_evitar_n_plus_one(): void
    {
        $query = MaterialAulaResource::getEloquentQuery();
        $eagerLoads = array_keys($query->getEagerLoads());

        $this->assertContains('turma', $eagerLoads);
        $this->assertContains('disciplina', $eagerLoads);
        $this->assertContains('professor', $eagerLoads);
    }

    public function test_plano_aula_resource_aplica_eager_loading_para_evitar_n_plus_one(): void
    {
        $query = PlanoAulaResource::getEloquentQuery();
        $eagerLoads = array_keys($query->getEagerLoads());

        $this->assertContains('turma', $eagerLoads);
        $this->assertContains('disciplina', $eagerLoads);
        $this->assertContains('professor', $eagerLoads);
    }
}
