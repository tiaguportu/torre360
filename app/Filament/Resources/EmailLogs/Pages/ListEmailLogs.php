<?php

namespace App\Filament\Resources\EmailLogs\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\EmailLogs\EmailLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmailLogs extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = EmailLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaCadastro(
                '📧', 'E-mails Enviados', 'Histórico dos e-mails disparados pelo sistema.', 'EmailLog',
                'Veja a data de envio, o assunto, o destinatário e o usuário relacionado a cada e-mail.',
                'Registre manualmente um envio.',
                'Corrija os dados de um registro de envio.',
                extras: [['👁️', 'Visualizar', 'Abra um registro para ver os detalhes do e-mail.']],
                dica: 'Use esta lista para conferir se um aviso (cobrança, convite, notificação) realmente foi enviado.',
            ),
        ];
    }
}
