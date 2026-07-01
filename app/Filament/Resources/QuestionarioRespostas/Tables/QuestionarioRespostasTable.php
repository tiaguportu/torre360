<?php

namespace App\Filament\Resources\QuestionarioRespostas\Tables;

use App\Filament\Resources\QuestionarioRespostas\QuestionarioRespostaResource;
use App\Filament\Resources\Questionarios\QuestionarioResource;
use App\Models\QuestionarioResposta;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionarioRespostasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('questionario.titulo')
                    ->label('Questionário')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Respondente')
                    ->placeholder('Anônimo')
                    ->searchable(),
                TextColumn::make('perfil_institucional')
                    ->label('Perfil')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'enviado' => 'success',
                        'pendente' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('fim_preenchimento')
                    ->label('Data de Envio')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('dar_feedback')
                    ->label('Dar Feedback')
                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                    ->color('primary')
                    ->url(fn (QuestionarioResposta $record) => QuestionarioResource::getUrl('responder', ['record' => $record->questionario_id]).'?parent_id='.$record->id),
            ])
            ->headerActions([
                Action::make('export_csv')
                    ->label('Exportar CSV')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(fn () => static::exportToCsv()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('comparar_respostas')
                        ->label('Comparar Respostas')
                        ->icon('heroicon-o-arrows-right-left')
                        ->action(fn (Collection $records) => redirect(QuestionarioRespostaResource::getUrl('comparar', ['ids' => $records->pluck('id')->toArray()])))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }

    protected static function exportToCsv(): StreamedResponse
    {
        $respostas = QuestionarioResposta::with(['questionario', 'user', 'perguntaRespostas.pergunta'])->get();

        $response = new StreamedResponse(function () use ($respostas) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM para Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Cabeçalho
            fputcsv($handle, [
                'ID Resposta',
                'Questionário',
                'Respondente',
                'Perfil',
                'Data Envio',
                'Pergunta',
                'Resposta',
            ], ';');

            foreach ($respostas as $resposta) {
                foreach ($resposta->perguntaRespostas as $detalhe) {
                    $valorResposta = $detalhe->resposta_texto;

                    if (is_array($detalhe->resposta_json)) {
                        $valorResposta = implode(', ', $detalhe->resposta_json);
                    }

                    fputcsv($handle, [
                        $resposta->id,
                        $resposta->questionario->titulo,
                        $resposta->user->name ?? 'Anônimo',
                        $resposta->perfil_institucional,
                        $resposta->fim_preenchimento->format('d/m/Y H:i:s'),
                        $detalhe->pergunta->enunciado ?? 'N/A',
                        $valorResposta,
                    ], ';');
                }
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="respostas_questionarios_'.now()->format('Y-m-d_H-i').'.csv"');

        return $response;
    }
}
