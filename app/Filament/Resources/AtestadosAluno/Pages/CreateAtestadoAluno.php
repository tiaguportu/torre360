<?php

declare(strict_types=1);

namespace App\Filament\Resources\AtestadosAluno\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\AtestadosAluno\AtestadoAlunoResource;
use App\Models\AtestadoAluno;
use App\Models\Matricula;
use App\Services\AtestadoAlunoService;
use App\Support\HelpContent;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class CreateAtestadoAluno extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = AtestadoAlunoResource::class;

    protected static bool $canCreateAnother = false;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Registrar atestado', HelpContent::make('🩺', 'Registrar atestado', 'Anexe o comprovante e informe o período: as faltas do aluno viram justificadas.')
                ->secao('🎯 Como preencher', [
                    ['🎓', 'Aluno', 'Aparecem as matrículas ativas. Digite parte do nome para buscar.'],
                    ['📅', 'Período', 'Do primeiro ao último dia do afastamento. Vale também para os dias que ainda não chegaram: as faltas lançadas depois já entram justificadas.'],
                    ['📎', 'Comprovante', 'PDF ou foto do atestado. Fica em área privada: só a secretaria e a coordenação abrem.'],
                    ['⚖️', 'Abono de falta', 'Justificar não é abonar. Desmarcado, as faltas ficam justificadas mas continuam contando na frequência. Marcado, ficam abonadas: não contam como falta.'],
                    ['🔒', 'Observação', 'Fica criptografada. Não é preciso informar diagnóstico nem CID.'],
                ])
                ->dica('Errou a data? Use "Corrigir período" na lista: as faltas que ficarem de fora voltam a injustificadas.')),
        ];
    }

    /**
     * Passa pelo serviço, que guarda o comprovante, recusa duplicidade e abona as faltas.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $matricula = Matricula::query()->findOrFail($data['matricula_id']);
        $comprovante = Arr::first(Arr::wrap($data['comprovante'] ?? null));

        if (! $comprovante instanceof UploadedFile) {
            Notification::make()->title('Comprovante não recebido')->body('Anexe o PDF ou a foto do atestado.')->danger()->send();

            $this->halt();
        }

        try {
            return app(AtestadoAlunoService::class)->registrar(
                $matricula,
                Carbon::parse($data['data_inicio']),
                Carbon::parse($data['data_fim']),
                $comprovante,
                $data['observacao'] ?? null,
                auth()->user(),
                (bool) ($data['abona_faltas'] ?? false),
            );
        } catch (\DomainException $e) {
            Notification::make()->title('Não foi possível registrar o atestado')->body($e->getMessage())->danger()->send();

            $this->halt();
        }

        // halt() lança a exceção que interrompe a criação; este retorno só satisfaz a assinatura.
        return new AtestadoAluno;
    }

    protected function getCreatedNotification(): ?Notification
    {
        $justificadas = $this->record instanceof AtestadoAluno ? $this->record->frequencias()->count() : 0;
        $abona = $this->record instanceof AtestadoAluno && $this->record->abona_faltas;

        $destino = $abona ? 'justificadas e abonadas (não contam na frequência)' : 'justificadas (continuam contando na frequência)';

        return Notification::make()
            ->success()
            ->title('Atestado registrado')
            ->body($justificadas === 0
                ? "Nenhuma falta lançada no período. As que forem lançadas até o último dia já entram {$destino}."
                : "{$justificadas} falta(s) do período ficaram {$destino}.");
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
