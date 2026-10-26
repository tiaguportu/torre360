<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orcamentos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Orcamentos\OrcamentoResource;
use App\Models\Orcamento;
use App\Services\OrcamentoService;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrcamento extends ViewRecord
{
    use HasAjudaAction;

    protected static string $resource = OrcamentoResource::class;

    private function orcamento(): Orcamento
    {
        /** @var Orcamento $orcamento */
        $orcamento = $this->getRecord();

        return $orcamento;
    }

    private function servico(): OrcamentoService
    {
        return app(OrcamentoService::class);
    }

    /**
     * @param  callable(): mixed  $acao
     */
    private function executar(callable $acao, string $sucesso, ?string $titulo = null): mixed
    {
        try {
            $resultado = $acao();
        } catch (\DomainException $e) {
            Notification::make()->title($titulo ?? 'Não foi possível concluir')->body($e->getMessage())->danger()->persistent()->send();

            return null;
        }

        Notification::make()->title($sucesso)->success()->send();

        return $resultado;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('planilha')
                ->label(fn (): string => $this->orcamento()->ehRascunho() ? 'Preencher planilha' : 'Ver planilha')
                ->icon('heroicon-o-table-cells')
                ->color('primary')
                ->url(fn (): string => OrcamentoResource::getUrl('planilha', ['record' => $this->orcamento()])),
            Action::make('importar')
                ->label('Importar planilha (CSV)')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->visible(fn (): bool => $this->orcamento()->ehRascunho() && (bool) auth()->user()?->can('update', $this->orcamento()))
                ->modalHeading('Importar valores previstos')
                ->modalDescription('Colunas: código da conta do plano; centro de custo (opcional); e os 12 meses (jan a dez). Valores com vírgula ou ponto. Quem já tem valor no mês é atualizado.')
                ->schema([
                    FileUpload::make('arquivo')
                        ->label('Arquivo CSV')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                        ->maxSize(512)
                        ->storeFiles(false)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    abort_unless(auth()->user()?->can('update', $this->orcamento()), 403);

                    $arquivo = $data['arquivo'] ?? null;
                    $conteudo = is_object($arquivo) && method_exists($arquivo, 'get') ? $arquivo->get() : null;

                    if (! is_string($conteudo) || $conteudo === '') {
                        Notification::make()->title('Não foi possível ler o arquivo')->danger()->send();

                        return;
                    }

                    $resultado = $this->executar(fn (): array => $this->servico()->importarCsv($this->orcamento(), $conteudo), 'Planilha importada', 'Importação recusada');

                    if (is_array($resultado) && $resultado['erros'] !== []) {
                        Notification::make()
                            ->title("{$resultado['linhas']} linha(s) importada(s); ".count($resultado['erros']).' com problema')
                            ->body(implode("\n", array_slice($resultado['erros'], 0, 8)))
                            ->warning()
                            ->persistent()
                            ->send();
                    }
                }),
            Action::make('aprovar')
                ->label('Aprovar')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => $this->orcamento()->ehRascunho() && (bool) auth()->user()?->can('aprovar', $this->orcamento()))
                ->requiresConfirmation()
                ->modalHeading('Aprovar o orçamento')
                ->modalDescription('Depois de aprovado o orçamento não muda: ajustes passam por uma revisão. O aprovado anterior do mesmo ano fica como substituído.')
                ->action(function (): void {
                    $this->executar(fn () => $this->servico()->aprovar($this->orcamento(), auth()->user()), 'Orçamento aprovado');
                    $this->getRecord()->refresh();
                }),
            Action::make('revisar')
                ->label('Abrir revisão')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn (): bool => $this->orcamento()->estaAprovado() && (bool) auth()->user()?->can('revisar', $this->orcamento()))
                ->requiresConfirmation()
                ->modalHeading('Abrir uma revisão')
                ->modalDescription('Cria um rascunho (nova versão) com as mesmas linhas. O orçamento aprovado continua valendo até o rascunho ser aprovado.')
                ->action(function () {
                    $nova = $this->executar(fn () => $this->servico()->revisar($this->orcamento(), auth()->user()), 'Revisão aberta');

                    return $nova ? redirect(OrcamentoResource::getUrl('planilha', ['record' => $nova])) : null;
                }),
            Action::make('excluir')
                ->label('Excluir rascunho')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn (): bool => $this->orcamento()->ehRascunho() && (bool) auth()->user()?->can('delete', $this->orcamento()))
                ->requiresConfirmation()
                ->action(function () {
                    $ok = $this->executar(function (): bool {
                        $this->servico()->excluir($this->orcamento());

                        return true;
                    }, 'Rascunho excluído');

                    return $ok ? redirect(OrcamentoResource::getUrl('index')) : null;
                }),
            $this->ajudaAction('Orçamento', HelpContent::make('🏦', 'Orçamento', 'Rascunho, aprovado e revisões.')
                ->secao('📌 Como funciona', [
                    ['✏️', 'Rascunho', 'Edita à vontade na planilha ou importando CSV. Pode ser excluído.'],
                    ['✅', 'Aprovado', 'Não muda. É o que vale em Orçado × Realizado e na aprovação de compras.'],
                    ['🔁', 'Revisão', 'Abre uma nova versão a partir do aprovado. Quando você a aprova, o anterior fica como substituído e continua no histórico.'],
                ])),
        ];
    }
}
