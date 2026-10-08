<?php

namespace App\Filament\Portal\Pages;

use App\Enums\StatusConsentimento;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Portal\Concerns\InteractsWithMatriculaSelecionada;
use App\Models\ConsentimentoMatricula;
use App\Models\TipoConsentimento;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

class Consentimentos extends Page
{
    use HasAjudaAction;
    use InteractsWithMatriculaSelecionada;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?string $title = 'Consentimentos';

    protected static ?string $slug = 'consentimentos';

    protected string $view = 'filament.portal.pages.consentimentos';

    public function mount(): void
    {
        $this->matriculaId = $this->getMatriculaSelecionada()?->id;
    }

    /**
     * Para cada tipo de consentimento ativo, garante (sem persistir até a resposta real
     * acontecer em outro tipo) um registro para a matrícula selecionada — assim a família
     * sempre vê todos os tipos vigentes, mesmo um recém-cadastrado pela secretaria.
     *
     * @return Collection<int, ConsentimentoMatricula>
     */
    public function getConsentimentosProperty(): Collection
    {
        $matricula = $this->getMatriculaSelecionada();

        if (! $matricula) {
            return collect();
        }

        return TipoConsentimento::query()
            ->where('is_ativo', true)
            ->get()
            ->map(fn (TipoConsentimento $tipo): ConsentimentoMatricula => ConsentimentoMatricula::localizarOuPendente($matricula, $tipo));
    }

    public function responder(int $consentimentoId, string $status): void
    {
        $consentimento = ConsentimentoMatricula::with('tipoConsentimento')->findOrFail($consentimentoId);

        if (! $this->getMatriculasAcessiveis()->has($consentimento->matricula_id)) {
            Notification::make()->title('Você não tem permissão para esta ação.')->danger()->send();

            return;
        }

        $consentimento->responder(StatusConsentimento::from($status), auth()->user(), request()->ip());

        Notification::make()
            ->title('Resposta registrada com sucesso!')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Consentimentos', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('🛡️', 'Consentimentos', 'Autorize ou não cada tipo de consentimento do aluno (ex.: uso de imagem).')
            ->secao('🎯 O que você pode fazer?', [
                ['📖', 'Ler o termo', 'Veja o texto completo antes de responder.'],
                ['✅', 'Autorizar', 'Registra sua autorização, com data e validade quando aplicável.'],
                ['❌', 'Não autorizar', 'Registra a recusa — pode ser alterada depois, a qualquer momento.'],
            ])
            ->dica('Consentimentos com renovação periódica voltam a pedir sua resposta automaticamente quando a validade vencer.');
    }
}
