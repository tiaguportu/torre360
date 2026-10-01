<?php

namespace App\Filament\Resources\Bancos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Bancos\BancoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBancos extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = BancoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaCadastro('🏦', 'Bancos', 'Contas bancárias da instituição, usadas na conciliação.', 'Banco',
                'Veja nome, código BACEN, agência, conta, chave PIX e se o banco está ativo.', 'Cadastre uma nova conta bancária.', 'Atualize dados da conta ou desative um banco.',
                dica: 'Apenas bancos ativos aparecem ao importar um extrato em Transações Bancárias.'),
        ];
    }
}
