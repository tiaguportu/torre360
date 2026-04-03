<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Form;
use Filament\Pages\SimplePage;
use Illuminate\Contracts\Support\Htmlable;

class AcceptTerms extends SimplePage
{
    protected string $view = 'filament.pages.accept-terms';

    protected static ?string $slug = 'accept-terms';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public function mount(): void
    {
        if (auth()->user()->terms_accepted_at) {
            redirect()->to(url('/admin'));
        }

        $this->form->fill();
    }

    public function getHeading(): string|Htmlable
    {
        return 'Aceitação Obrigatória';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Para continuar utilizando o Torre360, você precisa concordar com os nossos Termos e Condições de Uso.';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Checkbox::make('terms')
                    ->label('Eu li e concordo com os Termos e Condições de Uso.')
                    ->required(),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('accept')
                ->label('Concordar e Continuar')
                ->submit('accept'),
        ];
    }

    public function accept(): void
    {
        /** @var User $user */
        $user = auth()->user();

        $this->form->validate();

        $user->update([
            'terms_accepted_at' => now(),
        ]);

        redirect()->to(url('/admin'));
    }
}
