<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reunioes\Schemas;

use App\Enums\StatusTurma;
use App\Enums\TipoReuniao;
use App\Models\EtapaAvaliativa;
use App\Models\Sala;
use App\Models\Turma;
use App\Models\User;
use App\Services\ConselhoClasseService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ReuniaoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Reunião')
                ->columns(2)
                ->schema([
                    Select::make('tipo')
                        ->label('Tipo')
                        ->options(TipoReuniao::class)
                        ->default(TipoReuniao::Pedagogica->value)
                        ->native(false)
                        ->live()
                        ->required()
                        ->disabledOn('edit')
                        ->helperText('O conselho de classe traz a pauta aluno por aluno. A devolutiva individual é sempre sigilosa.'),
                    TextInput::make('titulo')
                        ->label('Título')
                        ->placeholder('Ex.: Conselho de classe do 1º bimestre — 6º ano A')
                        ->required()
                        ->maxLength(160),
                    DateTimePicker::make('inicio_em')
                        ->label('Início')
                        ->native(false)
                        ->seconds(false)
                        ->displayFormat('d/m/Y H:i')
                        ->required(),
                    DateTimePicker::make('fim_em')
                        ->label('Fim')
                        ->native(false)
                        ->seconds(false)
                        ->displayFormat('d/m/Y H:i')
                        ->helperText('Em branco, usa a duração padrão do tipo de reunião. Começa e termina no mesmo dia.'),
                    Select::make('sala_id')
                        ->label('Espaço')
                        ->options(fn (): array => Sala::query()->where('ativa', true)->orderBy('nome')->pluck('nome', 'id')->all())
                        ->searchable()
                        ->native(false)
                        ->helperText('Escolher um espaço reserva o horário nele. Reunião sigilosa não mostra o assunto na reserva.'),
                    TextInput::make('local')
                        ->label('Local (se não for um espaço cadastrado)')
                        ->placeholder('Ex.: Sala dos professores, videochamada')
                        ->maxLength(120),
                ]),

            Section::make('Conselho de classe')
                ->columns(2)
                ->visible(fn (Get $get): bool => self::tipo($get)?->temAlunos() === true)
                ->schema([
                    Select::make('turma_id')
                        ->label('Turma')
                        ->relationship('turma', 'nome', fn ($query) => $query->where('status', '!=', StatusTurma::Cancelada->value)->orderBy('nome'))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required(fn (Get $get): bool => self::tipo($get)?->temAlunos() === true)
                        ->disabledOn('edit')
                        ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                            $set('etapa_avaliativa_id', null);

                            $turma = $state ? Turma::query()->find($state) : null;

                            if ($turma === null) {
                                return;
                            }

                            $servico = app(ConselhoClasseService::class);
                            $set('presidente_user_id', $servico->presidente($turma)?->id);

                            if (blank($get('participantes'))) {
                                $set('participantes', $servico->sugerirParticipantes($turma)->pluck('id')->all());
                            }
                        }),
                    Select::make('etapa_avaliativa_id')
                        ->label('Etapa avaliativa')
                        ->options(function (Get $get): array {
                            $turma = filled($get('turma_id')) ? Turma::query()->find($get('turma_id')) : null;

                            if ($turma === null) {
                                return [];
                            }

                            return EtapaAvaliativa::query()
                                ->daTurma($turma)
                                ->orderBy('data_inicio')
                                ->pluck('nome', 'id')
                                ->all();
                        })
                        ->searchable()
                        ->native(false)
                        ->required(fn (Get $get): bool => self::tipo($get)?->temAlunos() === true)
                        ->disabledOn('edit')
                        ->helperText('A etapa define de onde vêm as notas e a frequência mostradas na pauta.'),
                    Select::make('presidente_user_id')
                        ->label('Presidente do conselho')
                        ->options(fn (): array => self::equipe())
                        ->searchable()
                        ->native(false)
                        ->helperText('Em geral o professor conselheiro. O presidente delibera e fecha a ata junto com a coordenação.'),
                ]),

            Section::make('Quem participa e a pauta')
                ->columns(1)
                ->schema([
                    Select::make('participantes')
                        ->label('Participantes')
                        ->multiple()
                        ->options(fn (): array => self::equipe())
                        ->searchable()
                        ->native(false)
                        ->helperText('Cada pessoa recebe um aviso com o convite. Quem organiza já está na lista.')
                        ->suffixAction(
                            Action::make('sugerir_participantes')
                                ->label('Sugerir os da turma')
                                ->icon('heroicon-o-user-group')
                                ->visible(fn (Get $get): bool => self::tipo($get)?->temAlunos() === true && filled($get('turma_id')))
                                ->action(function (Get $get, Set $set): void {
                                    $turma = Turma::query()->find($get('turma_id'));

                                    if ($turma !== null) {
                                        $set('participantes', app(ConselhoClasseService::class)->sugerirParticipantes($turma)->pluck('id')->all());
                                    }
                                }),
                        ),
                    Toggle::make('confidencial')
                        ->label('Reunião sigilosa')
                        ->helperText('Só quem organiza e quem participa vê a reunião e a ata. A devolutiva individual já é sempre sigilosa.')
                        ->hidden(fn (Get $get): bool => self::tipo($get)?->sempreConfidencial() === true),
                    Textarea::make('pauta')
                        ->label('Pauta')
                        ->rows(4)
                        ->maxLength(4000),
                ]),
        ]);
    }

    private static function tipo(Get $get): ?TipoReuniao
    {
        $valor = $get('tipo');

        return $valor instanceof TipoReuniao ? $valor : TipoReuniao::tryFrom((string) $valor);
    }

    /**
     * Quem pode ser convidado: contas ativas da equipe (quem tem algum papel além de responsável e aluno).
     *
     * @return array<int, string>
     */
    public static function equipe(): array
    {
        return User::query()
            ->ativos()
            ->whereHas('roles', fn ($q) => $q->whereNotIn('name', ['responsavel', 'aluno']))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
