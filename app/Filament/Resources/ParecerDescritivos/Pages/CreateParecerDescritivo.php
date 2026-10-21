<?php

declare(strict_types=1);

namespace App\Filament\Resources\ParecerDescritivos\Pages;

use App\Exceptions\ParecerDescritivoException;
use App\Filament\Resources\ParecerDescritivos\ParecerDescritivoResource;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Services\ParecerDescritivoService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateParecerDescritivo extends CreateRecord
{
    protected static string $resource = ParecerDescritivoResource::class;

    /**
     * O parecer nasce no serviço (matrícula ativa, etapa da turma do aluno, um por aluno e etapa). Se já existe um para
     * o aluno nesta etapa, abre o existente em vez de duplicar.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            $parecer = app(ParecerDescritivoService::class)->iniciar(
                Matricula::findOrFail($data['matricula_id']),
                EtapaAvaliativa::findOrFail($data['etapa_avaliativa_id']),
                auth()->user(),
            );
        } catch (ParecerDescritivoException $e) {
            Notification::make()->title('Não foi possível iniciar o parecer')->body($e->getMessage())->danger()->send();

            $this->halt();
        }

        if (filled($data['texto'] ?? null) && ! $parecer->estaPublicado() && blank($parecer->texto)) {
            $parecer->update(['texto' => $data['texto']]);
        }

        return $parecer;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
