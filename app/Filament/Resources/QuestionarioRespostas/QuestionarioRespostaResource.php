<?php

namespace App\Filament\Resources\QuestionarioRespostas;

use App\Filament\Resources\QuestionarioRespostas\Pages\CreateQuestionarioResposta;
use App\Filament\Resources\QuestionarioRespostas\Pages\EditQuestionarioResposta;
use App\Filament\Resources\QuestionarioRespostas\Pages\ListQuestionarioRespostas;
use App\Filament\Resources\QuestionarioRespostas\Pages\ViewQuestionarioResposta;
use App\Filament\Resources\QuestionarioRespostas\Schemas\QuestionarioRespostaForm;
use App\Filament\Resources\QuestionarioRespostas\Schemas\QuestionarioRespostaInfolist;
use App\Filament\Resources\QuestionarioRespostas\Tables\QuestionarioRespostasTable;
use App\Models\QuestionarioResposta;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class QuestionarioRespostaResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = QuestionarioResposta::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static UnitEnum|string|null $navigationGroup = 'Gestão Acadêmica';

    protected static ?string $modelLabel = 'Resposta de Questionário';

    protected static ?string $pluralModelLabel = 'Respostas de Questionários';

    public static function form(Schema $schema): Schema
    {
        return QuestionarioRespostaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return QuestionarioRespostaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuestionarioRespostasTable::configure($table);
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
            'index' => ListQuestionarioRespostas::route('/'),
            'create' => CreateQuestionarioResposta::route('/create'),
            'view' => ViewQuestionarioResposta::route('/{record}'),
            'edit' => EditQuestionarioResposta::route('/{record}/edit'),
        ];
    }
}
