<?php

namespace App\Filament\Resources\EmailLogs;

use App\Filament\Resources\EmailLogs\Pages\ListEmailLogs;
use App\Filament\Resources\EmailLogs\Pages\ViewEmailLog;
use App\Models\EmailLog;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmailLogResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = EmailLog::class;

    protected static ?string $navigationGroup = 'Auditoria';

    protected static ?string $navigationLabel = 'E-mails Enviados';

    protected static ?string $modelLabel = 'E-mail Enviado';

    protected static ?string $pluralModelLabel = 'E-mails Enviados';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'delete',
            'delete_any',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalhes do E-mail')
                    ->components([
                        Grid::make(3)
                            ->components([
                                TextEntry::make('user.name')
                                    ->label('Quem enviou')
                                    ->placeholder('Sistema'),
                                TextEntry::make('sent_at')
                                    ->label('Data de Envio')
                                    ->dateTime(),
                                TextEntry::make('subject')
                                    ->label('Assunto'),
                                TextEntry::make('to')
                                    ->label('Para')
                                    ->bulleted()
                                    ->columnSpan(2),
                            ]),
                    ]),

                Section::make('Conteúdo')
                    ->components([
                        TextEntry::make('body')
                            ->label('')
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Quem enviou')
                    ->placeholder('Sistema')
                    ->sortable(),
                TextColumn::make('to')
                    ->label('Para')
                    ->searchable()
                    ->bulleted(),
                TextColumn::make('subject')
                    ->label('Assunto')
                    ->searchable(),
                TextColumn::make('sent_at')
                    ->label('Enviado em')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailLogs::route('/'),
            'view' => ViewEmailLog::route('/{record}'),
        ];
    }
}
