@if(auth()->user()?->hasRole('super_admin'))
    <div class="flex items-center gap-x-3 pe-3">
        <a 
            href="{{ \App\Filament\Pages\GitPull::getUrl() }}" 
            title="Sincronizar Repositório (Git Pull)"
            class="flex items-center justify-center h-10 w-10 rounded-full bg-primary-50 dark:bg-primary-900/10 text-primary-600 dark:text-primary-400 hover:bg-primary-600 hover:text-white dark:hover:bg-primary-500 transition-all duration-200 shadow-sm border border-primary-100 dark:border-primary-800"
        >
            <x-filament::icon
                icon="heroicon-o-arrow-path"
                class="h-5 w-5"
            />
        </a>
    </div>
@endif
