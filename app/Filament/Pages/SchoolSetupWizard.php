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
use Filament\Forms\Components\ViewField;
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Assistente de Configuração Escolar')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $html = '<p>O <strong>Assistente de Configuração Escolar</strong> guia você em 3 etapas para realizar a configuração inicial do sistema, criando a primeira Unidade, o Período Letivo, o Curso e a Turma.</p>';

        $html .= '<h3>Etapas</h3><ol>';
        $html .= '<li><strong>A Escola:</strong> Informe o nome da Unidade/Escola sede.</li>';
        $html .= '<li><strong>Calendário:</strong> Defina o nome do ano letivo e as datas de início e término previsto das aulas.</li>';
        $html .= '<li><strong>Estrutura de Ensino:</strong> Cadastre o primeiro Curso e a primeira Turma do sistema.</li>';
        $html .= '</ol>';

        $html .= '<h3>Dicas importantes</h3><ul>';
        $html .= '<li>Este assistente é destinado apenas à configuração inicial do sistema, quando ainda não existem unidades cadastradas.</li>';
        $html .= '<li>Após salvar, você será redirecionado para o painel principal, onde poderá complementar os cadastros criados.</li>';
        $html .= '</ul>';

        return $html;
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
