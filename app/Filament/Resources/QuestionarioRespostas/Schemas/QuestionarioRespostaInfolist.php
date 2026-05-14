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

                Section::make('Estrutura de Blocos e Respostas')
                    ->schema([
                        RepeatableEntry::make('questionario_blocos')
                            ->label('')
                            ->state(fn ($record) => $record->questionario->blocos()->orderBy('ordem')->get())
                            ->schema([
                                Section::make(fn ($record) => $record->titulo)
                                    ->description(fn ($record) => $record->descricao)
                                    ->schema([
                                        RepeatableEntry::make('bloco_perguntas')
                                            ->label('')
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

                                                        if ($pr->resposta_texto !== null && $pr->resposta_texto !== '') {
                                                            return $pr->resposta_texto;
                                                        }

                                                        if (! empty($pr->resposta_json)) {
                                                            return is_array($pr->resposta_json) ? implode(', ', $pr->resposta_json) : $pr->resposta_json;
                                                        }

                                                        return '---';
                                                    }),
                                            ])
                                            ->columns(1),
                                    ])
                                    ->compact(),
                            ]),
                    ]),
            ]);
    }
}
