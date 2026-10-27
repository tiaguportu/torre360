<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequisicoesCompra\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\RequisicoesCompra\RequisicaoCompraResource;
use App\Services\ComprasService;
use App\Support\HelpContent;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateRequisicaoCompra extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = RequisicaoCompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Nova requisição de compra', HelpContent::make('🛒', 'Nova requisição de compra', 'A requisição nasce como rascunho: dá para ajustar até enviá-la para cotação.')
                ->secao('🎯 Como preencher', [
                    ['🏷️', 'Setor e conta do plano', 'Dizem de onde sai o dinheiro. É com a conta do plano que a aprovação confere o saldo do orçamento.'],
                    ['📦', 'Destino do item', 'Estoque: entra no saldo do item. Patrimônio: vira bem cadastrado. Consumo direto: só gera a conta a pagar.'],
                    ['💬', 'Estimativa', 'Opcional, mas ajuda a escolher quantas cotações pedir antes de haver propostas.'],
                ])),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $itens = array_values((array) ($data['itens'] ?? []));
        unset($data['itens']);

        try {
            return app(ComprasService::class)->criarRequisicao(auth()->user(), $data, $itens);
        } catch (\DomainException $e) {
            Notification::make()->title('Não foi possível criar')->body($e->getMessage())->danger()->send();

            throw ValidationException::withMessages(['data.titulo' => $e->getMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
