<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\IndicadorMetas\IndicadorMetaResource;
use App\Models\Unidade;
use App\Services\Painel\PainelDirecaoService;
use App\Support\HelpContent;
use App\Support\Pendencias\FilaDePendencias;
use App\Support\PermissaoAcao;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Painel da Direção (Onda 45): a saúde da escola em uma tela. Cada cartão é um indicador com uma definição só (a classe dele), valor
 * atual, variação contra 30 dias atrás, meta e semáforo. Cada pessoa vê apenas os indicadores cuja permissão tem: financeiro não
 * aparece para quem não vê financeiro.
 */
class PainelDirecao extends Page
{
    use HasAjudaAction;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static UnitEnum|string|null $navigationGroup = 'Relatórios';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Painel da Direção';

    protected static ?string $navigationLabel = 'Painel da Direção';

    protected static ?string $slug = 'painel-da-direcao';

    protected string $view = 'filament.pages.painel-direcao';

    #[Url(as: 'unidade')]
    public ?int $unidadeId = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:PainelDirecao');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    /**
     * @return array<int, string>
     */
    public function unidades(): array
    {
        abort_unless(static::canAccess(), 403);

        return Unidade::query()->orderBy('nome')->pluck('nome', 'id')->all();
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function cartoes(): array
    {
        abort_unless(static::canAccess(), 403);

        return app(PainelDirecaoService::class)->cartoes(auth()->user(), (int) $this->unidadeId);
    }

    /**
     * @return list<FilaDePendencias>
     */
    public function atencaoHoje(): array
    {
        abort_unless(static::canAccess(), 403);

        return app(PainelDirecaoService::class)->atencaoHoje(auth()->user());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('atualizar')
                ->label('Atualizar')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);

                    app(PainelDirecaoService::class)->esquecer();
                }),
            Action::make('fotografar')
                ->label('Guardar fotografia de hoje')
                ->icon('heroicon-o-camera')
                ->color('gray')
                ->authorize(PermissaoAcao::qualquer('Fotografar:PainelDirecao'))
                ->requiresConfirmation()
                ->modalDescription('A rotina já guarda a fotografia todo dia de manhã. Use este botão para registrar os números de agora (substitui a fotografia de hoje).')
                ->action(function (): void {
                    $gravadas = app(PainelDirecaoService::class)->fotografar();

                    Notification::make()->title("{$gravadas} fotografia(s) guardada(s)")->success()->send();
                }),
            Action::make('metas')
                ->label('Metas')
                ->icon('heroicon-o-flag')
                ->color('gray')
                ->visible(fn (): bool => (bool) auth()->user()?->can('ViewAny:IndicadorMeta'))
                ->url(fn (): string => IndicadorMetaResource::getUrl('index')),
            $this->ajudaAction('Painel da Direção', HelpContent::make('🧭', 'Painel da Direção', 'A saúde da escola em uma tela, com meta, variação e semáforo.')
                ->passos('🔁 Como ler', [
                    'Cada cartão mostra o valor de agora, a variação contra 30 dias atrás (seta verde é melhora, vermelha é piora) e a meta, quando existe.',
                    'O semáforo compara com a meta do ano: verde atingiu, amarelo está dentro da tolerância, vermelho passou dela.',
                    'Passe o mouse (ou toque) no título para ler a fórmula do número. Clique no cartão para abrir a tela de detalhe.',
                    '"Atenção hoje" lista as filas da sua Minha Mesa que têm algo atrasado.',
                ])
                ->secao('📌 Bom saber', [
                    ['🔒', 'Cada um vê o que pode', 'Os indicadores financeiros só aparecem para quem tem acesso às telas financeiras.'],
                    ['📸', 'Comparação', 'Vem das fotografias diárias. Num painel novo, a variação aparece depois de alguns dias.'],
                    ['🏫', 'Unidade', 'O filtro vale para os indicadores que existem por unidade; os outros mostram a escola toda e avisam.'],
                    ['🧮', 'Uma definição só', 'Os números são calculados pelo mesmo código dos relatórios e telas de origem, para não haver dois valores para a mesma coisa.'],
                ])),
        ];
    }
}
