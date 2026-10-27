<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reunioes\RelationManagers;

use App\Filament\Resources\Reunioes\Schemas\ReuniaoForm;
use App\Models\Reuniao;
use App\Models\ReuniaoAluno;
use App\Services\ConselhoClasseService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A pauta do conselho de classe: cada aluno da turma com a fotografia dos indicadores e a deliberação. A deliberação só registra o que
 * ficou decidido; não altera nota, frequência nem a situação da matrícula.
 */
class AlunosRelationManager extends RelationManager
{
    protected static string $relationship = 'alunos';

    protected static ?string $title = 'Alunos (pauta do conselho)';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Reuniao && $ownerRecord->ehConselho();
    }

    private function reuniao(): Reuniao
    {
        /** @var Reuniao $reuniao */
        $reuniao = $this->getOwnerRecord();

        return $reuniao;
    }

    private function podeDeliberar(): bool
    {
        return $this->reuniao()->emAndamento() && (bool) auth()->user()?->can('deliberar', $this->reuniao());
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['matricula.pessoa', 'encaminhamentos']))
            ->recordTitleAttribute('id')
            ->defaultSort('ordem')
            ->paginated(false)
            ->columns([
                TextColumn::make('matricula.pessoa.nome')->label('Aluno')->weight('bold')->searchable(),
                TextColumn::make('media')
                    ->label('Média')
                    ->state(fn (ReuniaoAluno $record): ?string => isset($record->indicadores['media_geral']) ? number_format((float) $record->indicadores['media_geral'], 1, ',', '.') : null)
                    ->placeholder('—')
                    ->color(fn (ReuniaoAluno $record): ?string => self::abaixoDaMedia($record) ? 'danger' : null),
                TextColumn::make('abaixo')
                    ->label('Abaixo da média')
                    ->state(fn (ReuniaoAluno $record): string => (string) count($record->indicadores['disciplinas_abaixo_da_media'] ?? []))
                    ->tooltip(fn (ReuniaoAluno $record): ?string => ($lista = $record->indicadores['disciplinas_abaixo_da_media'] ?? []) !== [] ? implode(', ', $lista) : null)
                    ->badge()
                    ->color(fn (ReuniaoAluno $record): string => ($record->indicadores['disciplinas_abaixo_da_media'] ?? []) !== [] ? 'danger' : 'gray'),
                TextColumn::make('frequencia')
                    ->label('Frequência')
                    ->state(fn (ReuniaoAluno $record): ?string => isset($record->indicadores['frequencia']) ? number_format((float) $record->indicadores['frequencia'], 1, ',', '.').'%' : null)
                    ->placeholder('—')
                    ->tooltip(fn (ReuniaoAluno $record): ?string => isset($record->indicadores['faltas']) ? ($record->indicadores['faltas'].' falta(s)') : null)
                    ->color(fn (ReuniaoAluno $record): ?string => isset($record->indicadores['frequencia']) && (float) $record->indicadores['frequencia'] < 75 ? 'danger' : null),
                TextColumn::make('ocorrencias')
                    ->label('Ocorrências')
                    ->state(fn (ReuniaoAluno $record): string => (string) ($record->indicadores['ocorrencias'] ?? 0))
                    ->tooltip(fn (ReuniaoAluno $record): ?string => ($tipos = $record->indicadores['ocorrencias_tipos'] ?? []) !== [] ? implode(', ', $tipos) : null)
                    ->badge()
                    ->color(fn (ReuniaoAluno $record): string => (int) ($record->indicadores['ocorrencias'] ?? 0) > 0 ? 'warning' : 'gray'),
                IconColumn::make('parecer')
                    ->label('Parecer')
                    ->state(fn (ReuniaoAluno $record): bool => (bool) ($record->indicadores['parecer_publicado'] ?? false))
                    ->boolean()
                    ->falseColor('gray'),
                TextColumn::make('risco')
                    ->label('Risco de evasão')
                    ->state(fn (ReuniaoAluno $record): ?int => $record->indicadores['risco_evasao'] ?? null)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('deliberacao')
                    ->label('Deliberação')
                    ->state(fn (ReuniaoAluno $record): ?string => $record->rotuloDaDeliberacao())
                    ->badge()
                    ->color(fn (ReuniaoAluno $record): string => $record->foiDeliberado() ? 'success' : 'warning')
                    ->placeholder('Pendente')
                    ->description(fn (ReuniaoAluno $record): ?string => $record->observacao ? str($record->observacao)->limit(60)->toString() : null),
                TextColumn::make('encaminhamentos_abertos')
                    ->label('Encaminhamentos')
                    ->state(fn (ReuniaoAluno $record): string => (string) $record->encaminhamentos->count())
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
            ])
            ->headerActions([
                Action::make('atualizar_indicadores')
                    ->label('Atualizar indicadores')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (): bool => $this->podeDeliberar())
                    ->requiresConfirmation()
                    ->modalDescription('Refaz a fotografia de notas, frequência e ocorrências dos alunos ainda sem deliberação e inclui quem entrou na turma depois. Quem já foi deliberado mantém o que o conselho viu.')
                    ->action(function (): void {
                        abort_unless($this->podeDeliberar(), 403);

                        try {
                            $total = app(ConselhoClasseService::class)->montarPauta($this->reuniao());
                        } catch (\DomainException $e) {
                            Notification::make()->title('Não foi possível atualizar')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title("Pauta atualizada: {$total} aluno(s)")->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('deliberar')
                    ->label(fn (ReuniaoAluno $record): string => $record->foiDeliberado() ? 'Rever deliberação' : 'Deliberar')
                    ->icon('heroicon-o-scale')
                    ->color('primary')
                    ->visible(fn (): bool => $this->podeDeliberar())
                    ->modalHeading(fn (ReuniaoAluno $record): string => 'Deliberar: '.($record->matricula?->pessoa?->nome ?? 'aluno'))
                    ->modalDescription('A deliberação registra a decisão do conselho. Ela não altera nota, frequência nem a matrícula do aluno.')
                    ->modalWidth('2xl')
                    ->fillForm(fn (ReuniaoAluno $record): array => [
                        'deliberacao' => $record->deliberacao,
                        'observacao' => $record->observacao,
                        'responsavel_user_id' => $this->reuniao()->organizador_user_id,
                        'prazo' => now()->addDays((int) config('reunioes.encaminhamento_prazo_dias', 15))->toDateString(),
                    ])
                    ->schema([
                        Select::make('deliberacao')
                            ->label('Decisão do conselho')
                            ->options(config('reunioes.deliberacoes', []))
                            ->native(false)
                            ->live()
                            ->required(),
                        Textarea::make('observacao')->label('Observações do conselho')->rows(3)->maxLength(2000),
                        Textarea::make('descricao')
                            ->label('O que será feito')
                            ->rows(2)
                            ->maxLength(300)
                            ->visible(fn (Get $get): bool => in_array($get('deliberacao'), config('reunioes.deliberacoes_com_encaminhamento', []), true))
                            ->required(fn (Get $get): bool => in_array($get('deliberacao'), config('reunioes.deliberacoes_com_encaminhamento', []), true)),
                        Select::make('responsavel_user_id')
                            ->label('Responsável')
                            ->options(fn (): array => ReuniaoForm::equipe())
                            ->searchable()
                            ->native(false)
                            ->visible(fn (Get $get): bool => in_array($get('deliberacao'), config('reunioes.deliberacoes_com_encaminhamento', []), true)),
                        DatePicker::make('prazo')
                            ->label('Prazo')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->minDate(today())
                            ->visible(fn (Get $get): bool => in_array($get('deliberacao'), config('reunioes.deliberacoes_com_encaminhamento', []), true)),
                    ])
                    ->action(function (ReuniaoAluno $record, array $data): void {
                        abort_unless($this->podeDeliberar(), 403);

                        try {
                            app(ConselhoClasseService::class)->deliberar(
                                $record,
                                (string) $data['deliberacao'],
                                $data['observacao'] ?? null,
                                auth()->user(),
                                [
                                    'descricao' => $data['descricao'] ?? null,
                                    'responsavel_user_id' => $data['responsavel_user_id'] ?? null,
                                    'prazo' => $data['prazo'] ?? null,
                                ],
                            );
                        } catch (\DomainException $e) {
                            Notification::make()->title('Deliberação recusada')->body($e->getMessage())->danger()->persistent()->send();

                            return;
                        }

                        Notification::make()->title('Deliberação registrada')->success()->send();
                    }),
            ])
            ->stackedOnMobile()
            ->emptyStateHeading('Sem alunos na pauta')
            ->emptyStateDescription('A pauta é montada com os alunos ativos da turma quando o conselho é marcado.');
    }

    private static function abaixoDaMedia(ReuniaoAluno $linha): bool
    {
        $indicadores = $linha->indicadores ?? [];

        return isset($indicadores['media_geral'], $indicadores['nota_aprovacao']) && (float) $indicadores['media_geral'] < (float) $indicadores['nota_aprovacao'];
    }
}
