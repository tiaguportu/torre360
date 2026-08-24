<?php

namespace App\Filament\Resources\MensagemWhatsappTemplates;

use App\Filament\Resources\MensagemWhatsappTemplates\Pages\CreateMensagemWhatsappTemplate;
use App\Filament\Resources\MensagemWhatsappTemplates\Pages\EditMensagemWhatsappTemplate;
use App\Filament\Resources\MensagemWhatsappTemplates\Pages\ListMensagemWhatsappTemplates;
use App\Filament\Resources\MensagemWhatsappTemplates\Schemas\MensagemWhatsappTemplateForm;
use App\Filament\Resources\MensagemWhatsappTemplates\Tables\MensagemWhatsappTemplatesTable;
use App\Models\MensagemWhatsappTemplate;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MensagemWhatsappTemplateResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
        ];
    }

    protected static ?string $model = MensagemWhatsappTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static UnitEnum|string|null $navigationGroup = 'CRM / Comercial';

    protected static ?string $modelLabel = 'Modelo de Mensagem WhatsApp';

    protected static ?string $pluralModelLabel = 'Modelos de Mensagem WhatsApp';

    protected static ?string $navigationLabel = 'Modelos de WhatsApp';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return MensagemWhatsappTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MensagemWhatsappTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMensagemWhatsappTemplates::route('/'),
            'create' => CreateMensagemWhatsappTemplate::route('/create'),
            'edit' => EditMensagemWhatsappTemplate::route('/{record}/edit'),
        ];
    }
}
