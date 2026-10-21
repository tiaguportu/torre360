<?php

namespace App\Filament\Resources\BolsaConcedidas\Pages;

use App\Enums\StatusBolsa;
use App\Filament\Resources\BolsaConcedidas\BolsaConcedidaResource;
use App\Models\TipoBolsa;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateBolsaConcedida extends CreateRecord
{
    protected static string $resource = BolsaConcedidaResource::class;

    /**
     * Quando o tipo de bolsa não exige aprovação, a concessão já nasce aprovada.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tipoBolsa = TipoBolsa::find($data['tipo_bolsa_id']);

        if ($tipoBolsa && ! $tipoBolsa->exige_aprovacao) {
            $data['status'] = StatusBolsa::Aprovada->value;
            $data['aprovado_por_user_id'] = auth()->id();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Cadastrar Bolsa Concedida')
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
        $user = auth()->user();
        $html = '<p class="text-sm text-gray-600 dark:text-gray-300">Preencha os campos abaixo para cadastrar um novo registro de <strong>Bolsa Concedida</strong>.</p>';

        if ($user && $user->can('Create:BolsaConcedida')) {
            $html .= '<h4 class="font-semibold text-gray-800 dark:text-gray-100 mt-2 text-sm">Instruções de Preenchimento:</h4>';
            $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';
            $html .= '<li>Preencha todos os campos obrigatórios sinalizados antes de submeter.</li>';
            $html .= '<li>Utilize <strong>Criar</strong> para salvar o registro ou <strong>Criar e criar outro</strong> para novos cadastros contínuos.</li>';
            $html .= '</ul>';
        }

        return $html;
    }
}
