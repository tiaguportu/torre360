<?php

declare(strict_types=1);

namespace App\Filament\Resources\Funcionarios\RelationManagers;

use App\Enums\TipoAcordoBancoHoras;
use App\Exceptions\PontoException;
use App\Models\PontoAcordoBancoHoras;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Acordos de banco de horas do funcionário. Sem acordo vigente, a hora excedente é hora extra a pagar. O prazo de
 * compensação respeita o máximo do tipo de acordo (CLT, art. 59). Não se apaga: encerra-se pela data final.
 */
class AcordosBancoHorasRelationManager extends RelationManager
{
    protected static string $relationship = 'acordosBancoHoras';

    protected static ?string $title = 'Acordos de banco de horas';

    public static function canViewForRecord(Model $ownerRecord, string $pageName): bool
    {
        return (bool) auth()->user()?->can('viewAny', PontoAcordoBancoHoras::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tipo')
                    ->label('Tipo de acordo')
                    ->options(TipoAcordoBancoHoras::class)
                    ->required()
                    ->live(),
                TextInput::make('prazo_compensacao_meses')
                    ->label('Prazo para compensar (meses)')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(function (Get $get): int {
                        $tipo = $get('tipo');

                        return ($tipo instanceof TipoAcordoBancoHoras ? $tipo : TipoAcordoBancoHoras::tryFrom((string) $tipo))?->prazoMaximoMeses() ?? 12;
                    })
                    ->default(6)
                    ->required()
                    ->helperText('Individual escrito: até 6. Coletivo: até 12. Mesmo mês: o crédito vale até o fim do mês.'),
                DatePicker::make('vigencia_inicio')->label('Início da vigência')->native(false)->displayFormat('d/m/Y')->required(),
                DatePicker::make('vigencia_fim')->label('Fim da vigência')->native(false)->displayFormat('d/m/Y')->afterOrEqual('vigencia_inicio'),
                TextInput::make('documento_referencia')
                    ->label('Documento do acordo')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Número ou título do termo assinado. O acordo individual precisa ser escrito.')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('documento_referencia')
            ->defaultSort('vigencia_inicio', 'desc')
            ->columns([
                TextColumn::make('tipo')->label('Tipo')->badge(),
                TextColumn::make('prazo_compensacao_meses')->label('Prazo')->formatStateUsing(fn ($state): string => $state > 0 ? "{$state} meses" : 'Até o fim do mês'),
                TextColumn::make('vigencia_inicio')->label('Início')->date('d/m/Y'),
                TextColumn::make('vigencia_fim')->label('Fim')->date('d/m/Y')->placeholder('Em vigor'),
                TextColumn::make('documento_referencia')->label('Documento')->wrap(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Novo acordo')
                    ->visible(fn (): bool => (bool) auth()->user()?->can('create', PontoAcordoBancoHoras::class))
                    ->using(fn (array $data, string $model): Model => self::protegido(fn () => $model::create([...$data, 'funcionario_id' => $this->getOwnerRecord()->getKey()]))),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (PontoAcordoBancoHoras $record): bool => (bool) auth()->user()?->can('update', $record))
                    ->using(fn (Model $record, array $data): Model => self::protegido(function () use ($record, $data): Model {
                        $record->update($data);

                        return $record;
                    })),
            ])
            ->stackedOnMobile();
    }

    /**
     * O modelo recusa acordos fora das regras da CLT; aqui a recusa vira aviso na tela.
     */
    private static function protegido(callable $acao): Model
    {
        try {
            return $acao();
        } catch (PontoException $e) {
            Notification::make()->title('Acordo não salvo')->body($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }
}
