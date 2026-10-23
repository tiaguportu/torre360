<?php

declare(strict_types=1);

namespace App\Filament\Resources\CasosFrequencia\Pages;

use App\Enums\MeioContatoFamilia;
use App\Enums\ResultadoContatoFamilia;
use App\Enums\TipoTemplateDocumento;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\CasosFrequencia\CasoFrequenciaResource;
use App\Filament\Resources\DocumentosAssinatura\DocumentoAssinaturaResource;
use App\Models\CasoFrequencia;
use App\Models\ConselhoTutelar;
use App\Models\TemplateDocumento;
use App\Services\Documentos\AssinaturaDocumentoService;
use App\Services\Documentos\SignatariosSugeridosService;
use App\Services\FaltasExcessivasService;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Carbon;

class ViewCasoFrequencia extends ViewRecord
{
    use HasAjudaAction;

    protected static string $resource = CasoFrequenciaResource::class;

    private function caso(): CasoFrequencia
    {
        /** @var CasoFrequencia $caso */
        $caso = $this->getRecord();

        return $caso;
    }

    private function servico(): FaltasExcessivasService
    {
        return app(FaltasExcessivasService::class);
    }

    /**
     * O estado do caso mudou: recarrega o registro e as relações, de que os botões do cabeçalho dependem.
     */
    private function recarregar(): void
    {
        $this->getRecord()->refresh()->load(['conselhoTutelar', 'documentosAssinatura'])->loadCount('contatos');
        $this->refreshFormData(['status', 'faltas', 'faltas_justificadas', 'percentual_faltas', 'limite_aulas', 'aulas_previstas', 'notificado_em', 'protocolo_externo', 'resposta', 'respondido_em', 'encerrado_em', 'encerramento_motivo']);
    }

