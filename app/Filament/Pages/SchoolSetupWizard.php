<?php

namespace App\Filament\Pages;

use App\Models\Curso;
use App\Models\PeriodoLetivo;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Unidade;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class SchoolSetupWizard extends Page implements HasForms, HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
        ];
    }

    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home-modern';

    protected static string|\UnitEnum|null $navigationGroup = 'Configurações';

    protected static ?string $navigationLabel = 'Configuração Inicial';

    protected static ?string $title = 'Assistente de Configuração Escolar';

    protected string $view = 'filament.pages.school-setup-wizard';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('A Escola')
                        ->description('Identificação da Unidade Sede')
                        ->icon('heroicon-m-building-office')
                        ->components([
                            Section::make()
                                ->columns(1)
                                ->components([
                                    TextInput::make('unidade_nome')
                                        ->label('Nome da Escola/Unidade')
                                        ->placeholder('Ex: Unidade Centro')
                                        ->required(),
                                ]),
                        ]),
                    Step::make('Calendário')
                        ->description('Ano Letivo de Trabalho')
                        ->icon('heroicon-m-calendar')
                        ->components([
                            Section::make()
                                ->columns(3)
                                ->components([
                                    TextInput::make('periodo_nome')
                                        ->label('Nome do Ano Letivo')
                                        ->placeholder('Ex: 2026')
                                        ->required(),
                                    DatePicker::make('periodo_inicio')
                                        ->label('Início das Aulas')
                                        ->required(),
                                    DatePicker::make('periodo_fim')
                                        ->label('Previsão de Término')
                                        ->required(),
                                ]),
                        ]),
                    Step::make('Estrutura de Ensino')
                        ->description('Criação do primeiro Curso e Turma')
                        ->icon('heroicon-m-academic-cap')
                        ->components([
                            Section::make()
                                ->columns(2)
                                ->components([
                                    TextInput::make('curso_nome')
                                        ->label('Primeiro Curso')
                                        ->placeholder('Ex: Ensino Fundamental I')
                                        ->required(),
                                    TextInput::make('turma_nome')
                                        ->label('Primeira Turma')
                                        ->placeholder('Ex: 1º Ano A - Manhã')
                                        ->required(),
                                ]),
                        ]),
                ])
                    ->submitAction(
                        Action::make('save')
                            ->label('Salvar Configuração Inicial')
                            ->color('success')
                            ->icon('heroicon-m-sparkles')
                            ->action('save')
                    ),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $raw = $this->form->getState();

        try {
            DB::beginTransaction();

            // 1. Criar Unidade
            $unidade = Unidade::create([
                'nome' => $raw['unidade_nome'],
            ]);

            // 2. Criar Período Letivo
            $periodo = PeriodoLetivo::create([
                'nome' => $raw['periodo_nome'],
                'data_inicio' => $raw['periodo_inicio'],
                'data_fim' => $raw['periodo_fim'],
            ]);

            // 3. Criar Curso
            $curso = Curso::create([
                'nome_interno' => $raw['curso_nome'],
                'nome_externo' => $raw['curso_nome'],
                'unidade_id' => $unidade->id,
            ]);

            // 4. Criar Série Inicial (As turmas pertencem às séries)
            $serie = Serie::create([
                'nome' => 'Série Inicial',
                'curso_id' => $curso->id,
                'sistema_avaliacao' => 'Nota', // Pode ser Nota, Conceito ou Parecer - adotando Nota por pdarão inicial
            ]);

            // 5. Criar Turma vinculando-a à Série
            Turma::create([
                'nome' => $raw['turma_nome'],
                'serie_id' => $serie->id,
                'periodo_letivo_id' => $periodo->id,
            ]);

            DB::commit();

            Notification::make()
                ->title('Escola configurada com sucesso!')
                ->success()
                ->send();

            $this->redirect('/admin');

        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()
                ->title('Erro na configuração')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
