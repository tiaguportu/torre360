<?php

declare(strict_types=1);

namespace App\Filament\Resources\AutoavaliacaoDocentes\Pages;

use App\Exceptions\DesenvolvimentoDocenteException;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\AutoavaliacaoDocentes\AutoavaliacaoDocenteResource;
use App\Models\AutoavaliacaoDocente;
use App\Models\CicloAvaliacaoDocente;
use App\Services\AutoavaliacaoDocenteService;
use App\Support\FuncionarioDoUsuario;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAutoavaliacaoDocentes extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = AutoavaliacaoDocenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('iniciar')
                ->label('Iniciar autoavaliação')
                ->icon('heroicon-o-play')
                ->visible(fn (): bool => (bool) auth()->user()?->can('create', AutoavaliacaoDocente::class) && FuncionarioDoUsuario::para(auth()->user()) !== null)
                ->schema([
                    Select::make('ciclo_id')
                        ->label('Ciclo de avaliação')
                        ->options(fn (): array => CicloAvaliacaoDocente::query()->abertos()->orderByDesc('data_inicio')->pluck('nome', 'id')->all())
                        ->required()
                        ->helperText('Aparecem só os ciclos abertos hoje.'),
                ])
                ->action(function (array $data) {
                    try {
                        $autoavaliacao = app(AutoavaliacaoDocenteService::class)->iniciar(
                            CicloAvaliacaoDocente::query()->findOrFail($data['ciclo_id']),
                            FuncionarioDoUsuario::para(auth()->user()),
                            auth()->user(),
                        );
                    } catch (DesenvolvimentoDocenteException $e) {
                        Notification::make()->title('Não foi possível iniciar')->body($e->getMessage())->danger()->send();

                        return null;
                    }

                    return $this->redirect(
                        $autoavaliacao->estaEnviada()
                            ? AutoavaliacaoDocenteResource::getUrl('view', ['record' => $autoavaliacao])
                            : AutoavaliacaoDocenteResource::getUrl('edit', ['record' => $autoavaliacao]),
                    );
                }),
            $this->ajudaAction('Autoavaliação Docente', HelpContent::make('🪞', 'Autoavaliação Docente', 'Você dá nota a si mesmo(a) nos mesmos critérios que a coordenação usa.')
                ->passos('Como fazer', [
                    'Clique em "Iniciar autoavaliação" e escolha o ciclo aberto.',
                    'Dê uma nota a cada critério e, se quiser, explique. Salve para continuar depois.',
                    'Quando terminar, clique em "Enviar autoavaliação". Depois de enviada ela não muda.',
                ])
                ->secao('🔒 Quem vê o quê', [
                    ['👤', 'Em preenchimento', 'Só você enxerga o rascunho.'],
                    ['📬', 'Enviada', 'A coordenação e a administração passam a ver e comparam com as observações do ciclo.'],
                ])
                ->alerta('Se enviou por engano, peça à administração para reabrir.')),
        ];
    }
}
