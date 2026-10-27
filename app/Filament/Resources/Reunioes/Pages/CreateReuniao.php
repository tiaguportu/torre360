<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reunioes\Pages;

use App\Exceptions\ReuniaoInvalidaException;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Reunioes\ReuniaoResource;
use App\Models\ReuniaoParticipante;
use App\Services\ReuniaoService;
use App\Support\HelpContent;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateReuniao extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = ReuniaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Nova reunião', HelpContent::make('🆕', 'Nova reunião', 'Marque a reunião, convide a equipe e deixe a pauta pronta.')
                ->passos('Como marcar', [
                    'Escolha o tipo, dê um título e informe a data e o horário.',
                    'Se for o conselho de classe, escolha a turma e a etapa: a pauta aluno por aluno é montada na hora, com os indicadores de hoje.',
                    'Convide os participantes. Para o conselho, o botão "Sugerir os da turma" traz quem dá aula para ela e a coordenação.',
                    'Escolha um espaço para reservar o horário nele. Se estiver ocupado, o sistema avisa e nada é gravado.',
                ])
                ->dica('Registrando uma reunião que já aconteceu? Deixe o espaço em branco e escreva o local: não dá para reservar um horário que passou.')),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(ReuniaoService::class)->agendar(self::paraOServico($data), auth()->user());
        } catch (ReuniaoInvalidaException $e) {
            Notification::make()->title('Não foi possível marcar a reunião')->body(implode("\n", $e->mensagens()))->danger()->persistent()->send();

            throw ValidationException::withMessages(['data.titulo' => $e->getMessage()]);
        }
    }

    /**
     * Do formulário (participantes e presidente à parte) para o que o serviço espera.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function paraOServico(array $data): array
    {
        $participantes = collect((array) ($data['participantes'] ?? []))
            ->map(fn ($id): array => ['user_id' => (int) $id, 'papel' => ReuniaoParticipante::PAPEL_PARTICIPANTE]);

        if (filled($data['presidente_user_id'] ?? null)) {
            $presidente = (int) $data['presidente_user_id'];

            $participantes = $participantes
                ->reject(fn (array $p): bool => $p['user_id'] === $presidente)
                ->push(['user_id' => $presidente, 'papel' => ReuniaoParticipante::PAPEL_PRESIDENTE]);
        }

        unset($data['presidente_user_id']);
        $data['participantes'] = $participantes->values()->all();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Reunião marcada';
    }
}
