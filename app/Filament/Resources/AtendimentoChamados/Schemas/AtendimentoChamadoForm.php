<?php

namespace App\Filament\Resources\AtendimentoChamados\Schemas;

use App\Enums\PrioridadeChamado;
use App\Enums\StatusChamado;
use App\Models\AtendimentoSetor;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AtendimentoChamadoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação do Chamado')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('protocolo')
                                    ->label('Número de Protocolo')
                                    ->placeholder('Gerado automaticamente')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(1),

                                Select::make('setor_id')
                                    ->label('Setor de Destino')
                                    ->options(AtendimentoSetor::where('ativo', true)->pluck('nome', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->columnSpan(1),

                                Select::make('prioridade')
                                    ->label('Prioridade')
                                    ->options(PrioridadeChamado::class)
                                    ->default(PrioridadeChamado::Normal)
                                    ->required()
                                    ->columnSpan(1),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('solicitante_id')
                                    ->label('Solicitante (Responsável / Família)')
                                    ->options(Pessoa::pluck('nome', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->preload(),

                                Select::make('matricula_id')
                                    ->label('Estudante Vinculado (Opcional)')
                                    ->options(function () {
                                        return Matricula::with('pessoa')->get()->mapWithKeys(function ($m) {
                                            $nome = $m->pessoa?->nome ?? 'Estudante';
                                            $turma = $m->turma?->nome ? " ({$m->turma->nome})" : '';

                                            return [$m->id => "{$nome}{$turma}"];
                                        });
                                    })
                                    ->searchable()
                                    ->preload(),
                            ]),

                        TextInput::make('assunto')
                            ->label('Assunto do Atendimento')
                            ->placeholder('Ex: Dúvida sobre declaração de matrícula ou negociação de parcela')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make('Gestão e Resolução do Chamado')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('status')
                                    ->label('Situação do Chamado')
                                    ->options(StatusChamado::class)
                                    ->default(StatusChamado::Aberto)
                                    ->required(),

                                Select::make('responsavel_atendimento_id')
                                    ->label('Atendente Responsável')
                                    ->options(User::pluck('name', 'id'))
                                    ->searchable()
                                    ->placeholder('Não atribuído'),
                            ]),
                    ]),

                Section::make('Histórico de Mensagens / Conversa')
                    ->description('Relação de mensagens trocadas entre a família e a equipe da escola.')
                    ->schema([
                        Repeater::make('mensagens')
                            ->relationship('mensagens')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Select::make('user_id')
                                            ->label('Atendente (Escola)')
                                            ->options(User::pluck('name', 'id'))
                                            ->default(fn () => auth()->id()),

                                        FileUpload::make('anexo_path')
                                            ->label('Anexo')
                                            ->disk('public')
                                            ->directory('atendimentos/anexos'),
                                    ]),

                                Textarea::make('mensagem')
                                    ->label('Mensagem')
                                    ->required()
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
