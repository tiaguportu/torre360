<?php

namespace App\Filament\Resources\TemplateDocumentos\Pages;

use App\Enums\ContextoDocumento;
use App\Enums\TipoTemplateDocumento;
use App\Filament\Resources\TemplateDocumentos\Schemas\TemplateDocumentoForm;
use App\Filament\Resources\TemplateDocumentos\TemplateDocumentoResource;
use App\Models\TemplateDocumento;
use App\Services\Documentos\MacrosDocumentoService;
use App\Services\Documentos\RegistrosDoContexto;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;

class EditTemplateDocumento extends EditRecord
{
    protected static string $resource = TemplateDocumentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->previaAction(),
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Modelo de Documento')
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

    /**
     * Mostra o documento com os dados de um registro de verdade, usando o texto que está na tela (mesmo sem salvar), para
     * conferir as variáveis antes de emitir ou mandar para assinatura.
     */
    private function previaAction(): Action
    {
        return Action::make('previa')
            ->label('Prévia')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading('Prévia do modelo')
            ->modalDescription('Escolha um registro para ver como o documento fica com os dados dele. A prévia usa o texto que está na tela, mesmo sem salvar.')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalWidth('4xl')
            ->schema(fn (): array => [
                Select::make('registro_id')
                    ->label($this->contextoAtual()->getLabel())
                    ->searchable()
                    ->live()
                    ->getSearchResultsUsing(fn (string $search): array => RegistrosDoContexto::buscar($this->contextoAtual(), $search))
                    ->getOptionLabelUsing(fn ($value): ?string => RegistrosDoContexto::rotulo($this->contextoAtual(), $value)),
                Placeholder::make('previa')
                    ->label('')
                    ->content(fn (Get $get): HtmlString => $this->renderizarPrevia($get('registro_id'))),
            ]);
    }

    private function tipoAtual(): TipoTemplateDocumento
    {
        return TemplateDocumentoForm::tipo(data_get($this->data, 'tipo')) ?? $this->getRecord()->tipo;
    }

    private function contextoAtual(): ContextoDocumento
    {
        return $this->tipoAtual()->contexto();
    }

    private function renderizarPrevia(mixed $registroId): HtmlString
    {
        if (! $registroId) {
            return new HtmlString('<p class="text-sm text-gray-500">Escolha um registro acima.</p>');
        }

        $contexto = $this->contextoAtual();
        $registro = $contexto->modelo()::query()->find($registroId);

        if (! $registro) {
            return new HtmlString('<p class="text-sm text-danger-600">Registro não encontrado.</p>');
        }

        $modelo = new TemplateDocumento([
            'tipo' => $this->tipoAtual(),
            'conteudo' => (string) data_get($this->data, 'conteudo', ''),
            'validade_dias' => (int) data_get($this->data, 'validade_dias', 30),
        ]);

        try {
            $html = app(MacrosDocumentoService::class)->preencher($modelo, $registro);
        } catch (\Throwable $e) {
            return new HtmlString('<p class="text-sm text-danger-600">Não foi possível montar a prévia: '.e($e->getMessage()).'</p>');
        }

        // O texto já passou pelo sanitizador; o fundo branco imita a folha.
        return new HtmlString('<div class="rounded-lg border border-gray-200 bg-white p-6 text-sm text-gray-900" style="max-height: 60vh; overflow: auto;">'.$html.'</div>');
    }

    private function getHelpContent(): string
    {
        $html = '<p>Edite os textos e parâmetros deste modelo de documento.</p>';
        $html .= '<p><strong>Atenção:</strong> Alterar o texto deste modelo afetará apenas as novas emissões geradas a partir de agora. Documentos e PDFs já emitidos anteriormente mantêm o conteúdo original arquivado para fins de conformidade e auditoria; o mesmo vale para os documentos já enviados para assinatura eletrônica, que guardam a cópia do que foi enviado.</p>';
        $html .= '<h4>Variáveis</h4>';
        $html .= '<p>Cada tipo de documento tem as suas variáveis (veja o guia na primeira aba). O sistema recusa ao salvar uma variável que não existe para o tipo, porque ela sairia impressa do jeito que foi escrita.</p>';
        $html .= '<h4>Prévia</h4>';
        $html .= '<p>O botão <strong>Prévia</strong> mostra o documento com os dados de um aluno, de um cancelamento ou de um funcionário, conforme o tipo, usando o texto que está na tela.</p>';

        return $html;
    }
}
