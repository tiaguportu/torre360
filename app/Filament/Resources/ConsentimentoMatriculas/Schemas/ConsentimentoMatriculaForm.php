<?php

namespace App\Filament\Resources\ConsentimentoMatriculas\Schemas;

use App\Enums\StatusConsentimento;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ConsentimentoMatriculaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Consentimento')
                    ->columns(2)
                    ->schema([
                        Select::make('matricula_id')
                            ->label('Matrícula / Aluno')
                            ->relationship('matricula', 'id', modifyQueryUsing: fn ($query) => $query->with(['pessoa', 'turma']))
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->pessoa?->nome} — {$record->turma?->nome}")
                            ->searchable(['pessoa.nome'])
                            ->preload()
                            ->required()
                            ->columnSpanFull(),
                        Select::make('tipo_consentimento_id')
                            ->label('Tipo de Consentimento')
                            ->relationship('tipoConsentimento', 'nome')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('status')
                            ->label('Status')
                            ->options(StatusConsentimento::class)
                            ->default(StatusConsentimento::Pendente)
                            ->required()
                            ->helperText('Use para registrar manualmente uma resposta colhida fora do Portal (ex.: papel assinado presencialmente).'),
                        Textarea::make('observacao')
                            ->label('Observação')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
