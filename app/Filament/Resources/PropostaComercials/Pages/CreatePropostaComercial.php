<?php

namespace App\Filament\Resources\PropostaComercials\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PropostaComercials\PropostaComercialResource;
use App\Models\Interessado;
use App\Models\PropostaComercial;
use App\Services\RevenueManagementService;
use App\Support\HelpContent;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePropostaComercial extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = PropostaComercialResource::class;

    public function mount(): void
    {
        parent::mount();

        $interessadoId = request()->query('interessado_id');
        if ($interessadoId) {
            $lead = Interessado::with(['pessoa', 'dependentes.serie.curso', 'dependentes.unidade'])->find($interessadoId);
            if ($lead) {
                $dados = [
                    'interessado_id' => $lead->id,
                ];
                if ($lead->pessoa) {
                    $dados['responsavel_nome'] = $lead->pessoa->nome;
                    $dados['responsavel_telefone'] = $lead->pessoa->telefone;
                    $dados['responsavel_email'] = $lead->pessoa->email;
                }
                $dep = $lead->dependentes->first();
                if ($dep) {
                    $dados['aluno_nome'] = $dep->nome_crianca;
                    if ($dep->unidade_id) {
                        $dados['unidade_id'] = $dep->unidade_id;
                    }
                    if ($dep->serie?->curso_id) {
                        $dados['curso_id'] = $dep->serie->curso_id;
                    }
                    if ($dep->serie_id) {
                        $dados['serie_id'] = $dep->serie_id;
                    }
                }
                $this->form->fill(array_merge($this->data ?? [], $dados));
            }
        }
    }

    protected function getHeaderActions(): array
    {
        $conteudo = HelpContent::make(
            '🆕',
            'Criar Proposta Comercial',
            'Selecione o lead ou informe os dados da família, escolha a turma e utilize o simulador em tempo real para calcular a mensalidade com o desconto pretendido.'
        )
            ->secao('💡 Dicas de Simulação', [
                ['🟢', 'Até 7.0% de Desconto', 'Aprovação imediata na alçada do consultor comercial.'],
                ['🟡', 'De 7.1% a 15.0%', 'Requer validação da Coordenação Comercial ou Secretaria.'],
                ['🔴', 'Acima de 15.0%', 'Escala para autorização da Diretoria Geral / Mantenedora.'],
            ]);

        return [
            $this->ajudaAction('Criar Proposta Comercial', $conteudo),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $service = app(RevenueManagementService::class);

        $data['codigo'] = PropostaComercial::gerarCodigo();
        $data['solicitado_por_user_id'] = auth()->id();

        // Recalcula os valores consolidados para garantir consistência financeira
        $calc = $service->calcularValores(
            (float) ($data['valor_tabela_mensal'] ?? 0),
            (string) ($data['tipo_desconto'] ?? 'percentual'),
            (float) ($data['desconto_solicitado'] ?? 0),
            (int) ($data['quantidade_alunos'] ?? 1),
            (int) ($data['quantidade_parcelas'] ?? 12)
        );

        $data['valor_desconto_mensal'] = $calc['valor_desconto_mensal'];
        $data['valor_liquido_mensal'] = $calc['valor_liquido_mensal'];
        $data['valor_total_anual'] = $calc['valor_total_anual'];
        $data['nivel_alcada_necessario'] = $calc['nivel_alcada']->value;

        /** @var PropostaComercial $proposta */
        $proposta = static::getModel()::make($data);

        return $service->processarCriacao($proposta, auth()->user());
    }
}
