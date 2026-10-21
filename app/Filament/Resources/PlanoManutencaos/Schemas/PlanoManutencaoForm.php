<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanoManutencaos\Schemas;

use App\Enums\StatusBemPatrimonial;
use App\Models\BemPatrimonial;
use App\Models\PlanoManutencao;
use App\Models\Sala;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PlanoManutencaoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plano')
                    ->columns(2)
                    ->schema([
                        TextInput::make('titulo')
                            ->label('Título')
                            ->placeholder('Ex.: Revisão dos extintores')
                            ->required()
                            ->maxLength(255),
                        Select::make('prioridade')
                            ->label('Prioridade da ordem de serviço')
                            ->options(['Baixa' => 'Baixa', 'Média' => 'Média', 'Alta' => 'Alta', 'Urgente' => 'Urgente'])
                            ->default('Média')
                            ->required(),
                        Textarea::make('descricao')
                            ->label('Descrição')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                Section::make('O que será mantido')
                    ->description('Informe o bem do patrimônio, o espaço, ou os dois (ex.: o projetor da Sala 3).')
                    ->columns(2)
                    ->schema([
                        Select::make('bem_patrimonial_id')
                            ->label('Bem do patrimônio')
                            ->options(fn (?PlanoManutencao $record): array => BemPatrimonial::query()
                                ->where(fn ($q) => $q->where('status', '!=', StatusBemPatrimonial::Baixado->value)->when($record?->bem_patrimonial_id, fn ($qq) => $qq->orWhere('id', $record->bem_patrimonial_id)))
                                ->orderBy('descricao')
                                ->get()
                                ->mapWithKeys(fn (BemPatrimonial $b): array => [$b->id => trim("{$b->numero_patrimonio} — {$b->descricao}", ' —')])
                                ->all())
                            ->searchable()
                            ->requiredWithout('sala_id')
                            ->validationMessages(['required_without' => 'Informe o bem ou o espaço (ou os dois).']),
                        Select::make('sala_id')
                            ->label('Espaço')
                            ->options(fn (?PlanoManutencao $record): array => Sala::query()
                                ->where(fn ($q) => $q->where('ativa', true)->when($record?->sala_id, fn ($qq) => $qq->orWhere('id', $record->sala_id)))
                                ->orderBy('nome')
                                ->pluck('nome', 'id')
                                ->all())
                            ->searchable()
                            ->requiredWithout('bem_patrimonial_id')
                            ->validationMessages(['required_without' => 'Informe o bem ou o espaço (ou os dois).']),
                    ]),
                Section::make('Recorrência')
                    ->columns(2)
                    ->schema([
                        TextInput::make('periodicidade_dias')
                            ->label('A cada quantos dias')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(3650)
                            ->required()
                            ->helperText('Ex.: 30 (mensal), 90 (trimestral), 180 (semestral), 365 (anual).'),
                        DatePicker::make('proxima_execucao')
                            ->label('Próxima manutenção')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required(),
                        TextInput::make('antecedencia_dias')
                            ->label('Abrir a ordem de serviço com antecedência de (dias)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(365)
                            ->default(7)
                            ->required(),
                        Select::make('categoria_os_id')
                            ->label('Categoria da ordem de serviço')
                            ->relationship('categoriaOs', 'nome')
                            ->searchable()
                            ->preload(),
                        Toggle::make('ativo')
                            ->label('Plano ativo')
                            ->default(true)
                            ->helperText('Planos inativos não geram ordens de serviço.'),
                    ]),
                Section::make('Checklist')
                    ->description('Itens que o executor confere e que acompanham a ordem de serviço.')
                    ->collapsible()
                    ->schema([
                        Repeater::make('checklist')
                            ->hiddenLabel()
                            ->simple(TextInput::make('item')->required()->maxLength(255))
                            ->addActionLabel('Adicionar item')
                            ->defaultItems(0),
                    ]),
            ]);
    }
}
