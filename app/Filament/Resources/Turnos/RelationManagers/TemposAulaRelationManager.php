<?php

declare(strict_types=1);

namespace App\Filament\Resources\Turnos\RelationManagers;

use App\Models\TempoAula;
use App\Services\TempoAulaService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules\Unique;

/**
 * Tempos de aula do turno (Onda 64): o "sinal" da escola. A grade assistida só sabe onde cabe cada aula com eles.
 */
class TemposAulaRelationManager extends RelationManager
{
    protected static string $relationship = 'temposAula';

    protected static ?string $title = 'Tempos de aula';

    protected static ?string $modelLabel = 'tempo de aula';

    protected static ?string $pluralModelLabel = 'tempos de aula';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ordem')
                    ->label('Número do tempo')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(20)
                    ->default(fn (): int => ((int) $this->getOwnerRecord()->temposAula()->reorder()->max('ordem')) + 1)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('turno_id', $this->getOwnerRecord()->getKey()))
                    ->required(),
                TimePicker::make('hora_inicio')
                    ->label('Início')
                    ->seconds(false)
                    ->required(),
                TimePicker::make('hora_fim')
                    ->label('Fim')
                    ->seconds(false)
                    ->after('hora_inicio')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ordem')
            ->defaultSort('ordem')
            ->columns([
                TextColumn::make('ordem')
                    ->label('Tempo')
                    ->formatStateUsing(fn (int $state): string => "{$state}º"),
                TextColumn::make('hora_inicio')
                    ->label('Início')
                    ->time('H:i'),
                TextColumn::make('hora_fim')
                    ->label('Fim')
                    ->time('H:i'),
                TextColumn::make('duracao')
                    ->label('Duração')
                    ->state(fn (TempoAula $record): string => Carbon::createFromFormat('H:i', $record->inicio())->diffInMinutes(Carbon::createFromFormat('H:i', $record->fim())).' min'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Novo tempo')
                    ->before(function (CreateAction $action, array $data): void {
                        $this->validarSobreposicao($action, $data);
                    }),
                $this->gerarAction(),
            ])
            ->recordActions([
                EditAction::make()
                    ->before(function (EditAction $action, array $data, TempoAula $record): void {
                        $this->validarSobreposicao($action, $data, $record);
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->paginated(false)
            ->stackedOnMobile();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validarSobreposicao(CreateAction|EditAction $action, array $data, ?TempoAula $record = null): void
    {
        $outro = app(TempoAulaService::class)->sobreposicao((int) $this->getOwnerRecord()->getKey(), (string) $data['hora_inicio'], (string) $data['hora_fim'], $record?->id);

        if (! $outro) {
            return;
        }

        Notification::make()
            ->danger()
            ->title('Tempos se cruzando')
            ->body("Esse horário cruza com o {$outro->rotulo()}.")
            ->persistent()
            ->send();

        $action->halt();
    }

    /**
     * Cria todos os tempos do turno de uma vez: horário de início, quantidade, duração e um intervalo.
     */
    private function gerarAction(): Action
    {
        return Action::make('gerar')
            ->label('Gerar tempos')
            ->icon('heroicon-o-sparkles')
            ->color('gray')
            ->modalHeading('Gerar os tempos de aula do turno')
            ->modalDescription('Informe o primeiro horário, quantos tempos e a duração de cada um. O intervalo (recreio) é o espaço entre dois tempos.')
            ->schema([
                TimePicker::make('hora_inicio')
                    ->label('Primeira aula começa às')
                    ->seconds(false)
                    ->default(fn () => substr((string) $this->getOwnerRecord()->hora_inicio, 0, 5) ?: '07:30')
                    ->required(),
                TextInput::make('quantidade')
                    ->label('Quantidade de tempos')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(12)
                    ->default(5)
                    ->required(),
                TextInput::make('duracao')
                    ->label('Duração de cada tempo (minutos)')
                    ->numeric()
                    ->minValue(10)
                    ->maxValue(180)
                    ->default(50)
                    ->required(),
                TextInput::make('intervalo_apos')
                    ->label('Intervalo depois de qual tempo? (opcional)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(12),
                TextInput::make('intervalo_minutos')
                    ->label('Duração do intervalo (minutos)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(120)
                    ->default(20),
                Toggle::make('substituir')
                    ->label('Substituir os tempos atuais')
                    ->helperText('Só é necessário se o turno já tem tempos. Os horários já colocados nas grades das turmas não mudam.')
                    ->default(false),
            ])
            ->action(function (array $data): void {
                try {
                    $tempos = app(TempoAulaService::class)->gerar(
                        $this->getOwnerRecord(),
                        (string) $data['hora_inicio'],
                        (int) $data['quantidade'],
                        (int) $data['duracao'],
                        filled($data['intervalo_apos'] ?? null) ? (int) $data['intervalo_apos'] : null,
                        (int) ($data['intervalo_minutos'] ?? 0),
                        (bool) ($data['substituir'] ?? false),
                    );
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível gerar')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title($tempos->count().' tempo(s) criado(s)')->success()->send();
            });
    }
}
