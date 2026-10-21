<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\OrigemRegistroPonto;
use App\Exceptions\PontoException;
use App\Filament\Concerns\HasAjudaAction;
use App\Models\Funcionario;
use App\Models\PontoConfiguracao;
use App\Models\PontoRegistro;
use App\Services\Ponto\PontoRegistroService;
use App\Support\FuncionarioDoUsuario;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Tela em que o trabalhador marca o ponto. O horário gravado é sempre o do servidor; o relógio da tela é só visual.
 */
class RegistrarPonto extends Page
{
    use HasAjudaAction;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static UnitEnum|string|null $navigationGroup = 'RH';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Registrar Ponto';

    protected static ?string $navigationLabel = 'Registrar Ponto';

    protected static ?string $slug = 'rh/registrar-ponto';

    protected string $view = 'filament.pages.registrar-ponto';

    /** Registro que acabou de ser gravado nesta tela (para mostrar o comprovante). */
    public ?int $ultimoRegistroId = null;

    /**
     * Exige a permissão e o cadastro de funcionário ativo. A checagem do cadastro é repetida aqui porque o super_admin
     * passa por qualquer Gate (`Gate::before`) e, sem cadastro, a tela não teria quem registrar.
     */
    public static function canAccess(): bool
    {
        return Gate::allows('registrar', PontoRegistro::class)
            && FuncionarioDoUsuario::para(auth()->user()) !== null;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getFuncionario(): ?Funcionario
    {
        return FuncionarioDoUsuario::para(auth()->user());
    }

    public function registrar(): void
    {
        abort_unless(static::canAccess(), 403);

        try {
            $registro = app(PontoRegistroService::class)->registrar($this->getFuncionario(), OrigemRegistroPonto::Web, request()->ip());
        } catch (PontoException $e) {
            Notification::make()->title('Não foi possível registrar')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->ultimoRegistroId = $registro->id;

        Notification::make()
            ->title('Ponto registrado às '.$registro->momento->format('H:i:s'))
            ->body("NSR {$registro->nsr}. Guarde o comprovante.")
            ->success()
            ->send();
    }

    /**
     * Marcações do dia do trabalhador, da mais antiga para a mais recente.
     *
     * @return Collection<int, PontoRegistro>
     */
    public function getMarcacoesDeHoje(): Collection
    {
        $funcionario = $this->getFuncionario();

        if (! $funcionario) {
            return collect();
        }

        return PontoRegistro::query()
            ->where('funcionario_id', $funcionario->id)
            ->whereBetween('momento', [today()->startOfDay(), today()->endOfDay()])
            ->orderBy('momento')
            ->orderBy('id')
            ->get();
    }

    public function getConfiguracao(): ?PontoConfiguracao
    {
        $funcionario = $this->getFuncionario();

        return $funcionario?->unidade_id
            ? PontoConfiguracao::query()->where('unidade_id', $funcionario->unidade_id)->first()
            : null;
    }

    public function getUltimoRegistro(): ?PontoRegistro
    {
        return $this->ultimoRegistroId
            ? PontoRegistro::query()->where('funcionario_id', $this->getFuncionario()?->id)->find($this->ultimoRegistroId)
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Registrar Ponto', HelpContent::make('⏰', 'Registrar Ponto', 'Marque a entrada, as saídas e os retornos da sua jornada.')
                ->passos('Como usar', [
                    'Clique em "Registrar ponto agora" em cada entrada e saída (almoço, retorno, fim do expediente).',
                    'Confira o horário que aparece na confirmação: é o horário do servidor, não o do seu aparelho.',
                    'Baixe o comprovante se quiser guardar. O número NSR identifica a sua marcação.',
                    'Esqueceu de marcar ou marcou errado? Peça um ajuste em "Ajustes de Ponto". A marcação original nunca é apagada.',
                ])
                ->dica('Um segundo toque em até 10 segundos devolve a mesma marcação: não precisa clicar duas vezes.')
                ->alerta('O ponto é um registro pessoal e legal. Marque só a sua própria jornada.')),
        ];
    }
}
