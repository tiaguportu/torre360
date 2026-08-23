<?php

namespace Tests\Feature;

use App\Filament\Widgets\AlunosPorTurmaChart;
use App\Filament\Widgets\ContratosPendentesWidget;
use App\Filament\Widgets\CrmFollowUpCalendarWidget;
use App\Filament\Widgets\CronogramaCalendarWidget;
use App\Filament\Widgets\FrequenciaPendenteWidget;
use App\Filament\Widgets\InteressadoOrigemChart;
use App\Filament\Widgets\InteressadoStatusChart;
use App\Filament\Widgets\MatriculasPendentesWidget;
use App\Filament\Widgets\PreceptoriaCalendarWidget;
use App\Filament\Widgets\PreceptoriaSchedulingWidget;
use App\Filament\Widgets\QuestionariosPendentes;
use App\Filament\Widgets\QueueSupervisorWidget;
use App\Filament\Widgets\StatsOverview;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Tests\TestCase;

class ShieldWidgetsTest extends TestCase
{
    public function test_shield_discovers_all_application_widgets(): void
    {
        $widgets = FilamentShield::getWidgets();

        $this->assertIsArray($widgets);

        $expectedWidgets = [
            AlunosPorTurmaChart::class,
            ContratosPendentesWidget::class,
            CrmFollowUpCalendarWidget::class,
            CronogramaCalendarWidget::class,
            FrequenciaPendenteWidget::class,
            InteressadoOrigemChart::class,
            InteressadoStatusChart::class,
            MatriculasPendentesWidget::class,
            PreceptoriaCalendarWidget::class,
            PreceptoriaSchedulingWidget::class,
            QuestionariosPendentes::class,
            QueueSupervisorWidget::class,
            StatsOverview::class,
        ];

        foreach ($expectedWidgets as $widgetClass) {
            $this->assertArrayHasKey($widgetClass, $widgets, "Widget {$widgetClass} não foi descoberto pelo Shield.");
            $permissions = $widgets[$widgetClass]['permissions'] ?? [];
            $this->assertNotEmpty($permissions, "Widget {$widgetClass} não possui permissões mapeadas.");

            $permissionKey = array_key_first($permissions);
            $expectedKey = 'View:'.class_basename($widgetClass);
            $this->assertEquals($expectedKey, $permissionKey, "A chave de permissão gerada para {$widgetClass} deveria ser {$expectedKey}");
        }
    }
}
