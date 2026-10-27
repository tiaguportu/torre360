<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reunioes\Pages;

use App\Exceptions\ReuniaoInvalidaException;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Reunioes\ReuniaoResource;
use App\Models\Reuniao;
use App\Models\ReuniaoParticipante;
use App\Services\ReuniaoService;
use App\Support\HelpContent;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditReuniao extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = ReuniaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Alterar reunião', HelpContent::make('✏️', 'Alterar a reunião', 'Só a reunião ainda agendada se altera.')
                ->secao('📌 O que acontece', [
                    ['⏰', 'Mudou o horário', 'Todos os convidados voltam a "sem resposta" e recebem um aviso de reunião remarcada. A reserva do espaço acompanha.'],
                    ['👥', 'Mudou a lista', 'Quem entra recebe o convite; quem sai deixa a reunião. Quem já respondeu mantém a resposta.'],
                    ['🔒', 'Não muda', 'O tipo, a turma e a etapa do conselho de classe ficam como foram marcados.'],
                ])),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Reuniao $reuniao */
        $reuniao = $this->getRecord();
        $participantes = $reuniao->participantes()->get();

        $data['participantes'] = $participantes
            ->where('papel', '!=', ReuniaoParticipante::PAPEL_ORGANIZADOR)
            ->pluck('user_id')
            ->all();
        $data['presidente_user_id'] = $participantes->firstWhere('papel', ReuniaoParticipante::PAPEL_PRESIDENTE)?->user_id;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(ReuniaoService::class)->reagendar($record, CreateReuniao::paraOServico($data), auth()->user());
        } catch (ReuniaoInvalidaException $e) {
            Notification::make()->title('Não foi possível salvar')->body(implode("\n", $e->mensagens()))->danger()->persistent()->send();

            throw ValidationException::withMessages(['data.titulo' => $e->getMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Reunião atualizada';
    }
}
