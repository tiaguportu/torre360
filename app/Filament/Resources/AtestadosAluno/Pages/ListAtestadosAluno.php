<?php

declare(strict_types=1);

namespace App\Filament\Resources\AtestadosAluno\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\AtestadosAluno\AtestadoAlunoResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAtestadosAluno extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = AtestadoAlunoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Registrar atestado'),
            $this->ajudaAction('Atestados e Abono de Faltas', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('🩺', 'Atestados e Abono de Faltas', 'Atestados dos alunos e a justificativa automática das faltas do período.')
            ->passos('🔁 Como funciona', [
                'Clique em "Registrar atestado", escolha o aluno, informe o primeiro e o último dia do afastamento e anexe o PDF ou a foto.',
                'Ao salvar, todas as faltas do aluno nesse período viram justificadas ("Atestado médico (dd/mm/aaaa a dd/mm/aaaa)").',
                'Marque "Registrar também como abono de falta" só se a escola abona essas faltas: aí elas deixam de contar na frequência.',
                'As faltas lançadas depois pelo professor, dentro do período, já entram justificadas (e abonadas, se for o caso).',
                'A família não recebe o aviso diário de falta justificada.',
            ])
            ->secao('⚖️ Justificar não é abonar', [
                ['📝', 'Só justificada', 'A falta tem motivo registrado, mas continua contando na frequência (boletim, Portal, Censo, risco de evasão).'],
                ['✅', 'Justificada e abonada', 'A falta tem motivo e o abono registrado: deixa de contar como falta. O abono aparece no atestado e em cada falta.'],
                ['🧾', 'Conselho Tutelar', 'A abonada sai da apuração; a só justificada entra como justificada e a injustificada, como injustificada.'],
            ])
            ->secao('📌 Bom saber', [
                ['✏️', 'Corrigir período / abono', 'As faltas que ficarem fora do novo período voltam a injustificadas; as novas do período são justificadas. Mudar o abono vale para todas as faltas do atestado.'],
                ['🚫', 'Cancelar', 'Use quando o atestado foi lançado por engano ou não é válido. As faltas que ele justificou (e abonou) voltam a injustificadas e a contar na frequência. O comprovante continua guardado.'],
                ['✍️', 'Justificativa manual', 'Uma falta que já foi justificada ou abonada por outra pessoa (em Frequência Escolar) não é alterada pelo atestado, nem desfeita ao cancelá-lo.'],
                ['🔒', 'Privacidade', 'O atestado traz dado de saúde: o comprovante só abre para quem tem a permissão, e a observação fica criptografada.'],
            ])
            ->alerta('Abonar falta depende do regimento da escola. Em dúvida, registre só a justificativa: dá para marcar o abono depois, em "Corrigir período / abono".');
    }
}
