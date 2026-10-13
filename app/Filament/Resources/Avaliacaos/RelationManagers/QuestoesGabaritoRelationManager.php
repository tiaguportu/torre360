<?php

declare(strict_types=1);

namespace App\Filament\Resources\Avaliacaos\RelationManagers;

use App\Enums\ModoAnulacaoQuestao;
use App\Models\Avaliacao;
use App\Models\AvaliacaoGabarito;
use App\Models\AvaliacaoGabaritoQuestao;
use App\Models\Habilidade;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class QuestoesGabaritoRelationManager extends RelationManager
{
    protected static string $relationship = 'questoesGabarito';

    protected static ?string $title = 'Gabarito Oficial da Prova';

    protected static \BackedEnum|string|null $icon = Heroicon::OutlinedDocumentCheck;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('numero')
                    ->label('Número')
                    ->disabled()
                    ->required(),
                Select::make('alternativa_correta')
                    ->label('Alternativa Correta')
                    ->options([
                        'A' => 'A',
                        'B' => 'B',
                        'C' => 'C',
                        'D' => 'D',
                        'E' => 'E',
                    ])
                    ->native(false)
                    ->required(),
                TextInput::make('peso')
                    ->label('Peso da Questão')
                    ->numeric()
                    ->default(1.00)
                    ->required(),
                Select::make('habilidade_id')
                    ->label('Habilidade BNCC')
                    ->options(fn () => Habilidade::query()->pluck('codigo', 'id'))
                    ->searchable()
                    ->placeholder('Opcional'),
                Toggle::make('anulada')
                    ->label('Questão Anulada')
                    ->helperText('Se marcada, aplica o critério de anulação configurado no gabarito.')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        /** @var Avaliacao $avaliacao */
        $avaliacao = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('numero')
            ->defaultSort('numero')
            ->columns([
                TextColumn::make('numero')
                    ->label('Questão')
                    ->formatStateUsing(fn ($state) => str_pad((string) $state, 2, '0', STR_PAD_LEFT))
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('alternativa_correta')
                    ->label('Correta')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'A' => 'info',
                        'B' => 'primary',
                        'C' => 'warning',
                        'D' => 'success',
                        'E' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('peso')
                    ->label('Peso')
                    ->numeric(2),
                TextColumn::make('habilidade.codigo')
                    ->label('Habilidade BNCC')
                    ->placeholder('Não vinculada')
                    ->badge(),
                IconColumn::make('anulada')
                    ->label('Anulada')
                    ->boolean(),
            ])
            ->headerActions([
                Action::make('configurar_gabarito')
                    ->label('Configurar Gabarito')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->color('primary')
                    ->visible(fn () => auth()->user()?->can('gerenciarGabarito', $avaliacao))
                    ->fillForm(function () use ($avaliacao): array {
                        $g = $avaliacao->gabarito;

                        return [
                            'total_questoes' => $g?->total_questoes ?? 10,
                            'alternativas' => $g?->alternativas ?? 5,
                            'anulacao_modo' => $g?->anulacao_modo?->value ?? ModoAnulacaoQuestao::PontuaTodos->value,
                        ];
                    })
                    ->form([
                        TextInput::make('total_questoes')
                            ->label('Total de Questões')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->required(),
                        Select::make('alternativas')
                            ->label('Alternativas por Questão')
                            ->options([
                                4 => '4 alternativas (A, B, C, D)',
                                5 => '5 alternativas (A, B, C, D, E)',
                            ])
                            ->required()
                            ->native(false),
                        Select::make('anulacao_modo')
                            ->label('Critério para Questões Anuladas')
                            ->options([
                                ModoAnulacaoQuestao::PontuaTodos->value => 'Pontuar para todos os alunos',
                                ModoAnulacaoQuestao::Desconsidera->value => 'Desconsiderar no peso total da prova',
                            ])
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (array $data) use ($avaliacao): void {
                        $gabarito = $avaliacao->gabarito ?? new AvaliacaoGabarito(['avaliacao_id' => $avaliacao->id]);
                        $gabarito->total_questoes = (int) $data['total_questoes'];
                        $gabarito->alternativas = (int) $data['alternativas'];
                        $gabarito->anulacao_modo = ModoAnulacaoQuestao::from((string) $data['anulacao_modo']);
                        $gabarito->save();

                        // Sincroniza a quantidade de questões
                        $existentes = $gabarito->questoes()->count();
                        for ($i = $existentes + 1; $i <= $gabarito->total_questoes; $i++) {
                            AvaliacaoGabaritoQuestao::create([
                                'avaliacao_gabarito_id' => $gabarito->id,
                                'numero' => $i,
                                'alternativa_correta' => 'A',
                                'peso' => 1.00,
                            ]);
                        }

                        // Remove excedentes se reduziu o total
                        if ($existentes > $gabarito->total_questoes) {
                            $gabarito->questoes()->where('numero', '>', $gabarito->total_questoes)->delete();
                        }

                        Notification::make()
                            ->title('Gabarito configurado com sucesso!')
                            ->success()
                            ->send();
                    }),

                Action::make('preenchimento_rapido')
                    ->label('Colar Gabarito (ABCDE...)')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->color('warning')
                    ->visible(fn () => auth()->user()?->can('gerenciarGabarito', $avaliacao))
                    ->form([
                        TextInput::make('letras')
                            ->label('Sequência de Respostas')
                            ->helperText('Cole as alternativas em sequência (ex: ABCDEABCDE). Espaços serão ignorados.')
                            ->required()
                            ->minLength(1),
                    ])
                    ->action(function (array $data) use ($avaliacao): void {
                        $gabarito = $avaliacao->gabarito;
                        if (! $gabarito) {
                            Notification::make()
                                ->title('Configure o gabarito primeiro antes de colar as respostas.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $limpo = strtoupper((string) preg_replace('/[^A-Za-z]/', '', (string) $data['letras']));
                        $letrasArray = str_split($limpo);

                        foreach ($letrasArray as $idx => $letra) {
                            $num = $idx + 1;
                            if ($num > $gabarito->total_questoes) {
                                break;
                            }

                            AvaliacaoGabaritoQuestao::updateOrCreate(
                                [
                                    'avaliacao_gabarito_id' => $gabarito->id,
                                    'numero' => $num,
                                ],
                                [
                                    'alternativa_correta' => $letra,
                                    'peso' => 1.00,
                                ]
                            );
                        }

                        Notification::make()
                            ->title('Respostas do gabarito atualizadas com sucesso!')
                            ->success()
                            ->send();
                    }),

                Action::make('mesmo_peso')
                    ->label('Mesmo Peso')
                    ->icon(Heroicon::OutlinedScale)
                    ->color('gray')
                    ->visible(fn () => auth()->user()?->can('gerenciarGabarito', $avaliacao))
                    ->form([
                        TextInput::make('peso_geral')
                            ->label('Peso para Todas as Questões')
                            ->numeric()
                            ->default(1.00)
                            ->required(),
                    ])
                    ->action(function (array $data) use ($avaliacao): void {
                        $gabarito = $avaliacao->gabarito;
                        if ($gabarito) {
                            $gabarito->questoes()->update(['peso' => (float) $data['peso_geral']]);
                            Notification::make()
                                ->title('Pesos atualizados uniformemente!')
                                ->success()
                                ->send();
                        }
                    }),

                Action::make('baixar_folhas_turma')
                    ->label('Baixar Folhas da Turma (PDF)')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('success')
                    ->visible(fn () => auth()->user()?->can('gerenciarGabarito', $avaliacao))
                    ->url(fn () => route('omr.folhas.turma.pdf', ['avaliacao' => $avaliacao->id]))
                    ->openUrlInNewTab(),

                Action::make('baixar_folha_em_branco')
                    ->label('Folha de Teste (PDF)')
                    ->icon(Heroicon::OutlinedDocument)
                    ->color('gray')
                    ->visible(fn () => auth()->user()?->can('gerenciarGabarito', $avaliacao))
                    ->url(fn () => route('omr.folha.em_branco.pdf', ['avaliacao' => $avaliacao->id]))
                    ->openUrlInNewTab(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->stackedOnMobile();
    }
}
