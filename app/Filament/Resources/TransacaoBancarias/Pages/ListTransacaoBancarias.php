<?php

namespace App\Filament\Resources\TransacaoBancarias\Pages;

use App\Filament\Resources\TransacaoBancarias\TransacaoBancariaResource;
use App\Models\Banco;
use App\Services\ConciliacaoBancariaService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListTransacaoBancarias extends ListRecords
{
    protected static string $resource = TransacaoBancariaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importarExtrato')
                ->label('Importar Extrato (.ofx / .csv)')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('info')
                ->form([
                    Select::make('banco_id')
                        ->label('Banco')
                        ->options(Banco::where('is_active', true)->pluck('nome', 'id'))
                        ->required(),
                    FileUpload::make('arquivo')
                        ->label('Arquivo')
                        ->acceptedFileTypes(['application/x-ofx', 'text/csv', 'text/plain'])
                        ->mimeTypeMap([
                            'ofx' => 'application/x-ofx',
                        ])
                        ->required()
                        ->disk('local')
                        ->directory('imports/extratos'),
                ])
                ->action(function (array $data, ConciliacaoBancariaService $service) {
                    $filePath = Storage::disk('local')->path($data['arquivo']);
                    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                    $content = file_get_contents($filePath);

                    $importedCount = 0;

                    // Verifica se o conteúdo parece ser OFX (mesmo que a extensão seja outra)
                    if (str_contains($content, '<OFX>') || str_contains($content, 'OFXHEADER') || strtolower($extension) === 'ofx') {
                        $importedCount = $service->processarOfx($content, (int) $data['banco_id']);
                    } else {
                        $importedCount = $service->processarCsv($filePath, (int) $data['banco_id']);
                    }

                    Notification::make()
                        ->title('Importação concluída!')
                        ->body("{$importedCount} novas transações foram importadas.")
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
