<?php

declare(strict_types=1);

namespace App\Filament\Resources\PontoTratamentos\Pages;

use App\Enums\TipoTratamentoPonto;
use App\Exceptions\PontoException;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PontoTratamentos\PontoTratamentoResource;
use App\Models\Funcionario;
use App\Services\Ponto\PontoTratamentoService;
use App\Support\FuncionarioDoUsuario;
use App\Support\HelpContent;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePontoTratamento extends CreateRecord
{
    use HasAjudaAction;

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

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Novo Ajuste de Ponto', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('⚙️', 'Novo Ajuste de Ponto', 'Solicitação ou inclusão de ajuste de batida, abono ou justificativa de horário.')
            ->secao('🎯 Funcionalidades Disponíveis', array_filter([
                ['👤', 'Funcionário e Data', 'Identifique o colaborador e o dia da ocorrência de ponto.'],
                ['📝', 'Tipo de Ajuste', 'Indique se é inclusão de marcação esquecida, abono ou desconsideração.'],
            ]))
            ->secao('🔒 Permissões do Perfil', array_filter([
                $user?->can('Create:PontoTratamento') ? ['🆕', 'Cadastrar', 'Permissão para criar novos registros deste recurso.'] : null,
                $user?->can('Update:PontoTratamento') ? ['✏️', 'Editar', 'Permissão para alterar informações existentes.'] : null,
                $user?->can('Delete:PontoTratamento') ? ['🗑️', 'Excluir', 'Permissão para remover dados do sistema.'] : null,
            ]))
            ->alerta('Todo ajuste submetido passará por aprovação do gestor de RH antes de impactar o espelho de ponto.');
    }
}
