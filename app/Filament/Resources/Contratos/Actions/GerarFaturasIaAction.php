<?php

declare(strict_types=1);

namespace App\Filament\Resources\Contratos\Actions;

use App\Models\Contrato;
use App\Services\CrmIaVendasService;
use App\Services\GeracaoFaturasContratoService;
use App\Services\PlanoFaturasIaService;
use App\Support\PermissaoAcao;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;

/**
 * Gera ou regenera as faturas de um contrato a partir de uma instrução em texto livre, interpretada pelo Gemini.
 *
 * São duas ações encadeadas, porque regenerar apaga as faturas atuais e o operador precisa ver o que a IA entendeu
 * antes: `make()` recebe o texto e consulta a IA; `confirmar()` mostra o plano calculado e só ela grava. A segunda
 * é aberta pela primeira e precisa existir na página como método `confirmarFaturasIaAction()`.
 */
class GerarFaturasIaAction
{
    public const ACAO_CONFIRMAR = 'confirmarFaturasIa';

    public static function make(): Action
    {
        $temFaturas = fn (?Contrato $record): bool => $record !== null && $record->faturas()->exists();

        return Action::make('gerarFaturasIa')
            ->label(fn (?Contrato $record): string => $temFaturas($record) ? 'Regenerar Faturas com IA' : 'Gerar Faturas com IA')
            ->icon('heroicon-o-sparkles')
            ->color('purple')
            // Apaga e recria as faturas do contrato e aciona o Gemini: exige poder alterar o contrato.
            ->authorize(PermissaoAcao::qualquer('Update:Contrato'))
            ->visible(fn (?Contrato $record): bool => $record !== null)
            ->modalHeading(fn (?Contrato $record): string => $temFaturas($record) ? 'Regenerar Faturas com IA' : 'Gerar Faturas com IA')
            ->modalDescription(fn (?Contrato $record): string => 'Descreva o parcelamento com as suas palavras. A IA da Google entende as datas e a regra de divisão; os valores são calculados pelo sistema a partir do valor do contrato. Você confere o resultado antes de qualquer fatura ser gravada.'
                .($temFaturas($record) ? ' As faturas atuais serão substituídas; faturas com pagamento ou cobrança emitida impedem a regeneração.' : ''))
            ->modalWidth(Width::ExtraLarge)
            ->modalSubmitActionLabel('Interpretar e conferir ✨')
            ->schema([
                Textarea::make('instrucao')
                    ->label('Como as faturas devem ser geradas?')
                    ->placeholder('Ex.: Divida o valor do contrato em 2 parcelas pagas semestralmente, no final da primeira quinzena do primeiro mês de cada semestre, a partir do ano seguinte.')
                    ->rows(5)
                    ->required()
                    ->maxLength(PlanoFaturasIaService::MAX_CARACTERES_INSTRUCAO)
                    ->helperText('Diga quantas parcelas são, quando vencem e, se houver, entrada ou valores diferentes entre elas. Se não citar valores, o saldo é dividido igualmente.'),
            ])
            ->action(function (array $data, EditRecord $livewire): void {
                /** @var Contrato $contrato */
                $contrato = $livewire->getRecord();

                $limite = CrmIaVendasService::verificarIncrementarRateLimit();

                if (! $limite['permitido']) {
                    Notification::make()
                        ->title('Limite de consultas de IA atingido')
                        ->body($limite['mensagem'])
                        ->warning()
                        ->send();

                    throw new Halt;
                }

                try {
                    $plano = app(PlanoFaturasIaService::class)->interpretar($contrato, (string) $data['instrucao']);
                } catch (\InvalidArgumentException|\RuntimeException $e) {
                    Notification::make()
                        ->title('Não foi possível montar as faturas')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    // Mantém o modal aberto com o texto digitado, para o operador ajustar a instrução.
                    throw new Halt;
                }

                $livewire->replaceMountedAction(self::ACAO_CONFIRMAR, [
                    'plano' => $plano,
                    'instrucao' => (string) $data['instrucao'],
                ]);
            });
    }

    public static function confirmar(): Action
    {
        return Action::make(self::ACAO_CONFIRMAR)
            ->icon('heroicon-o-sparkles')
            ->color('purple')
            ->authorize(PermissaoAcao::qualquer('Update:Contrato'))
            ->modalHeading('Confira as faturas antes de gerar')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Confirmar e gerar faturas')
            ->modalCancelActionLabel('Cancelar')
            ->modalContent(function (array $arguments, EditRecord $livewire) {
                /** @var Contrato $contrato */
                $contrato = $livewire->getRecord();
                $plano = (array) ($arguments['plano'] ?? []);
                $parcelas = array_values(array_filter((array) ($plano['parcelas'] ?? []), 'is_array'));

                return view('filament.resources.contratos.previa-faturas-ia', [
                    'instrucao' => (string) ($arguments['instrucao'] ?? ''),
                    'resumo' => (string) ($plano['resumo'] ?? ''),
                    'avisos' => (array) ($plano['avisos'] ?? []),
                    'parcelas' => $parcelas,
                    'totalPlano' => (float) collect($parcelas)->sum('valor'),
                    'valorContrato' => (float) $contrato->valor_total,
                    'percentualBolsa' => app(GeracaoFaturasContratoService::class)->percentualBolsaAtiva($contrato),
                    'faturasAtuais' => $contrato->faturas()->count(),
                    'faturasProtegidas' => $contrato->faturas()->protegida()->count(),
                    'jaEnviadoAssinafy' => $contrato->jaEnviadoAssinafy(),
                ]);
            })
            ->action(function (array $arguments, EditRecord $livewire): void {
                /** @var Contrato $contrato */
                $contrato = $livewire->getRecord();
                $servico = app(GeracaoFaturasContratoService::class);

                // Os argumentos da ação viajam pelo navegador: o plano é recalculado e validado de novo, e só as
                // três colunas conhecidas passam. Todas as parcelas já têm valor, então a soma precisa fechar com o contrato.
                $parcelas = collect((array) ($arguments['plano']['parcelas'] ?? []))
                    ->filter(fn (mixed $parcela): bool => is_array($parcela))
                    ->map(fn (array $parcela): array => [
                        'descricao' => $parcela['descricao'] ?? null,
                        'vencimento' => $parcela['vencimento'] ?? null,
                        'valor' => $parcela['valor'] ?? null,
                    ])
                    ->values()
                    ->all();

                try {
                    $plano = $servico->resolverPlano($contrato, $parcelas);
                    $faturas = $servico->gerarPlano($contrato, $plano);
                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Erro')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Faturas geradas com sucesso!')
                    ->body($faturas->count().' fatura(s) criada(s). 1º vencimento: '.Carbon::parse($plano[0]['vencimento'])->format('d/m/Y').'.')
                    ->success()
                    ->send();

                activity('contrato')
                    ->performedOn($contrato)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'tipo' => 'geracao_faturas_ia',
                        'instrucao' => (string) ($arguments['instrucao'] ?? ''),
                        'parcelas' => $plano,
                        'valor_total_contrato' => (float) $contrato->valor_total,
                    ])
                    ->log("Faturas do Contrato #{$contrato->id} foram geradas pelo operador a partir de instrução interpretada por IA.");

                if ($contrato->jaEnviadoAssinafy()) {
                    $contrato->resetAssinafyState();

                    Notification::make()
                        ->title('Assinatura Resetada')
                        ->body('Como as faturas foram regeradas, os registros anteriores de assinatura no Assinafy foram limpos.')
                        ->warning()
                        ->send();
                }
            });
    }
}
