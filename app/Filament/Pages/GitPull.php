<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Process;

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

        $result = Process::run('git pull origin main');

        if ($result->successful()) {
            Process::run('php artisan optimize:clear');
            Process::run('php artisan migrate --force');

            Notification::make()
                ->title('Sistema Atualizado com Sucesso')
                ->body($result->output())
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Erro na Atualização')
                ->body($result->errorOutput())
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
        $html = '<p>Esta página permite atualizar o sistema em produção, buscando as mudanças mais recentes do repositório Git (branch <strong>main</strong>).</p>';

        $html .= '<h3>Como usar?</h3><ul>';
        $html .= '<li><strong>Executar Git Pull Origin Main:</strong> Busca as últimas alterações do repositório e, se a atualização for bem-sucedida, executa automaticamente a limpeza de cache (<code>optimize:clear</code>) e as migrações pendentes do banco de dados (<code>migrate --force</code>).</li>';
        $html .= '</ul>';

        $html .= '<h3>Dicas importantes</h3><ul>';
        $html .= '<li>Esta é uma ação sensível e afeta o sistema em produção para todos os usuários. Utilize com cautela.</li>';
        $html .= '<li>Acesso restrito a usuários com o papel de Super Administrador.</li>';
        $html .= '</ul>';

        return $html;
    }
}
