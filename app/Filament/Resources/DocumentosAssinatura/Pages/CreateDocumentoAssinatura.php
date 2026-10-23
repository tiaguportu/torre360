<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentosAssinatura\Pages;

use App\Enums\ContextoDocumento;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\DocumentosAssinatura\DocumentoAssinaturaResource;
use App\Models\DocumentoAssinatura;
use App\Models\TemplateDocumento;
use App\Services\Documentos\AssinaturaDocumentoService;
use App\Support\HelpContent;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class CreateDocumentoAssinatura extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = DocumentoAssinaturaResource::class;

    protected static bool $canCreateAnother = false;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Novo documento para assinatura', HelpContent::make('✍️', 'Novo documento para assinatura', 'Monte o documento a partir de um modelo e confira antes de enviar.')
                ->secao('🎯 Como preencher', [
                    ['📄', 'Modelo', 'Só aparecem modelos ativos. O texto e as variáveis (nome do aluno, valores, datas) vêm do modelo.'],
                    ['🎓', 'Aluno, cancelamento ou funcionário', 'Depende do modelo escolhido. É de onde o sistema tira os dados para preencher o texto.'],
                    ['✍️', 'Quem assina', 'O sistema sugere a família e o representante da escola (ou o funcionário). Ajuste a lista: cada pessoa precisa de um e-mail que ela use.'],
                    ['📅', 'Prazo', 'Opcional. Passado o prazo sem todas as assinaturas, o documento expira.'],
                ])
                ->dica('Salvar só monta o PDF; nada vai para o Assinafy ainda. Abra o documento para conferir e clique em "Enviar para assinatura".')),
        ];
    }

    /**
     * Passa pelo serviço, que confere o modelo, o registro e os assinantes, monta o PDF e registra o histórico.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $modelo = TemplateDocumento::query()->findOrFail($data['template_documento_id']);
        $contexto = $modelo->contexto();

        if ($contexto === ContextoDocumento::Funcionario && ! auth()->user()?->can('View:ProcessoFuncionario')) {
            Notification::make()->title('Sem permissão')->body('Documentos de funcionário são do RH: peça a quem vê Admissão e Desligamento.')->danger()->send();

            $this->halt();
        }

        $registro = $contexto->modelo()::query()->find($data['documentavel_id']);

        if (! $registro) {
            Notification::make()->title('Registro não encontrado')->body('Escolha de novo de quem são os dados do documento.')->danger()->send();

            $this->halt();
        }

        try {
            return app(AssinaturaDocumentoService::class)->criar(
                $modelo,
                $registro,
                array_values($data['assinantes'] ?? []),
                auth()->user(),
                $data['titulo'] ?? null,
                filled($data['expira_em'] ?? null) ? Carbon::parse($data['expira_em']) : null,
            );
        } catch (\DomainException $e) {
            Notification::make()->title('Não foi possível criar o documento')->body($e->getMessage())->danger()->persistent()->send();

            $this->halt();
        }

        // halt() lança a exceção que interrompe a criação; este retorno só satisfaz a assinatura.
        return new DocumentoAssinatura;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Documento criado')
            ->body('Confira o texto e os assinantes e clique em "Enviar para assinatura".');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