    /**
     * Executa uma regra do serviço, mostra o erro de negócio como aviso e recarrega a tela.
     */
    private function executar(callable $acao, string $sucesso, ?string $corpo = null): void
    {
        try {
            $acao();
        } catch (\DomainException $e) {
            Notification::make()->title('Não foi possível concluir')->body($e->getMessage())->danger()->persistent()->send();

            return;
        }

        Notification::make()->title($sucesso)->body($corpo)->success()->send();
        $this->recarregar();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('contato')
                ->label('Registrar contato')
                ->icon('heroicon-o-phone')
                ->color('primary')
                ->visible(fn (): bool => $this->caso()->estaAberto() && (bool) auth()->user()?->can('acompanhar', $this->caso()))
                ->modalHeading('Contato com a família')
                ->modalDescription('Registre cada tentativa, inclusive as que não deram resultado: é a prova de que a escola procurou a família antes de comunicar o Conselho Tutelar.')
                ->schema([
                    DatePicker::make('data')->label('Data')->native(false)->default(today())->maxDate(today())->required(),
                    Select::make('meio')->label('Meio')->options(MeioContatoFamilia::class)->required(),
                    Select::make('resultado')->label('Resultado')->options(ResultadoContatoFamilia::class)->required(),
                    Textarea::make('observacao')
                        ->label('O que a família disse (opcional)')
                        ->helperText('Fica criptografado. Escreva só o necessário.')
                        ->rows(3)
                        ->maxLength(500),
                ])
                ->action(function (array $data): void {
                    $this->executar(fn () => $this->servico()->registrarContato(
                        $this->caso(),
                        Carbon::parse($data['data']),
                        $data['meio'] instanceof MeioContatoFamilia ? $data['meio'] : MeioContatoFamilia::from($data['meio']),
                        $data['resultado'] instanceof ResultadoContatoFamilia ? $data['resultado'] : ResultadoContatoFamilia::from($data['resultado']),
                        $data['observacao'] ?? null,
                        auth()->user(),
                    ), 'Contato registrado');
                }),
            Action::make('atualizar')
                ->label('Atualizar apuração')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => $this->caso()->estaAberto() && (bool) auth()->user()?->can('acompanhar', $this->caso()))
                ->action(fn () => $this->executar(fn () => $this->servico()->atualizarApuracao($this->caso(), auth()->user()), 'Apuração atualizada', 'Os números do caso seguem as faltas de hoje.')),
            $this->oficioAction(),
            Action::make('ver_oficio')
                ->label('Ver ofício')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->visible(fn (): bool => $this->caso()->documentosAssinatura->isNotEmpty() && (bool) auth()->user()?->can('view', $this->caso()->documentosAssinatura->first()))
                ->url(fn (): string => DocumentoAssinaturaResource::getUrl('view', ['record' => $this->caso()->documentosAssinatura->first()])),
            Action::make('notificado')
                ->label('Registrar envio ao Conselho')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn (): bool => $this->caso()->estaAberto() && ! $this->caso()->foiNotificado() && (bool) auth()->user()?->can('enviar', $this->caso()))
                ->modalHeading('Registrar o envio do ofício')
                ->modalDescription('Use depois de entregar ou enviar o ofício ao Conselho Tutelar. O sistema não protocola nada fora da escola.')
                ->schema(fn (): array => [
                    DatePicker::make('quando')->label('Enviado em')->native(false)->default(today())->maxDate(today())->required(),
                    TextInput::make('protocolo')->label('Protocolo ou recibo (opcional)')->maxLength(80),
                    Select::make('conselho')
                        ->label('Conselho Tutelar')
                        ->options(fn (): array => ConselhoTutelar::query()->ativos()->orderBy('nome')->pluck('nome', 'id')->all())
                        ->default($this->caso()->conselho_tutelar_id),
                ])
                ->action(function (array $data): void {
                    $this->executar(fn () => $this->servico()->marcarNotificado(
                        $this->caso(),
                        Carbon::parse($data['quando']),
                        $data['protocolo'] ?? null,
                        filled($data['conselho'] ?? null) ? ConselhoTutelar::query()->find($data['conselho']) : null,
                        auth()->user(),
                    ), 'Envio registrado');
                }),
            Action::make('resposta')
                ->label('Registrar resposta do Conselho')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('gray')
                ->visible(fn (): bool => $this->caso()->estaAberto() && $this->caso()->foiNotificado() && (bool) auth()->user()?->can('enviar', $this->caso()))
                ->schema([
                    DatePicker::make('quando')->label('Respondido em')->native(false)->default(today())->maxDate(today())->required(),
                    Textarea::make('resposta')->label('Resposta')->helperText('Fica criptografada.')->required()->rows(4)->maxLength(2000),
                ])
                ->action(function (array $data): void {
                    $this->executar(fn () => $this->servico()->registrarResposta($this->caso(), (string) $data['resposta'], Carbon::parse($data['quando']), auth()->user()), 'Resposta registrada');
                }),
            Action::make('encerrar')
                ->label('Encerrar caso')
                ->icon('heroicon-o-check-circle')
                ->color('gray')
                ->visible(fn (): bool => $this->caso()->estaAberto() && (bool) auth()->user()?->can('acompanhar', $this->caso()))
                ->modalHeading('Encerrar o caso')
                ->modalDescription('Use quando a frequência se regularizou, o aluno saiu da escola ou o Conselho já respondeu. O histórico fica.')
                ->schema([
                    TextInput::make('motivo')->label('Motivo do encerramento')->required()->maxLength(160),
                ])
                ->action(function (array $data): void {
                    $this->executar(fn () => $this->servico()->encerrar($this->caso(), (string) $data['motivo'], auth()->user()), 'Caso encerrado');
                }),
            $this->ajudaAction('Caso de faltas', HelpContent::make('🛡️', 'Caso de faltas', 'Acompanhe o que a escola já fez por este aluno.')
                ->secao('🎯 Como usar', [
                    ['📞', 'Registrar contato', 'Anote cada tentativa de falar com a família, mesmo a que não deu certo.'],
                    ['🔄', 'Atualizar apuração', 'Use depois de justificar faltas: refaz os números do caso (e do ofício).'],
                    ['📄', 'Gerar ofício', 'Monta o ofício ao Conselho Tutelar com o modelo ativo. Você confere, baixa o PDF ou manda para a assinatura da direção.'],
                    ['✉️', 'Registrar envio', 'Guarda a data e o protocolo. Só depois de pelo menos um contato com a família.'],
                    ['✅', 'Encerrar', 'Quando a situação se resolveu. O histórico permanece.'],
                ])
                ->alerta('O ofício leva só os dados necessários. O que a família disse e a resposta do Conselho ficam criptografados.')),
        ];
    }

    private function oficioAction(): Action
    {
        return Action::make('oficio')
            ->label('Gerar ofício')
            ->icon('heroicon-o-document-plus')
            ->color('info')
            ->visible(fn (): bool => $this->caso()->estaAberto()
                && (bool) auth()->user()?->can('Create:DocumentoAssinatura')
                && ! $this->caso()->documentosAssinatura->contains(fn ($documento): bool => $documento->emAndamento() || $documento->estaAssinado()))
            ->modalHeading('Gerar o ofício ao Conselho Tutelar')
            ->modalDescription('O sistema monta o ofício com o modelo ativo e leva você ao documento, onde confere o texto, baixa o PDF ou manda para a assinatura da direção.')
            ->modalSubmitActionLabel('Montar o ofício')
            ->action(function () {
                $modelo = TemplateDocumento::query()->where('tipo', TipoTemplateDocumento::OficioConselhoTutelar->value)->where('is_ativo', true)->first();

                if (! $modelo) {
                    Notification::make()
                        ->title('Não há modelo de ofício ativo')
                        ->body('Revise e ative o modelo "Ofício ao Conselho Tutelar" em Modelos de Documentos (o botão "Criar modelos padrão" traz um ponto de partida).')
                        ->warning()
                        ->persistent()
                        ->send();

                    return null;
                }

                try {
                    $sugestao = app(SignatariosSugeridosService::class)->para($this->caso());
                    $documento = app(AssinaturaDocumentoService::class)->criar($modelo, $this->caso(), $sugestao['assinantes'], auth()->user());
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível montar o ofício')->body($e->getMessage().' Cadastre o representante legal da unidade (com e-mail) para a direção assinar.')->danger()->persistent()->send();

                    return null;
                }

                return redirect(DocumentoAssinaturaResource::getUrl('view', ['record' => $documento]));
            });
    }
}
