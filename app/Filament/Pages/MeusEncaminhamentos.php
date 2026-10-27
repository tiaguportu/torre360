<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\StatusEncaminhamento;
use App\Enums\StatusReuniao;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Reunioes\ReuniaoResource;
use App\Models\ReuniaoEncaminhamento;
use App\Services\ReuniaoService;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Meus encaminhamentos (Ondas 27 e 32): o que ficou combinado nas reuniões, com responsável e prazo. Cada pessoa acompanha os seus; a
 * coordenação, que vê as reuniões da equipe, enxerga também os dos outros, menos os de reuniões sigilosas.
 */
class MeusEncaminhamentos extends Page implements HasTable
{
    use HasAjudaAction;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static UnitEnum|string|null $navigationGroup = 'Acadêmico';

    protected static ?int $navigationSort = 41;

    protected static ?string $title = 'Meus encaminhamentos';

    protected static ?string $navigationLabel = 'Meus encaminhamentos';

    protected static ?string $slug = 'reunioes/meus-encaminhamentos';

    protected string $view = 'filament.pages.meus-encaminhamentos';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('ViewAny:Reuniao');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Meus encaminhamentos', HelpContent::make('📌', 'Meus encaminhamentos', 'O que ficou combinado nas reuniões da escola, com responsável e prazo.')
                ->secao('🎯 O que você pode fazer?', [
                    ['📋', 'Acompanhar', 'Por padrão aparecem os seus encaminhamentos em aberto. Tire o filtro "Somente os meus" para ver os das reuniões que você acompanha.'],
                    ['✅', 'Concluir', 'Ao terminar, conclua e conte em uma linha como foi resolvido. Quem organizou a reunião acompanha.'],
                    ['⏰', 'Prazos', 'O encaminhamento vencido fica em vermelho e avisa você e a coordenação uma vez.'],
                ])
                ->dica('As reuniões sigilosas (devolutiva individual) só mostram o encaminhamento para o responsável e para quem organizou.')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->consulta())
            ->defaultSort('prazo')
            ->columns([
                TextColumn::make('descricao')->label('O que foi combinado')->wrap()->weight('bold')->searchable(),
                TextColumn::make('reuniao.titulo')
                    ->label('Reunião')
                    ->state(fn (ReuniaoEncaminhamento $record): string => $record->reuniao->ehSigilosa() ? $record->reuniao->tipo->getLabel() : $record->reuniao->titulo)
                    ->description(fn (ReuniaoEncaminhamento $record): string => $record->reuniao->inicio_em->format('d/m/Y')),
                TextColumn::make('matricula.pessoa.nome')->label('Aluno')->placeholder('—')->toggleable(),
                TextColumn::make('responsavel.name')->label('Responsável')->placeholder('Sem responsável'),
                TextColumn::make('prazo')
                    ->label('Prazo')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('Sem prazo')
                    ->color(fn (ReuniaoEncaminhamento $record): ?string => $record->estaVencido() ? 'danger' : null)
                    ->description(fn (ReuniaoEncaminhamento $record): ?string => $record->estaVencido() ? 'Vencido' : null),
                TextColumn::make('status')->label('Situação')->badge(),
            ])
            ->filters([
                Filter::make('meus')
                    ->label('Somente os meus')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->where('responsavel_user_id', auth()->id())),
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options(StatusEncaminhamento::class)
                    ->default(StatusEncaminhamento::Aberto->value),
                Filter::make('vencidos')
                    ->label('Somente os vencidos')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->vencidos()),
            ])
            ->recordActions([
                Action::make('concluir')
                    ->label('Concluir')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ReuniaoEncaminhamento $record): bool => $record->estaAberto() && (bool) auth()->user()?->can('update', $record))
                    ->modalHeading('Concluir o encaminhamento')
                    ->schema([
                        Textarea::make('observacao')->label('Como foi resolvido (opcional)')->rows(3)->maxLength(1000),
                    ])
                    ->action(function (ReuniaoEncaminhamento $record, array $data): void {
                        abort_unless($record->estaAberto() && auth()->user()?->can('update', $record), 403);

                        try {
                            app(ReuniaoService::class)->concluirEncaminhamento($record, auth()->user(), $data['observacao'] ?? null);
                        } catch (\DomainException $e) {
                            Notification::make()->title('Não foi possível concluir')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Encaminhamento concluído')->success()->send();
                    }),
                Action::make('abrir_reuniao')
                    ->label('Abrir reunião')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->visible(fn (ReuniaoEncaminhamento $record): bool => (bool) auth()->user()?->can('view', $record->reuniao))
                    ->url(fn (ReuniaoEncaminhamento $record): string => ReuniaoResource::getUrl('view', ['record' => $record->reuniao_id])),
            ])
            ->emptyStateHeading('Nenhum encaminhamento')
            ->emptyStateDescription('Quando uma reunião deixar um combinado para você, ele aparece aqui.')
            ->stackedOnMobile();
    }

    /**
     * Os encaminhamentos em que a pessoa é responsável e os das reuniões que ela pode ver.
     *
     * @return Builder<ReuniaoEncaminhamento>
     */
    private function consulta(): Builder
    {
        $usuario = auth()->user();

        return ReuniaoEncaminhamento::query()
            ->with(['reuniao', 'responsavel', 'matricula.pessoa'])
            ->whereHas('reuniao', fn (Builder $q) => $q->where('status', '!=', StatusReuniao::Cancelada->value))
            ->where(fn (Builder $q) => $q
                ->where('responsavel_user_id', $usuario?->id)
                ->orWhereHas('reuniao', fn (Builder $r) => $r->visiveisPara($usuario)));
    }
}
