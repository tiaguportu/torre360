<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Process;

class GitPull extends Page
{
    protected static $navigationIcon = 'heroicon-o-arrow-path';

    protected static string $view = 'filament.pages.git-pull';

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
            Notification::make()
                ->title('Sistema Atualizado')
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
}
