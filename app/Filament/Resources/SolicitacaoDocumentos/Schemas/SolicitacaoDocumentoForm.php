<?php

namespace App\Filament\Resources\SolicitacaoDocumentos\Schemas;

use App\Enums\StatusSolicitacaoDocumento;
use App\Models\Matricula;
use App\Models\TemplateDocumento;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SolicitacaoDocumentoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação da Solicitação')
                    ->schema([
                        Select::make('matricula_id')
                            ->label('Matrícula / Aluno')
                            ->relationship('matricula')
                            ->getOptionLabelFromRecordUsing(fn (Matricula $record) => "{$record->pessoa?->nome} — Turma: {$record->turma?->nome} ({$record->turma?->serie?->curso?->nome_interno})")
                            ->searchable(['pessoa.nome', 'pessoa.cpf'])
                            ->preload()
                            ->required()
                            ->disabled(fn (?string $operation) => $operation === 'edit'),

                        Select::make('template_documento_id')
                            ->label('Modelo de Documento')
                            ->relationship('templateDocumento', 'nome')
                            ->options(TemplateDocumento::where('is_ativo', true)->pluck('nome', 'id'))
                            ->required()
                            ->disabled(fn (?string $operation) => $operation === 'edit'),

                        Select::make('status')
                            ->label('Situação do Documento')
                            ->options(StatusSolicitacaoDocumento::class)
                            ->default(StatusSolicitacaoDocumento::Disponivel)
                            ->required(),

                        DatePicker::make('data_validade')
                            ->label('Validade do Documento')
                            ->helperText('Deixe vazio para aplicar o padrão configurado no modelo.')
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        Textarea::make('observacao_solicitante')
                            ->label('Observação do Solicitante / Família')
                            ->rows(2)
                            ->columnSpanFull(),

                        Textarea::make('justificativa_recusa')
                            ->label('Justificativa de Recusa (se aplicável)')
                            ->rows(2)
                            ->visible(fn ($get) => $get('status') === StatusSolicitacaoDocumento::Rejeitado->value)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
