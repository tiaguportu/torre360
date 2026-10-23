<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentosAssinatura\Actions;

use App\Filament\Resources\DocumentosAssinatura\DocumentoAssinaturaResource;
use App\Models\DocumentoAssinatura;
use App\Models\HistoricoEscolar;
use App\Models\SolicitacaoDocumento;
use App\Services\Documentos\DocumentosOficiaisService;
use App\Support\TiposArquivo;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Ações de assinatura digital da declaração e do histórico escolar (Onda 74), iguais na lista de declarações e na de
 * históricos. Cada uma confere a permissão e o estado do documento: o super admin passa por qualquer habilidade
 * (Gate::before), então a política sozinha não esconde "Anexar" de um documento já assinado.
 */
final class AcoesAssinaturaOficial
{
    private static function servico(): DocumentosOficiaisService
    {
        return app(DocumentosOficiaisService::class);
    }

    /**
     * Documento ainda sem cópia assinada, sem assinatura em andamento e com PDF emitido.
     */
    private static function podeAssinar(SolicitacaoDocumento|HistoricoEscolar $record): bool
    {
        return self::servico()->impedimento($record) === null
            && ! $record->documentosAssinatura()->abertos()->exists();
    }

    public static function enviar(): Action
    {
        return Action::make('assinar_pelo_assinafy')
            ->label('Enviar para assinatura')
            ->icon('heroicon-o-pencil-square')
            ->color('info')
            ->visible(fn (SolicitacaoDocumento|HistoricoEscolar $record): bool => self::podeAssinar($record) && (bool) auth()->user()?->can('Create:DocumentoAssinatura'))
            ->modalHeading('Enviar para a assinatura da direção')
            ->modalDescription('O PDF emitido vira um documento para assinatura eletrônica no Assinafy. Você confere a lista, e na tela do documento envia o e-mail aos assinantes.')
            ->modalSubmitActionLabel('Montar o documento')
            ->fillForm(fn (SolicitacaoDocumento|HistoricoEscolar $record): array => ['assinantes' => self::servico()->signatariosSugeridos($record)['assinantes']])
            ->schema([
                Repeater::make('assinantes')
                    ->label('Quem assina')
                    ->schema([
                        TextInput::make('nome')->label('Nome')->required()->maxLength(160),
                        TextInput::make('email')->label('E-mail')->email()->required()->maxLength(160),
                        TextInput::make('papel')->label('Cargo')->maxLength(40),
                    ])
                    ->columns(3)
                    ->minItems(1)
                    ->maxItems(10)
                    ->addActionLabel('Adicionar assinante')
                    ->helperText('Representantes legais da unidade com e-mail cadastrado. Ajuste se a assinatura for de outra pessoa.'),
            ])
            ->action(function (SolicitacaoDocumento|HistoricoEscolar $record, array $data) {
                try {
                    $documento = self::servico()->enviarParaAssinatura($record, $data['assinantes'] ?? [], auth()->user());
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível montar o documento')->body($e->getMessage())->danger()->persistent()->send();

                    return null;
                }

                return redirect(DocumentoAssinaturaResource::getUrl('view', ['record' => $documento]));
            });
    }

    public static function anexar(): Action
    {
        return Action::make('anexar_assinado')
            ->label('Anexar PDF assinado')
            ->icon('heroicon-o-shield-check')
            ->color('success')
            ->visible(fn (SolicitacaoDocumento|HistoricoEscolar $record): bool => self::podeAssinar($record) && (bool) auth()->user()?->can('AnexarAssinado:DocumentoAssinatura'))
            ->modalHeading('Anexar o PDF assinado digitalmente')
            ->modalDescription('Assine o PDF fora do sistema, com o certificado digital da escola (ICP-Brasil A1 ou A3) ou no gov.br, e anexe aqui o arquivo assinado. O sistema lê o que o PDF traz; ele não valida o certificado.')
            ->modalSubmitActionLabel('Anexar')
            ->schema(fn (SolicitacaoDocumento|HistoricoEscolar $record): array => [
                FileUpload::make('arquivo')
                    ->label('PDF assinado')
                    ->acceptedFileTypes(TiposArquivo::pdf())
                    ->maxSize((int) (DocumentosOficiaisService::TAMANHO_MAXIMO_BYTES / 1024))
                    ->storeFiles(false)
                    ->required(),
                Toggle::make('conferi')
                    ->label('Conferi que este PDF é o documento de '.self::servico()->nomeDoAluno($record))
                    ->accepted()
                    ->validationMessages(['accepted' => 'Confirme que o arquivo é o documento deste aluno.'])
                    ->required(),
            ])
            ->action(function (SolicitacaoDocumento|HistoricoEscolar $record, array $data): void {
                $arquivo = $data['arquivo'] ?? null;
                $bytes = is_object($arquivo) && method_exists($arquivo, 'get') ? $arquivo->get() : null;

                if (! is_string($bytes) || $bytes === '') {
                    Notification::make()->title('Não foi possível ler o arquivo')->body('Envie o PDF de novo.')->danger()->send();

                    return;
                }

                try {
                    $documento = self::servico()->anexarAssinado($record, $bytes, auth()->user());
                } catch (\DomainException $e) {
                    Notification::make()->title('PDF não aceito')->body($e->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()
                    ->title('Cópia assinada guardada')
                    ->body(($documento->nivel_assinatura?->getLabel() ?? 'Assinatura').' encontrada no arquivo. A validade do certificado não foi conferida pelo sistema.')
                    ->success()
                    ->send();
            });
    }

    public static function baixarAssinada(): Action
    {
        return Action::make('baixar_assinada')
            ->label('Baixar cópia assinada')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (SolicitacaoDocumento|HistoricoEscolar $record): bool => $record->temCopiaAssinada() && Storage::disk('local')->exists((string) $record->arquivo_assinado_path))
            ->url(fn (SolicitacaoDocumento|HistoricoEscolar $record): string => route('documentos.visualizar', ['path' => $record->arquivo_assinado_path]))
            ->openUrlInNewTab();
    }

    public static function verAssinatura(): Action
    {
        return Action::make('ver_assinatura')
            ->label('Ver assinatura')
            ->icon('heroicon-o-document-magnifying-glass')
            ->color('gray')
            ->visible(function (SolicitacaoDocumento|HistoricoEscolar $record): bool {
                return (bool) auth()->user()?->can('ViewAny:DocumentoAssinatura') && $record->documentosAssinatura()->exists();
            })
            ->url(function (SolicitacaoDocumento|HistoricoEscolar $record): string {
                /** @var DocumentoAssinatura $documento */
                $documento = $record->documentosAssinatura()->first();

                return DocumentoAssinaturaResource::getUrl('view', ['record' => $documento]);
            });
    }
}
