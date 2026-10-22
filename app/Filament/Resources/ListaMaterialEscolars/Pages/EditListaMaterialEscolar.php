<?php

declare(strict_types=1);

namespace App\Filament\Resources\ListaMaterialEscolars\Pages;

use App\Exceptions\ListaMaterialException;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ListaMaterialEscolars\ListaMaterialEscolarResource;
use App\Models\ListaMaterialEscolar;
use App\Services\ListaMaterialService;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditListaMaterialEscolar extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = ListaMaterialEscolarResource::class;

    private function lista(): ListaMaterialEscolar
    {
        /** @var ListaMaterialEscolar $lista */
        $lista = $this->getRecord();

        return $lista;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publicar')
                ->label('Publicar para as famílias')
                ->icon('heroicon-o-megaphone')
                ->color('success')
                ->visible(fn (): bool => ! $this->lista()->estaPublicada() && (bool) auth()->user()?->can('publicar', $this->lista()))
                ->requiresConfirmation()
                ->modalHeading('Publicar a lista de material?')
                ->modalDescription('As famílias dos alunos desta série passam a ver a lista no Portal. Depois de publicada, a lista só muda se você a voltar para rascunho.')
                ->action(function (): void {
                    try {
                        app(ListaMaterialService::class)->publicar($this->lista(), auth()->user());
                    } catch (ListaMaterialException $e) {
                        Notification::make()->title('Não foi possível publicar')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    }

                    Notification::make()->title('Lista publicada')->success()->send();
                    $this->redirect(ListaMaterialEscolarResource::getUrl('edit', ['record' => $this->lista()]));
                }),
            Action::make('despublicar')
                ->label('Voltar para rascunho')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (): bool => $this->lista()->estaPublicada() && (bool) auth()->user()?->can('publicar', $this->lista()))
                ->requiresConfirmation()
                ->modalHeading('Voltar a lista para rascunho?')
                ->modalDescription('As famílias deixam de ver a lista até você publicá-la de novo. Avise quem já comprou o material se houver mudança.')
                ->action(function (): void {
                    try {
                        app(ListaMaterialService::class)->despublicar($this->lista(), auth()->user());
                    } catch (ListaMaterialException $e) {
                        Notification::make()->title('Não foi possível alterar')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Lista em rascunho')->success()->send();
                    $this->redirect(ListaMaterialEscolarResource::getUrl('edit', ['record' => $this->lista()]));
                }),
            Action::make('copiar')
                ->label('Copiar itens de outra lista')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->visible(fn (): bool => ! $this->lista()->estaPublicada() && (bool) auth()->user()?->can('editarItens', $this->lista()))
                ->modalHeading('Copiar os itens de outra lista')
                ->modalDescription('A lista atual precisa estar vazia. Itens que a trava de uso coletivo não aceita hoje ficam de fora, com o motivo.')
                ->schema([
                    Select::make('origem_id')
                        ->label('Lista de origem')
                        ->options(fn (): array => ListaMaterialEscolar::query()
                            ->whereKeyNot($this->lista()->id)
                            ->has('itens')
                            ->with(['serie', 'periodoLetivo'])
                            ->withCount('itens')
                            ->get()
                            ->sortByDesc('periodo_letivo_id')
                            ->mapWithKeys(fn (ListaMaterialEscolar $l): array => [$l->id => "{$l->serie?->nome} — {$l->periodoLetivo?->nome} ({$l->itens_count} itens)"])
                            ->all())
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        $resultado = app(ListaMaterialService::class)->copiar(ListaMaterialEscolar::query()->findOrFail($data['origem_id']), $this->lista());
                    } catch (ListaMaterialException $e) {
                        Notification::make()->title('Não foi possível copiar')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    $aviso = Notification::make()->title("{$resultado['copiados']} item(ns) copiado(s)");

                    if ($resultado['ignorados'] !== []) {
                        $aviso->body('Ficaram de fora: '.implode(' | ', $resultado['ignorados']))->warning()->persistent();
                    } else {
                        $aviso->success();
                    }

                    $aviso->send();
                    $this->redirect(ListaMaterialEscolarResource::getUrl('edit', ['record' => $this->lista()]));
                }),
            Action::make('pdf')
                ->label('Baixar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn (): string => route('lista-material.pdf', ['lista' => $this->lista()->id]))
                ->openUrlInNewTab(),
            DeleteAction::make()->visible(fn (): bool => ! $this->lista()->estaPublicada() && (bool) auth()->user()?->can('delete', $this->lista())),
            $this->ajudaAction('Lista de material', HelpContent::make('🎒', 'Lista de material', 'Monte os itens, publique e acompanhe pelo Portal da Família.')
                ->passos('Passo a passo', [
                    'Na aba "Itens", clique em "Adicionar item" e descreva o que o aluno precisa, com a quantidade.',
                    'Se o item pode ser de uso coletivo (por exemplo, resma de papel), o sistema pede a confirmação de que é de uso individual do aluno. Itens claramente coletivos não entram.',
                    'Clique em "Publicar para as famílias". A lista aparece no Portal para os alunos da série neste período.',
                    'Para corrigir uma lista publicada, use "Voltar para rascunho", ajuste e publique de novo.',
                ])
                ->alerta('Não cite marca, loja nem kit fechado: é venda casada (CDC, art. 39). Material de limpeza, papel higiênico, giz, pincel de quadro e toner são custo da escola (Lei nº 9.870/1999, art. 1º, § 7º).')),
        ];
    }
}
