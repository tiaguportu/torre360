<?php

namespace App\Filament\Resources\EmailLogs;

use App\Filament\Resources\EmailLogs\Pages\ListEmailLogs;
use App\Filament\Resources\EmailLogs\Pages\ViewEmailLog;
use App\Filament\Resources\EmailLogs\Schemas\EmailLogInfolist;
use App\Filament\Resources\EmailLogs\Tables\EmailLogsTable;
use App\Models\EmailLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EmailLogResource extends Resource
{
    protected static ?string $model = EmailLog::class;

    protected static ?string $navigationGroup = 'Auditoria';

    protected static ?string $navigationLabel = 'E-mails Enviados';

    protected static ?string $modelLabel = 'E-mail Enviado';

    protected static ?string $pluralModelLabel = 'E-mails Enviados';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmailLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmailLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailLogs::route('/'),
            'view' => ViewEmailLog::route('/{record}'),
        ];
    }
}
