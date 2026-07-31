<?php

namespace App\Filament\Resources\Pessoas\Tables;

use App\Enums\CorRaca;
use App\Enums\Sexo;
use App\Filament\Exports\PessoaExporter;
use App\Models\Pessoa;
use App\Models\TemplateCracha;
use App\Models\TemplateCrachaV3;
use App\Services\Educacenso\EducacensoPessoaExporter;
use App\Services\TemplateCrachaV3Service;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class PessoasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('foto')
                    ->circular()
                    ->label('')
                    ->width(40)
                    ->height(40)
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=Pessoa&color=7F9CF5&background=EBF4FF'),

                TextColumn::make('nome')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('data_nascimento')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('cpf')
                    ->searchable(),

                TextColumn::make('nacionalidade.nome')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('naturalidade.nome')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('enderecos.logradouro')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('sexo')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('cor_raca')
                    ->label('Cor / Raça')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('estado_civil')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('profissao')
                    ->label('Profissão')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('identidade')
                    ->label('Identidade (RG)')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, Pessoa $record) {
                        $reasons = $record->getInviabilityReasons();

                        if (! empty($reasons)) {
                            Notification::make()
                                ->danger()
                                ->title('Não é possível excluir esta pessoa')
                                ->body('Existem vínculos ativos que impedem a exclusão: '.implode(', ', $reasons))
                                ->persistent()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(PessoaExporter::class)
                        ->label('Exportar Selecionados')
                        ->visible(fn (): bool => auth()->user()->can('export', Pessoa::class)),
                    BulkAction::make('imprimirCrachas')
                        ->label('Imprimir Crachá')
                        ->icon('heroicon-o-identification')
                        ->form([
                            Select::make('template_cracha_id')
                                ->label('Selecione o Modelo de Crachá')
                                ->options(fn () => TemplateCracha::pluck('nome', 'id'))
                                ->required()
                                ->searchable()
                                ->preload(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $template = TemplateCracha::find($data['template_cracha_id']);
                            if (! $template) {
                                Notification::make()
                                    ->danger()
                                    ->title('Template não encontrado')
                                    ->send();

                                return;
                            }

                            $layout = $template->dados_layout;
                            $objects = $layout['objects'] ?? [];
                            $backgroundImage = $layout['backgroundImage']['src'] ?? null;

                            // Dimensões do crachá em pontos (pixels * 0.75)
                            $crachaLargura = $template->largura * 0.75;
                            $crachaAltura = $template->altura * 0.75;

                            $pdf = Pdf::loadView('pdf.cracha-lote', [
                                'pessoas' => $records,
                                'objects' => $objects,
                                'backgroundImage' => $backgroundImage,
                                'crachaLargura' => $crachaLargura,
                                'crachaAltura' => $crachaAltura,
                            ])->setPaper('a4', 'portrait');

                            return response()->streamDownload(
                                fn () => print ($pdf->output()),
                                'crachas.pdf',
                                ['Content-Type' => 'application/pdf']
                            );
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('imprimirCrachasV3')
                        ->label('Imprimir Crachá V3 (Moveable)')
                        ->icon('heroicon-o-identification')
                        ->color('warning')
                        ->form([
                            Select::make('template_cracha_v3_id')
                                ->label('Selecione o Modelo de Crachá V3')
                                ->options(fn () => TemplateCrachaV3::pluck('nome', 'id'))
                                ->required()
                                ->searchable()
                                ->preload(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $template = TemplateCrachaV3::find($data['template_cracha_v3_id']);
                            if (! $template) {
                                Notification::make()
                                    ->danger()
                                    ->title('Template V3 não encontrado')
                                    ->send();

                                return;
                            }

                            $pessoasComTurma = collect();
                            foreach ($records as $p) {
                                $pessoasComTurma->push((object) [
                                    'pessoa' => $p,
                                    'turma' => null,
                                ]);
                            }

                            $pdf = TemplateCrachaV3Service::gerarPdf($template, $pessoasComTurma);

                            return response()->streamDownload(
                                fn () => print ($pdf->output()),
                                'crachas_v3.pdf',
                                ['Content-Type' => 'application/pdf']
                            );
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('editarLote')
                        ->label('Editar em Lote')
                        ->icon('heroicon-o-pencil-square')
                        ->form([
                            Select::make('sexo')
                                ->label('Sexo')
                                ->options(Sexo::class)
                                ->preload()
                                ->searchable(),
                            Select::make('cor_raca')
                                ->label('Cor / Raça')
                                ->options(CorRaca::class)
                                ->preload()
                                ->searchable(),
                            Select::make('nacionalidade_id')
                                ->label('Nacionalidade')
                                ->relationship('nacionalidade', 'nome')
                                ->preload()
                                ->searchable(),
                            Select::make('estado_civil')
                                ->label('Estado Civil')
                                ->options([
                                    'Solteiro(a)' => 'Solteiro(a)',
                                    'Casado(a)' => 'Casado(a)',
                                    'Divorciado(a)' => 'Divorciado(a)',
                                    'Viúvo(a)' => 'Viúvo(a)',
                                    'União Estável' => 'União Estável',
                                ])
                                ->searchable(),
                            TextInput::make('profissao')
                                ->label('Profissão'),
                            TextInput::make('identidade')
                                ->label('Identidade (RG)'),

                        ])
                        ->action(function (Collection $records, array $data): void {
                            $updateData = array_filter([
                                'sexo' => $data['sexo'] ?? null,
                                'cor_raca' => $data['cor_raca'] ?? null,
                                'nacionalidade_id' => $data['nacionalidade_id'] ?? null,
                                'estado_civil' => $data['estado_civil'] ?? null,
                                'profissao' => $data['profissao'] ?? null,
                                'identidade' => $data['identidade'] ?? null,
                            ], fn ($value) => filled($value));

                            try {
                                foreach ($records as $record) {
                                    if (! empty($updateData)) {
                                        $record->update($updateData);
                                    }

                                }

                                Notification::make()
                                    ->title('Atualização em lote concluída')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Log::error('Erro no Bulk Edit de Pessoas: '.$e->getMessage());

                                Notification::make()
                                    ->title('Erro na atualização em lote')
                                    ->body('Verifique os logs do sistema para mais detalhes.')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('exportarEducacenso')
                        ->label('Exportar para Educacenso')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $exporter = new EducacensoPessoaExporter;
                            $txtContent = $exporter->export($records);

                            $filename = 'educacenso_pessoas_'.now()->format('Ymd_His').'.txt';

                            return response()->streamDownload(
                                fn () => print ($txtContent),
                                $filename,
                                ['Content-Type' => 'text/plain; charset=UTF-8']
                            );
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->before(function (DeleteBulkAction $action, Collection $records) {
                            $totalInviabilized = 0;
                            $messages = [];

                            foreach ($records as $record) {
                                $reasons = $record->getInviabilityReasons();
                                if (! empty($reasons)) {
                                    $totalInviabilized++;
                                    $messages[] = "{$record->nome}: ".implode(', ', $reasons);
                                }
                            }

                            if ($totalInviabilized > 0) {
                                Notification::make()
                                    ->danger()
                                    ->title('Algumas pessoas não puderam ser excluídas')
                                    ->body('Os seguintes registros possuem vínculos impeditivos: '.implode(' | ', $messages))
                                    ->persistent()
                                    ->send();

                                // Se todos estiverem inviabilizados, cancela a ação total
                                if ($totalInviabilized === $records->count()) {
                                    $action->cancel();
                                } else {
                                    // Aqui é mais complexo, o Filament DeleteBulkAction não permite remover itens da coleção no before de forma fácil para continuar com o resto.
                                    // Uma abordagem comum é filtrar a coleção se o Filament permitir ou usar uma ação customizada.
                                    // Mas por segurança e para atender o pedido de "exibir o motivo", vamos cancelar a operação em lote se houver qualquer impedimento, ou informar.

                                    // Vou cancelar tudo para garantir integridade e clareza.
                                    $action->cancel();
                                }
                            }
                        }),
                ]),
            ])
            ->stackedOnMobile();
    }
}
