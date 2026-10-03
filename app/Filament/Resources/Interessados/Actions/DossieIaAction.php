<?php

declare(strict_types=1);

namespace App\Filament\Resources\Interessados\Actions;

use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\TipoContatoInteressado;
use App\Services\CrmIaVendasService;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class DossieIaAction
{
    public static function make(?string $name = 'dossieIa'): Action
    {
        return Action::make($name)
            ->label('Dossiê IA do Lead')
            ->icon('heroicon-o-sparkles')
            ->color('purple')
            ->modalHeading(fn (Interessado $record): string => "✨ Dossiê Estratégico IA: {$record->pessoa?->nome}")
            ->modalDescription('Análise profunda de perfil, dores, nível de maturidade e roteiro de vendas gerados em tempo real pela inteligência artificial.')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Salvar no Histórico do Lead')
            ->modalCancelActionLabel('Fechar')
            ->extraModalFooterActions(fn (Action $action): array => [
                Action::make('exportarPdf')
                    ->label('Exportar para PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->action(function (Interessado $record, array $data) {
                        $service = app(CrmIaVendasService::class);
                        $pdf = $service->gerarPdfDossie($record, [
                            'dossie_markdown' => $data['dossie_conteudo'] ?? null,
                            'resumo_executivo' => $data['resumo_executivo'] ?? null,
                            'temperatura_sugerida' => $data['temperatura_sugerida'] ?? null,
                            'proxima_acao_sugerida' => $data['proxima_acao_sugerida'] ?? null,
                        ]);

                        $nomeLead = Str::slug($record->pessoa?->nome ?? 'Lead');
                        $filename = "Dossie_Estrategico_{$nomeLead}_{$record->id}.pdf";

                        return response()->streamDownload(
                            fn () => print ($pdf->output()),
                            $filename,
                            ['Content-Type' => 'application/pdf']
                        );
                    }),
            ])
            ->form(function (Interessado $record): array {
                $service = app(CrmIaVendasService::class);
                $dossie = $service->gerarDossie($record);

                // Guarda em cache por 15 minutos para permitir download instantâneo via URL direta
                cache()->put("dossie_ia_lead_{$record->id}", $dossie, now()->addMinutes(15));

                $urlPdf = route('crm.interessados.dossie-pdf', $record);

                $tempBadge = match ($dossie['temperatura_sugerida']) {
                    'quente' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">🔥 Quente (Alta Probabilidade)</span>',
                    'morno' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300">🟡 Morno (Em Avaliação)</span>',
                    default => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">🔵 Frio (Sondagem Inicial)</span>',
                };

                $headerHtml = <<<HTML
<div class="p-4 mb-4 rounded-xl border border-purple-200 bg-purple-50/70 dark:bg-purple-950/20 dark:border-purple-800/40">
    <div class="flex items-center justify-between mb-2">
        <span class="text-xs uppercase tracking-wider font-semibold text-purple-700 dark:text-purple-300">Termômetro Comercial IA</span>
        <div class="flex items-center gap-2">
            <a href="{$urlPdf}" target="_blank" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-white dark:bg-slate-800 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-700 shadow-sm hover:bg-purple-100/50 dark:hover:bg-slate-700 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Exportar para PDF
            </a>
            {$tempBadge}
        </div>
    </div>
    <p class="text-sm font-medium text-slate-800 dark:text-slate-200 mb-2">
        <strong>💡 Síntese:</strong> {$dossie['resumo_executivo']}
    </p>
    <p class="text-xs text-purple-900 dark:text-purple-300 bg-purple-100/60 dark:bg-purple-900/30 p-2 rounded-lg">
        <strong>🚀 Próxima Ação Recomendada:</strong> {$dossie['proxima_acao_sugerida']}
    </p>
</div>
HTML;

                return [
                    Placeholder::make('header_dossie')
                        ->label('')
                        ->content(new HtmlString($headerHtml)),

                    Placeholder::make('dossie_visual')
                        ->label('Relatório Completo do Dossiê')
                        ->content($dossie['dossie_markdown'])
                        ->markdown()
                        ->prose()
                        ->extraAttributes([
                            'style' => 'max-height: 28rem; overflow-y: auto; padding: 1rem; border-radius: 0.75rem; border: 1px solid color-mix(in oklab, currentColor 15%, transparent);',
                        ])
                        ->columnSpanFull(),

                    // Guarda dados para exportação e histórico
                    Hidden::make('dossie_conteudo')
                        ->default($dossie['dossie_markdown']),

                    Hidden::make('resumo_executivo')
                        ->default($dossie['resumo_executivo']),

                    Hidden::make('proxima_acao_sugerida')
                        ->default($dossie['proxima_acao_sugerida']),

                    Hidden::make('temperatura_sugerida')
                        ->default($dossie['temperatura_sugerida']),

                    Toggle::make('atualizar_temperatura')
                        ->label("Atualizar temperatura do lead no funil para \"{$dossie['temperatura_sugerida']}\"")
                        ->default(true),

                    Toggle::make('registrar_historico')
                        ->label('Registrar uma entrada no Histórico de Contatos com esta análise da IA')
                        ->default(true),
                ];
            })
            ->action(function (array $data, Interessado $record): void {
                if (! empty($data['atualizar_temperatura']) && ! empty($data['temperatura_sugerida'])) {
                    $record->update(['temperatura' => $data['temperatura_sugerida']]);
                }

                if (! empty($data['registrar_historico']) && ! empty($data['dossie_conteudo'])) {
                    $tipoIa = TipoContatoInteressado::firstOrCreate(['nome' => 'Análise de IA']);

                    HistoricoContato::create([
                        'interessado_id' => $record->id,
                        'usuario_id' => auth()->id() ?? $record->usuario_id,
                        'tipo_contato_interessado_id' => $tipoIa->id,
                        'data_contato' => now(),
                        'relato' => "✨ Dossiê Estratégico gerado com IA:\n\n".$data['dossie_conteudo'],
                    ]);

                    Notification::make()
                        ->title('Dossiê registrado no histórico!')
                        ->body('As observações da IA foram salvas no histórico de contatos do lead.')
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Dossiê visualizado')
                        ->info()
                        ->send();
                }
            });
    }
}
