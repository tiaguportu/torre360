<?php

namespace App\Filament\Resources\VideoTutorials;

use App\Filament\Resources\VideoTutorials\Pages\CreateVideoTutorial;
use App\Filament\Resources\VideoTutorials\Pages\EditVideoTutorial;
use App\Filament\Resources\VideoTutorials\Pages\ListVideoTutorials;
use App\Filament\Resources\VideoTutorials\Schemas\VideoTutorialForm;
use App\Filament\Resources\VideoTutorials\Tables\VideoTutorialsTable;
use App\Models\VideoTutorial;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VideoTutorialResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = VideoTutorial::class;

    protected static ?string $modelLabel = 'Vídeo Tutorial';

    protected static ?string $pluralModelLabel = 'Vídeos Tutoriais';

    protected static string|\UnitEnum|null $navigationGroup = 'Ajuda';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedVideoCamera;

    public static function form(Schema $schema): Schema
    {
        return VideoTutorialForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VideoTutorialsTable::configure($table);
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
            'index' => ListVideoTutorials::route('/'),
            'create' => CreateVideoTutorial::route('/create'),
            'edit' => EditVideoTutorial::route('/{record}/edit'),
        ];
    }
}
