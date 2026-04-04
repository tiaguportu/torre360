<?php

namespace App\Filament\Resources\Enderecos;


use App\Filament\Resources\Enderecos\Pages\CreateEndereco;
use App\Filament\Resources\Enderecos\Pages\EditEndereco;
use App\Filament\Resources\Enderecos\Pages\ListEnderecos;
use App\Filament\Resources\Enderecos\Schemas\EnderecoForm;
use App\Filament\Resources\Enderecos\Tables\EnderecosTable;
use App\Models\Endereco;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EnderecoResource extends Resource
{

    protected static ?string $model = Endereco::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?int $navigationSort = 5;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    public static function form(Schema $schema): Schema
    {
        return EnderecoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EnderecosTable::configure($table);
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
            'index' => ListEnderecos::route('/'),
            'create' => CreateEndereco::route('/create'),
            'edit' => EditEndereco::route('/{record}/edit'),
        ];
    }
}
