<?php

namespace App\Filament\Resources\TemplateContratos;

use App\Filament\Resources\TemplateContratos\Pages\CreateTemplateContrato;
use App\Filament\Resources\TemplateContratos\Pages\EditTemplateContrato;
use App\Filament\Resources\TemplateContratos\Pages\ListTemplateContratos;
use App\Filament\Resources\TemplateContratos\Schemas\TemplateContratoForm;
use App\Filament\Resources\TemplateContratos\Tables\TemplateContratosTable;
use App\Models\TemplateContrato;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TemplateContratoResource extends Resource
{
    protected static ?string $model = TemplateContrato::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|\UnitEnum|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return TemplateContratoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TemplateContratosTable::configure($table);
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
            'index' => ListTemplateContratos::route('/'),
            'create' => CreateTemplateContrato::route('/create'),
            'edit' => EditTemplateContrato::route('/{record}/edit'),
        ];
    }
}
