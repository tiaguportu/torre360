<?php

namespace App\Filament\Resources\Preceptorias\Pages;

use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePreceptoria extends CreateRecord
{
    protected static string $resource = PreceptoriaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $datas = $data['datas'] ?? [];
        unset($data['datas']);

        if (empty($datas)) {
            $datas = [$data['data'] ?? now()->format('Y-m-d')];
        }

        $records = [];
        foreach ($datas as $d) {
            $dataStr = is_array($d) ? ($d['data'] ?? current($d)) : $d;

            $recordData = array_merge($data, [
                'data' => $dataStr,
            ]);

            $records[] = static::getModel()::create($recordData);
        }

        $quantidade = count($records);
        if ($quantidade > 1) {
            Notification::make()
                ->title("{$quantidade} preceptorias criadas com sucesso!")
                ->success()
                ->send();
        }

        return $records[0];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Criar Preceptoria')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $html = '<p>Nesta página você realiza o cadastro de novos horários/instâncias de preceptoria.</p>';
        $html .= '<h3>Instruções de Preenchimento:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Ciclo de Preceptoria:</strong> Selecione o ciclo letivo ou avaliativo correspondente.</li>';
        $html .= '<li><strong>Datas das Preceptorias:</strong> Você pode selecionar uma ou várias datas. Ao salvar, o sistema criará automaticamente uma instância de preceptoria para cada dia adicionado.</li>';
        $html .= '<li><strong>Horários:</strong> Defina o horário de início e término da preceptoria.</li>';
        $html .= '<li><strong>Vínculos:</strong> Selecione o professor responsável e, se aplicável, o aluno/matrícula a quem o horário se destina.</li>';
        $html .= '</ul>';

        return $html;
    }
}
