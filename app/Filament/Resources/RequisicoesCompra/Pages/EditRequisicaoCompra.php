<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequisicoesCompra\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\RequisicoesCompra\RequisicaoCompraResource;
use App\Services\ComprasService;
use App\Support\HelpContent;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditRequisicaoCompra extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = RequisicaoCompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Editar requisição', HelpContent::make('✏️', 'Editar a requisição', 'Só o rascunho se edita. Depois de enviada para cotação, a requisição fica travada para que todos comparem a mesma coisa.')),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['itens'] = $this->getRecord()->itens()->orderBy('id')->get()->map(fn ($item): array => [
            'descricao' => $item->descricao,
            'quantidade' => (float) $item->quantidade,
            'unidade_medida' => $item->unidade_medida,
            'estimativa_unitaria' => $item->estimativa_unitaria !== null ? (float) $item->estimativa_unitaria : null,
            'destino' => $item->destino?->value,
            'item_estoque_id' => $item->item_estoque_id,
        ])->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $itens = array_values((array) ($data['itens'] ?? []));
        unset($data['itens']);

        try {
            return app(ComprasService::class)->atualizarRequisicao($record, $data, $itens);
        } catch (\DomainException $e) {
            Notification::make()->title('Não foi possível salvar')->body($e->getMessage())->danger()->send();

            throw ValidationException::withMessages(['data.titulo' => $e->getMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
