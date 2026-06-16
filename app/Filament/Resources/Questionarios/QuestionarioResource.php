<?php

namespace App\Filament\Resources\Questionarios;

use App\Filament\Resources\Questionarios\Pages\CreateQuestionario;
use App\Filament\Resources\Questionarios\Pages\EditQuestionario;
use App\Filament\Resources\Questionarios\Pages\ListQuestionarios;
use App\Filament\Resources\Questionarios\Pages\ResponderQuestionario;
use App\Filament\Resources\Questionarios\Pages\ViewQuestionario;
use App\Filament\Resources\Questionarios\Schemas\QuestionarioForm;
use App\Filament\Resources\Questionarios\Schemas\QuestionarioInfolist;
use App\Filament\Resources\Questionarios\Tables\QuestionariosTable;
use App\Models\Questionario;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class QuestionarioResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = Questionario::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static UnitEnum|string|null $navigationGroup = 'Acadêmico';

    protected static ?string $modelLabel = 'Questionário';

    protected static ?string $pluralModelLabel = 'Questionários';

    public static function form(Schema $schema): Schema
    {
        return QuestionarioForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return QuestionarioInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuestionariosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\QuestionarioStats::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuestionarios::route('/'),
            'create' => CreateQuestionario::route('/create'),
            'view' => ViewQuestionario::route('/{record}'),
            'edit' => EditQuestionario::route('/{record}/edit'),
            'responder' => ResponderQuestionario::route('/{record}/responder'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'alvos',
            'responsaveis',
        ]);

        if (request()->routeIs('*.edit', '*.view', '*.responder')) {
            $query->with([
                'blocos.perguntas.opcoes',
            ]);
        }

        $user = auth()->user();

        if ($user && $user->hasRole('super_admin')) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            if ($user) {
                // É dono ou observador
                $q->whereHas('responsaveis', function ($sq) use ($user) {
                    $sq->where(function ($ssq) use ($user) {
                        $ssq->where('responsavel_type', 'User')
                            ->where('responsavel_id', $user->id);
                    })->orWhere(function ($ssq) use ($user) {
                        $ssq->where('responsavel_type', 'Role')
                            ->whereIn('responsavel_id', $user->roles->pluck('id'));
                    });
                });

                // Ou pode responder (baseado no escopo visivelPara)
                $q->orWhere(function ($sq) use ($user) {
                    // Nós recriamos o visivelPara usando a variável $user
                    // Não dá pra só chamar ->visivelPara($user) porque o scope já usa
                    // a mesma query root ($sq). Mas podemos passar para o scope.
                    // O Laravel permite misturar orWhere com Scopes!
                    $sq->visivelPara($user);
                });
            } else {
                // Visitante anônimo
                $q->visivelPara(null);
            }
        });
    }
}
