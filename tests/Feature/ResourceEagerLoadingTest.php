<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\AtendimentoEnfermagemResource;
use App\Filament\Resources\EmailLogs\EmailLogResource;
use App\Filament\Resources\FichaMedicaResource;
use App\Filament\Resources\OcorrenciaEscolarResource;
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
}
