<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Models\User;
use App\Services\CrmRelatoriosService;
use App\Services\FunilAnaliticoService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Painel de inteligência gerencial e relatórios estratégicos do CRM (Lote D2).
 *  - Motivos de perda e mapa de colégios concorrentes.
 *  - Desempenho por consultor (tempo de 1ª resposta, contatos, visitas e conversão).
 *  - Previsão de receita ponderada por etapa e confronto com dados históricos.
 */
class CrmRelatoriosPage extends Page
{
    use HasAjudaAction;
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static UnitEnum|string|null $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Relatórios & Inteligência';

    protected static ?string $title = 'Relatórios e Desempenho do CRM';

    protected static ?string $slug = 'crm/relatorios';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.crm-relatorios-page';

    public ?string $dataInicio = null;

    public ?string $dataFim = null;

    public ?int $consultorId = null;

    public string $periodoPredefinido = 'mes_atual';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin') || session('active_role') === 'super_admin') {
            return true;
        }

        return $user->can('View:CrmRelatoriosPage')
            || $user->can('page_CrmRelatoriosPage')
            || $user->hasRole('admin');
    }

    public function mount(): void
    {
        $this->definirPeriodo('mes_atual');
    }

    public function definirPeriodo(string $periodo): void
    {
        $this->periodoPredefinido = $periodo;

        match ($periodo) {
            'ultimos_30' => [
                $this->dataInicio = Carbon::today()->subDays(30)->toDateString(),
                $this->dataFim = Carbon::today()->toDateString(),
            ],
            'ultimos_90' => [
                $this->dataInicio = Carbon::today()->subDays(90)->toDateString(),
                $this->dataFim = Carbon::today()->toDateString(),
            ],
            'ano_atual' => [
                $this->dataInicio = Carbon::today()->startOfYear()->toDateString(),
                $this->dataFim = Carbon::today()->endOfYear()->toDateString(),
            ],
            default => [ // mes_atual
                $this->dataInicio = Carbon::today()->startOfMonth()->toDateString(),
                $this->dataFim = Carbon::today()->endOfMonth()->toDateString(),
            ],
        };
    }

    public function getConsultoresOptions(): Collection
    {
        return User::consultoresCrm()->pluck('name', 'id');
    }

    protected function getViewData(): array
    {
        $relatoriosService = app(CrmRelatoriosService::class);
        $funilService = app(FunilAnaliticoService::class);

        $filtros = [
            'data_inicio' => $this->dataInicio,
            'data_fim' => $this->dataFim,
            'usuario_id' => $this->consultorId,
        ];

        $motivosPerda = $relatoriosService->motivosPerda($filtros);
        $desempenhoConsultores = $relatoriosService->desempenhoConsultores($filtros);
        $previsaoReceita = $relatoriosService->previsaoReceita($filtros);
        $funilEtapas = $funilService->conversaoEtapaAEtapa($filtros);
        $temposMedios = $funilService->tempoMedioPorEtapa($filtros);

        return [
            'motivosPerda' => $motivosPerda,
            'desempenhoConsultores' => $desempenhoConsultores,
            'previsaoReceita' => $previsaoReceita,
            'funilEtapas' => $funilEtapas,
            'temposMedios' => $temposMedios,
            'consultores' => $this->getConsultoresOptions(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Relatórios & Inteligência do Funil')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData(['content' => $this->getHelpContent()]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        $podeVer = $user?->can('View:CrmRelatoriosPage') ?? true;

        $html = '<div class="help-modal">';
        $html .= '<h3>📊 Relatórios e Desempenho do Funil</h3>';
        $html .= '<p>Esta página centraliza a visão gerencial e estratégica do processo de captação e admissão de novos alunos.</p>';

        $html .= '<div class="help-sec">';
        $html .= '<h4>🎯 Funcionalidades Disponíveis</h4>';
        $html .= '<ul class="help-list">';
        $html .= '<li class="help-item"><span class="help-item-emoji">⏳</span><div><strong>Tempo Médio e Conversão Etapa a Etapa:</strong> Identifique gargalos e tempo de permanência dos leads em cada fase do funil.</div></li>';
        $html .= '<li class="help-item"><span class="help-item-emoji">💰</span><div><strong>Previsão de Receita Ponderada:</strong> Projeção financeira baseada no valor estimado dos leads e na probabilidade de fechamento por etapa, confrontada com a taxa histórica real.</div></li>';
        $html .= '<li class="help-item"><span class="help-item-emoji">👥</span><div><strong>Desempenho por Consultor:</strong> Indicadores de 1ª resposta, contatos humanos, visitas realizadas e conversão por atendente.</div></li>';
        $html .= '<li class="help-item"><span class="help-item-emoji">🛡️</span><div><strong>Inteligência Competitiva & Perdas:</strong> Mapeamento das escolas concorrentes e fatores decisivos de descarte.</div></li>';
        $html .= '</ul>';
        $html .= '</div>';

        $html .= '<div class="help-callout help-tip"><span>💡</span><p><strong>Dica:</strong> Use os filtros rápidos de período (Mês Atual, 30 Dias, Ano Atual) para analisar tendências e sazonalidade das matrículas.</p></div>';
        $html .= '</div>';

        return $html;
    }
}
