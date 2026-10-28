<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcessosFuncionario\Actions;

use App\Enums\MotivoDesligamentoFuncionario;
use App\Enums\TipoProcessoFuncionario;
use App\Filament\Resources\ProcessosFuncionario\ProcessoFuncionarioResource;
use App\Models\Funcionario;
use App\Models\ProcessoFuncionario;
use App\Services\ProcessoFuncionarioService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Livewire\Component;

/**
 * Ação de iniciar uma admissão ou um desligamento. Usada na lista de processos (com a escolha do funcionário) e na
 * página do funcionário (já com o funcionário definido).
 */
final class IniciarProcesso
{
    public static function make(TipoProcessoFuncionario $tipo, ?Funcionario $funcionario = null): Action
    {
        $admissao = $tipo === TipoProcessoFuncionario::Admissao;

        $descricaoModal = function () use ($admissao, $funcionario): string {
            if ($admissao) {
                if ($funcionario) {
                    $dataAdmissaoStr = $funcionario->data_admissao ? $funcionario->data_admissao->format('d/m/Y') : 'não informada';

                    return "O primeiro dia de trabalho é a data de admissão do cadastro: {$dataAdmissaoStr}. Para mudar, use Corrigir data de admissão na ficha.";
                }

                return 'O primeiro dia de trabalho será a data de admissão do cadastro do funcionário escolhido.';
            }

            return 'O sistema cria a lista de providências da saída. Os acessos ao sistema só são retirados ao concluir, a partir do último dia de trabalho.';
        };

        return Action::make($admissao ? 'iniciar_admissao' : 'iniciar_desligamento')
            ->label($admissao ? 'Iniciar admissão' : 'Iniciar desligamento')
            ->icon($tipo->getIcon())
            ->color($admissao ? 'success' : 'danger')
            ->visible(fn (): bool => (bool) auth()->user()?->can('create', ProcessoFuncionario::class))
            ->modalHeading($admissao ? 'Iniciar a admissão' : 'Iniciar o desligamento')
            ->modalDescription($descricaoModal)
            ->schema([
                Select::make('funcionario_id')
                    ->label('Funcionário')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => self::funcionarios($search, $admissao))
                    ->getOptionLabelUsing(fn ($value): ?string => self::rotulo(Funcionario::query()->with('pessoa')->find($value)))
                    ->required()
                    ->visible($funcionario === null),
                DatePicker::make('data_prevista')
                    ->label('Último dia de trabalho')
                    ->native(false)
                    ->default(today())
                    ->required()
                    ->visible(! $admissao),
                Select::make('motivo')
                    ->label('Motivo do desligamento')
                    ->options(MotivoDesligamentoFuncionario::class)
                    ->required()
                    ->visible(! $admissao),
                Textarea::make('observacao')
                    ->label('Observação (opcional)')
                    ->helperText('Fica guardada criptografada e fora do histórico de alterações.')
                    ->rows(2)
                    ->maxLength(2000),
            ])
            ->action(function (array $data, Component $livewire) use ($tipo, $admissao, $funcionario): void {
                $alvo = $funcionario ?? Funcionario::query()->find($data['funcionario_id'] ?? null);

                if (! $alvo) {
                    Notification::make()->title('Funcionário não encontrado')->danger()->send();

                    return;
                }

                $dataPrevista = $admissao ? null : ($data['data_prevista'] ?? null);

                try {
                    $processo = app(ProcessoFuncionarioService::class)->iniciar(
                        $alvo,
                        $tipo,
                        $dataPrevista,
                        auth()->user(),
                        // O estado de um Select com enum pode chegar como o próprio enum.
                        isset($data['motivo']) ? ($data['motivo'] instanceof MotivoDesligamentoFuncionario ? $data['motivo'] : MotivoDesligamentoFuncionario::from($data['motivo'])) : null,
                        $data['observacao'] ?? null,
                    );
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível iniciar')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title($tipo === TipoProcessoFuncionario::Admissao ? 'Admissão iniciada' : 'Desligamento iniciado')->success()->send();

                $livewire->redirect(ProcessoFuncionarioResource::getUrl('view', ['record' => $processo]));
            });
    }

    /**
     * Funcionários que podem receber o processo: ambos os tipos só valem para quem ainda está ativo.
     *
     * @return array<int, string>
     */
    private static function funcionarios(string $busca, bool $admissao): array
    {
        return Funcionario::query()
            ->with('pessoa')
            ->whereNull('data_desligamento')
            ->whereHas('pessoa', fn ($query) => $query->where('nome', 'like', "%{$busca}%"))
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Funcionario $f): array => [$f->id => self::rotulo($f)])
            ->all();
    }

    private static function rotulo(?Funcionario $funcionario): ?string
    {
        return $funcionario ? trim(($funcionario->pessoa?->nome ?? 'Funcionário').($funcionario->cargo ? ' — '.$funcionario->cargo : '')) : null;
    }
}
