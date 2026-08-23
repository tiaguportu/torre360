<?php

namespace Tests\Feature;

use App\Filament\Resources\Alunos\AlunoResource;
use App\Filament\Resources\ResponsavelFinanceiros\ResponsavelFinanceiroResource;
use Tests\TestCase;

class NavigationVisibilityTest extends TestCase
{
    public function test_aluno_resource_nao_deve_aparecer_na_navegacao(): void
    {
        $this->assertFalse(AlunoResource::shouldRegisterNavigation());
    }

    public function test_responsavel_financeiro_resource_nao_deve_aparecer_na_navegacao(): void
    {
        $this->assertFalse(ResponsavelFinanceiroResource::shouldRegisterNavigation());
    }
}
