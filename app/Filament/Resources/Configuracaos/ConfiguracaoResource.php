<?php

namespace App\Filament\Resources\Configuracaos;

use App\Filament\Resources\Configuracaos\Pages\CreateConfiguracao;
use App\Filament\Resources\Configuracaos\Pages\EditConfiguracao;
use App\Filament\Resources\Configuracaos\Pages\ListConfiguracaos;
use App\Filament\Resources\Configuracaos\Schemas\ConfiguracaoForm;
use App\Filament\Resources\Configuracaos\Tables\ConfiguracaosTable;
use App\Models\Configuracao;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConfiguracaoResource extends Resource
{
    protected static ?string $model = Configuracao::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Configurações';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog';

    protected static ?string $recordTitleAttribute = 'campo';

    public static function form(Schema $schema): Schema
    {
        return ConfiguracaoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConfiguracaosTable::configure($table);
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
            'index' => ListConfiguracaos::route('/'),
            'create' => CreateConfiguracao::route('/create'),
            'edit' => EditConfiguracao::route('/{record}/edit'),
        ];
    }
}
