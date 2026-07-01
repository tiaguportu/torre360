<?php

namespace App\Filament\Resources\QuestionarioRespostas\Schemas;

use App\Models\QuestionarioPerguntaResposta;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QuestionarioRespostaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações do Envio')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('questionario.titulo')
                            ->label('Questionário')
                            ->weight('bold')
                            ->columnSpan(2),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'enviado' => 'success',
                                'pendente' => 'warning',
                                default => 'gray',
                            }),
                        TextEntry::make('user.name')
                            ->label('Respondente')
                            ->placeholder('Anônimo'),
                        TextEntry::make('perfil_institucional')
                            ->label('Perfil'),
                        TextEntry::make('fim_preenchimento')
                            ->label('Data de Envio')
                            ->dateTime('d/m/Y H:i'),
                    ]),

                Section::make('Feedbacks / Pareceres Avaliativos')
                    ->description('Pareceres e feedbacks registrados por gestores/avaliadores para esta resposta.')
                    ->schema([
                        RepeatableEntry::make('feedbacks')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('texto')
                                    ->hiddenLabel()
                                    ->prose()
                                    ->columnSpanFull(),
                                TextEntry::make('user.name')
                                    ->label('Registrado por')
                                    ->placeholder('Sistema / Anônimo')
                                    ->inlineLabel(),
                                TextEntry::make('created_at')
                                    ->label('Em')
                                    ->dateTime('d/m/Y H:i')
                                    ->inlineLabel(),
                            ])
                            ->columns(2)
                            ->placeholder('Nenhum feedback registrado até o momento.'),
                    ])
                    ->visible(fn ($record) => $record->feedbacks()->exists() || auth()->user()?->can('Create:QuestionarioResposta')),

                Section::make('Perguntas e Respostas')
                    ->columns(1)
                    ->schema([
                        RepeatableEntry::make('questionario_blocos')
                            ->hiddenLabel()
                            ->columns(1)
                            ->state(fn ($record) => $record->questionario->blocos()->orderBy('ordem')->get())
                            ->schema([
                                Section::make(fn ($record) => $record->titulo)
                                    ->description(fn ($record) => $record->descricao)
                                    ->columns(1)
                                    ->schema([
                                        RepeatableEntry::make('bloco_perguntas')
                                            ->hiddenLabel()
                                            ->columns(1)
                                            ->state(fn ($record) => $record->perguntas()->orderBy('ordem')->get())
                                            ->schema([
                                                TextEntry::make('enunciado')
                                                    ->label('Pergunta')
                                                    ->weight('bold')
                                                    ->html(),
                                                TextEntry::make('valor_resposta')
                                                    ->label('Resposta')
                                                    ->state(function ($record, $component) {
                                                        // $record é QuestionarioPergunta
                                                        $questionarioResposta = $component->getLivewire()->record;

                                                        if (! $questionarioResposta) {
                                                            return '---';
                                                        }

                                                        $pr = QuestionarioPerguntaResposta::where('questionario_resposta_id', $questionarioResposta->id)
                                                            ->where('questionario_pergunta_id', $record->id)
                                                            ->first();

                                                        if (! $pr) {
                                                            return 'Não respondida';
                                                        }

                                                        return $pr->valor_exibicao;
                                                    }),
                                            ])
                                            ->columns(1),
                                    ])
                                    ->compact(),
                            ]),
                    ]),
            ])
            ->columns(1);
    }
}
