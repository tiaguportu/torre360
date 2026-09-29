<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Throwable;

class GitPull extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-path';

    protected string $view = 'filament.pages.git-pull';

    protected static ?string $title = 'Atualizar Sistema (Git Pull)';

    protected static ?string $navigationLabel = 'Git Pull';

    protected static ?string $slug = 'git-pull';

    protected static bool $shouldRegisterNavigation = false;

    public function runGitPull(): void
    {
        if (! auth()->user()->hasRole('super_admin')) {
            Notification::make()
                ->title('Acesso Negado')
                ->danger()
                ->send();

            return;
        }

        $result = Process::path(base_path())->run('git pull origin main');

        if (! $result->successful()) {
            Notification::make()
                ->title('Erro ao Executar Git Pull')
                ->body($result->errorOutput() ?: $result->output())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $pullOutput = trim($result->output());
        $migrateOutput = '';
        $migrateSuccess = true;

        try {
            Artisan::call('optimize:clear');
            Artisan::call('migrate', ['--force' => true]);
            $migrateOutput = trim(Artisan::output());
        } catch (Throwable $e) {
            $migrateSuccess = false;
            $migrateOutput = $e->getMessage();
        }

        if ($migrateSuccess) {
            Notification::make()
                ->title('Sistema Atualizado com Sucesso')
                ->body("Git Pull:\n{$pullOutput}\n\nMigrações:\n".($migrateOutput ?: 'Nenhuma migração pendente.'))
                ->success()
                ->persistent()
                ->send();
        } else {
            Notification::make()
                ->title('Git atualizado, mas falhou ao executar migrações!')
                ->body("Git Pull:\n{$pullOutput}\n\nErro nas Migrações:\n{$migrateOutput}")
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function runMigrate(): void
    {
        if (! auth()->user()->hasRole('super_admin')) {
            Notification::make()
                ->title('Acesso Negado')
                ->danger()
                ->send();

            return;
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = trim(Artisan::output());

            Notification::make()
                ->title('Migrações Executadas com Sucesso')
                ->body($output ?: 'O banco de dados já se encontra atualizado com todas as tabelas.')
                ->success()
                ->persistent()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Erro ao Executar Migrações')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function runOptimizeClear(): void
    {
        if (! auth()->user()->hasRole('super_admin')) {
            Notification::make()
                ->title('Acesso Negado')
                ->danger()
                ->send();

            return;
        }

        try {
            Artisan::call('optimize:clear');
            $output = trim(Artisan::output());

            Notification::make()
                ->title('Caches Limpos com Sucesso')
                ->body($output ?: 'Caches do sistema limpos com sucesso.')
                ->success()
                ->persistent()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Erro ao Limpar Caches')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Atualizar Sistema (Git Pull)')
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
        $html = '<div class="space-y-4">';
        $html .= '<p class="text-sm text-gray-600 dark:text-gray-300">Esta página permite atualizar o sistema e o banco de dados em produção (branch <strong>main</strong>).</p>';

        $html .= '<h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Ações disponíveis:</h4>';
        $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';
        $html .= '<li><strong>Atualizar Sistema (Git Pull):</strong> Baixa o código mais recente, executa a limpeza de caches e roda automaticamente as migrações do banco de dados (<code>migrate --force</code>).</li>';
        $html .= '<li><strong>Executar Migrações do Banco:</strong> Executa diretamente as migrações pendentes do Laravel via Artisan nativo, ideal quando uma tabela ou coluna nova foi adicionada.</li>';
        $html .= '<li><strong>Limpar Caches:</strong> Executa a limpeza de caches de rotas, configurações, visualizações e Filament (<code>optimize:clear</code>).</li>';
        $html .= '</ul>';

        $html .= '<h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Segurança:</h4>';
        $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';
        $html .= '<li>Acesso estritamente restrito a usuários com o perfil de <strong>Super Administrador</strong>.</li>';
        $html .= '</ul></div>';

        return $html;
    }
}
