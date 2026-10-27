<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequisicoesCompra\Pages;

use App\Enums\StatusRequisicaoCompra;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\RequisicoesCompra\RequisicaoCompraResource;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\ItemRequisicaoCompra;
use App\Models\RequisicaoCompra;
use App\Services\ComprasService;
use App\Support\HelpContent;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * A requisição de ponta a ponta. Cada botão só aparece no passo certo e para quem pode: o super admin passa por qualquer habilidade
 * da policy, então a tela também confere a situação, e o serviço recusa o que a regra de negócio não permite.
 */
class ViewRequisicaoCompra extends ViewRecord
{
    use HasAjudaAction;

    protected static string $resource = RequisicaoCompraResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        return $this->carregar(parent::resolveRecord($key));
    }

    private function carregar(Model $requisicao): Model
    {
        return $requisicao->load(['itens', 'solicitante', 'centroCusto', 'planoConta', 'pedido.fornecedor', 'pedido.aprovadoPor', 'pedido.recebimentos.recebidoPor']);
    }

    private function recarregar(): void
    {
        $this->record = $this->carregar($this->getRecord()->refresh());
    }

    private function requisicao(): RequisicaoCompra
    {
        /** @var RequisicaoCompra $requisicao */
        $requisicao = $this->getRecord();

        return $requisicao;
    }

    private function servico(): ComprasService
    {
        return app(ComprasService::class);
    }

    private function emSituacao(StatusRequisicaoCompra ...$situacoes): bool
    {
        return in_array($this->requisicao()->status, $situacoes, true);
    }

    /**
     * @param  callable(): mixed  $acao
     */
    private function executar(callable $acao, string $sucesso, ?string $titulo = null): bool
    {
        try {
            $acao();
        } catch (\DomainException $e) {
            Notification::make()->title($titulo ?? 'Não foi possível concluir')->body($e->getMessage())->danger()->persistent()->send();

            return false;
        } finally {
            $this->recarregar();
        }

        Notification::make()->title($sucesso)->success()->send();

        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->editarAction(),
            $this->enviarAction(),
            $this->cotacaoAction(),
            $this->removerCotacaoAction(),
            $this->escolherAction(),
            $this->aprovarAction(),
            $this->recusarAction(),
            $this->receberAction(),
            $this->cancelarAction(),
            $this->ajudaAction('Requisição de compra', HelpContent::make('🛒', 'Acompanhar a compra', 'Os botões do alto mudam conforme o passo em que a compra está e o que o seu perfil pode fazer.')
                ->secao('🔁 Passos', [
                    ['📤', 'Enviar para cotação', 'Trava a requisição e abre para o comprador pedir propostas.'],
                    ['💬', 'Registrar cotação', 'Uma proposta por fornecedor, com preço de todos os itens, prazo e frete.'],
                    ['🏆', 'Escolher cotação', 'Abre o pedido. Se não for a mais barata, explique o motivo; se faltarem cotações, justifique a dispensa.'],
                    ['✅', 'Aprovar ou recusar', 'Quem tem a alçada do valor decide e vê o saldo do orçamento da conta.'],
                    ['📥', 'Registrar recebimento', 'Informe o que chegou e a nota fiscal. Entra no estoque, vira bem no patrimônio e gera a conta a pagar.'],
                ])),
        ];
    }

    private function editarAction(): Action
    {
        return Action::make('editar')
            ->label('Editar')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->visible(fn (): bool => $this->requisicao()->ehRascunho() && $this->requisicao()->solicitante_user_id === auth()->id() && (bool) auth()->user()?->can('Update:RequisicaoCompra'))
            ->url(fn (): string => RequisicaoCompraResource::getUrl('edit', ['record' => $this->requisicao()]));
    }

    private function enviarAction(): Action
    {
        return Action::make('enviar')
            ->label('Enviar para cotação')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->visible(fn (): bool => $this->requisicao()->ehRascunho() && $this->requisicao()->solicitante_user_id === auth()->id() && (bool) auth()->user()?->can('Create:RequisicaoCompra'))
            ->requiresConfirmation()
            ->modalHeading('Enviar para cotação')
            ->modalDescription('Depois de enviada a requisição não é mais editada: o comprador passa a pedir propostas aos fornecedores.')
            ->action(fn () => $this->executar(fn () => $this->servico()->enviarParaCotacao($this->requisicao(), auth()->user()), 'Requisição enviada para cotação'));
    }

    private function podeCotar(): bool
    {
        return $this->emSituacao(StatusRequisicaoCompra::EmCotacao) && (bool) auth()->user()?->can('Cotar:RequisicaoCompra');
    }

    private function cotacaoAction(): Action
    {
        return Action::make('cotacao')
            ->label('Registrar cotação')
            ->icon('heroicon-o-currency-dollar')
            ->color('primary')
            ->visible(fn (): bool => $this->podeCotar())
            ->modalHeading('Registrar a proposta de um fornecedor')
            ->modalDescription('A cotação precisa trazer o preço de todos os itens. Se o fornecedor já tinha proposta nesta requisição, ela é substituída.')
            ->modalWidth('3xl')
            ->schema(fn (): array => [
                Select::make('fornecedor_id')
                    ->label('Fornecedor')
                    ->options(fn (): array => Fornecedor::query()->orderBy('razao_social')->pluck('razao_social', 'id')->all())
                    ->searchable()
                    ->native(false)
                    ->required(),
                ...$this->requisicao()->itens->map(fn (ItemRequisicaoCompra $item): TextInput => TextInput::make("preco_{$item->id}")
                    ->label('Preço unitário: '.$item->descricao.' ('.rtrim(rtrim(number_format((float) $item->quantidade, 3, ',', '.'), '0'), ',').' '.$item->unidade_medida.')')
                    ->numeric()
                    ->minValue(0.01)
                    ->prefix('R$')
                    ->required())->all(),
                TextInput::make('frete')->label('Frete (R$)')->numeric()->minValue(0)->default(0)->prefix('R$'),
                TextInput::make('prazo_entrega_dias')->label('Prazo de entrega (dias)')->numeric()->minValue(0),
                TextInput::make('prazo_pagamento_dias')->label('Prazo de pagamento (dias)')->numeric()->minValue(0)->default(30),
                DatePicker::make('validade_ate')->label('Proposta válida até')->native(false),
                Textarea::make('condicoes')->label('Condições')->rows(2),
            ])
            ->action(function (array $data): void {
                abort_unless($this->podeCotar(), 403);

                $fornecedor = Fornecedor::query()->find($data['fornecedor_id'] ?? null);

                if (! $fornecedor) {
                    Notification::make()->title('Escolha o fornecedor')->danger()->send();

                    return;
                }

                $precos = [];

                foreach ($this->requisicao()->itens as $item) {
                    $precos[$item->id] = $data["preco_{$item->id}"] ?? null;
                }

                $this->executar(fn () => $this->servico()->registrarCotacao($this->requisicao(), $fornecedor, $precos, $data, auth()->user()), 'Cotação registrada', 'Cotação recusada');
            });
    }

    private function removerCotacaoAction(): Action
    {
        return Action::make('remover_cotacao')
            ->label('Retirar cotação')
            ->icon('heroicon-o-x-circle')
            ->color('gray')
            ->visible(fn (): bool => $this->podeCotar() && $this->requisicao()->cotacoes()->exists())
            ->modalHeading('Retirar uma cotação')
            ->schema([
                Select::make('cotacao_id')->label('Cotação')->options(fn (): array => $this->opcoesDeCotacao())->native(false)->required(),
            ])
            ->action(function (array $data): void {
                abort_unless($this->podeCotar(), 403);

                $cotacao = $this->requisicao()->cotacoes()->whereKey($data['cotacao_id'] ?? 0)->first();

                if ($cotacao) {
                    $this->executar(fn () => $this->servico()->removerCotacao($cotacao), 'Cotação retirada');
                }
            });
    }

    /**
     * @return array<int, string>
     */
    private function opcoesDeCotacao(): array
    {
        $mapa = $this->servico()->mapaComparativo($this->requisicao());

        return collect($mapa['cotacoes'])->mapWithKeys(fn (array $c): array => [$c['id'] => $c['fornecedor'].' — R$ '.number_format($c['total'], 2, ',', '.').($c['mais_barata'] ? ' (menor valor)' : '')])->all();
    }

    private function escolherAction(): Action
    {
        return Action::make('escolher')
            ->label('Escolher cotação')
            ->icon('heroicon-o-trophy')
            ->color('success')
            ->visible(fn (): bool => $this->podeCotar() && $this->requisicao()->cotacoes()->exists())
            ->modalHeading('Escolher a cotação e abrir o pedido')
            ->modalDescription(function (): string {
                $mapa = $this->servico()->mapaComparativo($this->requisicao());

                return "Esta faixa de valor pede {$mapa['minimo']} cotação(ões); há ".count($mapa['cotacoes']).'. Se a escolhida não for a de menor valor, explique o motivo.';
            })
            ->schema([
                Select::make('cotacao_id')->label('Cotação escolhida')->options(fn (): array => $this->opcoesDeCotacao())->native(false)->required(),
                Textarea::make('motivo_escolha')->label('Por que esta (obrigatório se não for a mais barata)')->rows(2),
                Textarea::make('justificativa_dispensa')->label('Justificativa para a dispensa de cotações (se faltarem)')->rows(2),
            ])
            ->action(function (array $data): void {
                abort_unless($this->podeCotar(), 403);

                $cotacao = $this->requisicao()->cotacoes()->whereKey($data['cotacao_id'] ?? 0)->first();

                if (! $cotacao instanceof Cotacao) {
                    Notification::make()->title('Escolha uma cotação')->danger()->send();

                    return;
                }

                $this->executar(fn () => $this->servico()->escolherCotacao($this->requisicao(), $cotacao, $data['motivo_escolha'] ?? null, $data['justificativa_dispensa'] ?? null, auth()->user()), 'Pedido aberto: aguarda aprovação', 'Escolha recusada');
            });
    }

    private function podeDecidir(): bool
    {
        $pedido = $this->requisicao()->pedido;

        return $pedido !== null && $this->servico()->impedimentoParaAprovar($pedido, auth()->user()) === null;
    }

    private function aprovarAction(): Action
    {
        return Action::make('aprovar')
            ->label('Aprovar pedido')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (): bool => $this->podeDecidir())
            ->modalHeading('Aprovar o pedido')
            ->modalDescription(function (): string {
                $saldo = $this->servico()->saldoOrcamentario($this->requisicao()->pedido);

                if ($saldo === null) {
                    return 'A conta desta compra não tem orçamento aprovado no ano: a aprovação não confere saldo.';
                }

                return sprintf('%s: previsto R$ %s, realizado R$ %s, comprometido R$ %s, saldo R$ %s.%s', $saldo['plano_orcado'], number_format($saldo['previsto'], 2, ',', '.'), number_format($saldo['realizado'], 2, ',', '.'), number_format($saldo['comprometido'], 2, ',', '.'), number_format($saldo['saldo'], 2, ',', '.'), $saldo['acima'] ? ' Este pedido passa do saldo: justifique abaixo.' : '');
            })
            ->schema([
                Textarea::make('justificativa')->label('Justificativa (obrigatória se passar do saldo do orçamento)')->rows(2),
            ])
            ->action(function (array $data): void {
                abort_unless($this->podeDecidir(), 403);

                $this->executar(fn () => $this->servico()->aprovar($this->requisicao()->pedido, auth()->user(), $data['justificativa'] ?? null), 'Pedido aprovado', 'Aprovação recusada');
            });
    }

    private function recusarAction(): Action
    {
        return Action::make('recusar')
            ->label('Recusar pedido')
            ->icon('heroicon-o-x-mark')
            ->color('danger')
            ->visible(fn (): bool => $this->podeDecidir())
            ->modalHeading('Recusar o pedido')
            ->schema([
                Textarea::make('motivo')->label('Motivo da recusa')->required()->rows(2),
                Toggle::make('devolver')->label('Devolver para nova cotação (em vez de encerrar a requisição)')->default(false),
            ])
            ->action(function (array $data): void {
                abort_unless($this->podeDecidir(), 403);

                $this->executar(fn () => $this->servico()->recusar($this->requisicao()->pedido, auth()->user(), (string) ($data['motivo'] ?? ''), (bool) ($data['devolver'] ?? false)), 'Pedido recusado');
            });
    }

    private function podeReceber(): bool
    {
        $pedido = $this->requisicao()->pedido;

        return $pedido !== null && (bool) auth()->user()?->can('Receber:PedidoCompra') && $this->servico()->impedimentoParaReceber($pedido, auth()->user()) === null;
    }

    private function receberAction(): Action
    {
        return Action::make('receber')
            ->label('Registrar recebimento')
            ->icon('heroicon-o-inbox-arrow-down')
            ->color('success')
            ->visible(fn (): bool => $this->podeReceber())
            ->modalHeading('Registrar o recebimento')
            ->modalDescription('Informe o que chegou. Item de estoque dá entrada no saldo, bem durável é cadastrado no patrimônio e a nota gera a conta a pagar. Entrega parcial é aceita.')
            ->modalWidth('3xl')
            ->schema(fn (): array => [
                ...$this->requisicao()->itens->filter(fn (ItemRequisicaoCompra $i): bool => $i->quantidadePendente() > 0)->map(fn (ItemRequisicaoCompra $item): TextInput => TextInput::make("qtd_{$item->id}")
                    ->label($item->descricao.' ('.$item->unidade_medida.') — pendente: '.rtrim(rtrim(number_format($item->quantidadePendente(), 3, ',', '.'), '0'), ','))
                    ->numeric()
                    ->minValue(0)
                    ->default($item->quantidadePendente()))->all(),
                DatePicker::make('data_recebimento')->label('Data do recebimento')->default(now())->native(false)->required(),
                TextInput::make('nota_fiscal_numero')->label('Número da nota fiscal')->required()->maxLength(40),
                FileUpload::make('nota_fiscal')
                    ->label('Nota fiscal (PDF ou foto)')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->storeFiles(false)
                    ->maxSize(10240),
                Toggle::make('divergencia')->label('Houve divergência (nota diferente do pedido, item faltando, avaria)')->live(),
                Textarea::make('observacao')->label('Observação')->rows(2)->helperText('Obrigatória quando há divergência: fica no histórico do fornecedor.'),
            ])
            ->action(function (array $data): void {
                abort_unless($this->podeReceber(), 403);

                $quantidades = [];

                foreach ($this->requisicao()->itens as $item) {
                    $quantidades[$item->id] = $data["qtd_{$item->id}"] ?? 0;
                }

                $nota = null;
                $arquivo = $data['nota_fiscal'] ?? null;
                $arquivo = is_array($arquivo) ? (array_values($arquivo)[0] ?? null) : $arquivo;

                if ($arquivo instanceof TemporaryUploadedFile) {
                    $nota = ['conteudo' => (string) $arquivo->get(), 'extensao' => $arquivo->guessExtension() ?: pathinfo($arquivo->getClientOriginalName(), PATHINFO_EXTENSION)];
                }

                $this->executar(
                    fn () => $this->servico()->receber($this->requisicao()->pedido, auth()->user(), $quantidades, Carbon::parse($data['data_recebimento'] ?? now()), $data['nota_fiscal_numero'] ?? null, $nota, $data['observacao'] ?? null, (bool) ($data['divergencia'] ?? false)),
                    'Recebimento registrado',
                    'Recebimento recusado',
                );
            });
    }

    private function cancelarAction(): Action
    {
        return Action::make('cancelar')
            ->label('Cancelar requisição')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (): bool => ! $this->requisicao()->status->encerrada()
                && ! $this->emSituacao(StatusRequisicaoCompra::RecebidaParcial)
                && ($this->requisicao()->solicitante_user_id === auth()->id() || (bool) auth()->user()?->can('Cotar:RequisicaoCompra')))
            ->modalHeading('Cancelar a requisição')
            ->modalDescription('Encerra o processo. Pedido com entrega já registrada não pode ser cancelado.')
            ->schema([
                Textarea::make('motivo')->label('Motivo')->required()->rows(2),
            ])
            ->action(fn (array $data) => $this->executar(fn () => $this->servico()->cancelar($this->requisicao(), auth()->user(), (string) ($data['motivo'] ?? '')), 'Requisição cancelada'));
    }
}
