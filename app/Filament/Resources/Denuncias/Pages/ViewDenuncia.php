<?php

namespace App\Filament\Resources\Denuncias\Pages;

use App\Enums\StatusDenuncia;
use App\Enums\TipoMovimentacaoDenuncia;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Denuncias\DenunciaResource;
use App\Models\Denuncia;
use App\Services\OuvidoriaService;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewDenuncia extends ViewRecord
{
    use HasAjudaAction;

    protected static string $resource = DenunciaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Manifestação da ouvidoria', $this->getHelpContent()),

            Action::make('assumir')
                ->label('Assumir a apuração')
                ->icon('heroicon-o-hand-raised')
                ->visible(fn (): bool => $this->podeTratar() && $this->record->responsavel_user_id === null && ! $this->record->status->estaEncerrada())
                ->action(function (): void {
                    $assumiu = app(OuvidoriaService::class)->assumir($this->record, auth()->user());

                    Notification::make()->title($assumiu ? 'Você assumiu a apuração.' : 'Outra pessoa assumiu antes de você.')->{$assumiu ? 'success' : 'warning'}()->send();
                    $this->refreshFormData(['status', 'responsavel_user_id']);
                }),

            Action::make('registrar')
                ->label('Registrar andamento')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->visible(fn (): bool => $this->podeTratar() && ! $this->record->status->estaEncerrada())
                ->form([
                    Select::make('tipo')
                        ->label('Tipo')
                        ->options([
                            TipoMovimentacaoDenuncia::ParecerInterno->value => TipoMovimentacaoDenuncia::ParecerInterno->getLabel().' (só o comitê vê)',
                            TipoMovimentacaoDenuncia::Devolutiva->value => TipoMovimentacaoDenuncia::Devolutiva->getLabel().' (o autor lê pelo código)',
                            TipoMovimentacaoDenuncia::ConselhoTutelar->value => TipoMovimentacaoDenuncia::ConselhoTutelar->getLabel().' (só o comitê vê)',
                        ])
                        ->default(TipoMovimentacaoDenuncia::ParecerInterno->value)
                        ->required(),
                    Textarea::make('texto')->label('Texto')->rows(5)->required(),
                ])
                ->action(function (array $data): void {
                    $tipo = $data['tipo'] instanceof TipoMovimentacaoDenuncia ? $data['tipo'] : TipoMovimentacaoDenuncia::from($data['tipo']);

                    app(OuvidoriaService::class)->movimentar($this->record, auth()->user(), $tipo, $data['texto']);

                    Notification::make()->title('Andamento registrado.')->success()->send();
                }),

            Action::make('encerrar')
                ->label('Encerrar')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => $this->podeTratar() && ! $this->record->status->estaEncerrada())
                ->form([
                    Select::make('status')
                        ->label('Como encerrar')
                        ->options([StatusDenuncia::Concluida->value => 'Concluída (houve apuração)', StatusDenuncia::Arquivada->value => 'Arquivada (sem elementos)'])
                        ->default(StatusDenuncia::Concluida->value)
                        ->required(),
                    Textarea::make('conclusao')
                        ->label('Conclusão para o autor')
                        ->helperText('O autor lê esta mensagem pelo protocolo e código. Não cite nomes de terceiros nem dados que identifiquem outra pessoa.')
                        ->rows(5)
                        ->required(),
                    Toggle::make('confirmo')->label('Conferi o relato e a conclusão')->accepted()->dehydrated(false),
                ])
                ->action(function (array $data): void {
                    $status = $data['status'] instanceof StatusDenuncia ? $data['status'] : StatusDenuncia::from($data['status']);

                    try {
                        app(OuvidoriaService::class)->encerrar($this->record, auth()->user(), $status, $data['conclusao']);
                    } catch (\DomainException $e) {
                        Notification::make()->title('Não foi possível encerrar')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Manifestação encerrada.')->success()->send();
                    $this->refreshFormData(['status', 'conclusao', 'encerrada_em']);
                }),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('🛡️', 'Manifestação da ouvidoria', 'Leia o relato, assuma a apuração e registre cada passo até encerrar.')
            ->passos('🚀 O que fazer aqui', [
                '"Assumir a apuração" deixa a manifestação com você e muda a situação para "Em apuração".',
                '"Registrar andamento": parecer interno e comunicação ao Conselho Tutelar só o comitê vê; a devolutiva o autor lê pelo protocolo e código.',
                '"Encerrar" pede a conclusão, que o autor lê. Não cite nomes de terceiros nela.',
            ])
            ->dica('Suspeita de violência contra criança ou adolescente tem prazo de 2 dias e pede a comunicação ao Conselho Tutelar, no procedimento definido com o jurídico da escola.');
    }

    private function podeTratar(): bool
    {
        /** @var Denuncia $denuncia */
        $denuncia = $this->record;

        return (bool) auth()->user()?->can('Tratar:Denuncia') && $denuncia->podeSerVistaPor(auth()->user());
    }
}
