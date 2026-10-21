<?php

declare(strict_types=1);

namespace App\Filament\Resources\PontoTratamentos\Pages;

use App\Enums\TipoTratamentoPonto;
use App\Exceptions\PontoException;
use App\Filament\Resources\PontoTratamentos\PontoTratamentoResource;
use App\Models\Funcionario;
use App\Services\Ponto\PontoTratamentoService;
use App\Support\FuncionarioDoUsuario;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePontoTratamento extends CreateRecord
{
    protected static string $resource = PontoTratamentoResource::class;

    /**
     * Quem decide pode lançar o ajuste já aprovado para outra pessoa; o ajuste do próprio decisor, e o de qualquer
     * trabalhador comum, sempre nasce pendente (a aprovação é de outra pessoa). Ninguém pede ajuste do ponto alheio.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        $funcionario = Funcionario::query()->findOrFail($data['funcionario_id']);
        $proprio = FuncionarioDoUsuario::ehOProprio($user, $funcionario);
        $decide = (bool) $user->can('Aprovar:PontoTratamento');

        abort_unless($proprio || $decide, 403);

        $argumentos = [
            $funcionario,
            $data['tipo'] instanceof TipoTratamentoPonto ? $data['tipo'] : TipoTratamentoPonto::from($data['tipo']),
            Carbon::parse($data['data']),
            (string) $data['motivo'],
            $user,
            isset($data['momento']) ? Carbon::parse($data['momento']) : null,
            isset($data['registro_id']) ? (int) $data['registro_id'] : null,
            isset($data['minutos_abono']) ? (int) $data['minutos_abono'] : null,
        ];

        try {
            $servico = app(PontoTratamentoService::class);

            return $decide && ! $proprio
                ? $servico->registrarAprovado(...$argumentos)
                : $servico->solicitar(...$argumentos);
        } catch (PontoException $e) {
            Notification::make()->title('Não foi possível registrar o ajuste')->body($e->getMessage())->danger()->send();

            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
