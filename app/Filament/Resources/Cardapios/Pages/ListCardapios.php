<?php

declare(strict_types=1);

namespace App\Filament\Resources\Cardapios\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Cardapios\CardapioResource;
use App\Models\Cardapio;
use App\Models\Unidade;
use App\Services\CardapioService;
use App\Support\HelpContent;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use InvalidArgumentException;

class ListCardapios extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = CardapioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nova refeição'),
            Action::make('duplicarSemana')
                ->label('Duplicar semana')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->modalHeading('Duplicar uma semana do cardápio')
                ->modalDescription('Copia as refeições da semana de origem para a de destino como rascunho. O que já existe no destino não é alterado.')
                ->schema([
                    DatePicker::make('origem')
                        ->label('Qualquer dia da semana de origem')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->default(now()->toDateString())
                        ->required(),
                    DatePicker::make('destino')
                        ->label('Qualquer dia da semana de destino')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->default(now()->addWeek()->toDateString())
                        ->required(),
                    Select::make('unidade_id')
                        ->label('Unidade')
                        ->options(fn (): array => Unidade::query()->orderBy('nome')->pluck('nome', 'id')->all())
                        ->placeholder('Todas as unidades (cardápio geral)'),
                ])
                ->visible(fn (): bool => (bool) auth()->user()?->can('Create:Cardapio'))
                ->action(function (array $data): void {
                    try {
                        $criadas = app(CardapioService::class)->duplicarSemana(
                            Carbon::parse($data['origem']),
                            Carbon::parse($data['destino']),
                            $data['unidade_id'] ?? null,
                            auth()->user(),
                        );
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title('Não foi possível duplicar')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()
                        ->title($criadas > 0 ? "{$criadas} refeição(ões) copiada(s) como rascunho" : 'Nada foi copiado')
                        ->body($criadas > 0 ? 'Revise e publique quando estiver pronto.' : 'A semana de origem está vazia ou o destino já tem todas as refeições.')
                        ->{$criadas > 0 ? 'success' : 'warning'}()
                        ->send();
                }),
            Action::make('restricoes')
                ->label('Restrições alimentares do dia')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->url(fn (): string => CardapioResource::getUrl('restricoes'))
                ->visible(fn (): bool => (bool) auth()->user()?->can('verAlergicos', Cardapio::class)),
            $this->ajudaAction('Cardápio', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🍽️', 'Cardápio', 'Refeições da escola por data, com os alérgenos declarados e publicação no Portal da Família.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja as refeições por data. Por padrão aparecem a semana atual e as duas próximas; ajuste o filtro de período.'],
                $user->can('Create:Cardapio') ? ['🆕', 'Nova refeição', 'Informe a data, a refeição, o cardápio e marque os alérgenos que ela contém.'] : null,
                $user->can('Create:Cardapio') ? ['📑', 'Duplicar semana', 'Copia uma semana inteira como rascunho, para só ajustar o que muda.'] : null,
                $user->can('Publicar:Cardapio') ? ['👁️', 'Publicar', 'Só o que está publicado aparece para as famílias no Portal.'] : null,
                $user->can('VerAlergicos:Cardapio') ? ['⚠️', 'Restrições alimentares do dia', 'Mostra, por refeição, os alunos com restrição que conflita com os alérgenos declarados.'] : null,
            ])
            ->alerta('Marcar os alérgenos é responsabilidade da escola: o sistema cruza só o que está declarado no cardápio com o que a família registrou na ficha médica, e o resultado é um apoio, não uma garantia.')
            ->dica('Cada família vê no Portal um alerta nas refeições que conflitam com a restrição do próprio filho.');
    }
}
