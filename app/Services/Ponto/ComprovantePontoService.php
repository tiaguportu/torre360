<?php

declare(strict_types=1);

namespace App\Services\Ponto;

use App\Models\PontoConfiguracao;
use App\Models\PontoRegistro;
use App\Support\Formatacao;

/**
 * Dados do comprovante de registro de ponto: NSR, empregador (com CNPJ), trabalhador (com CPF), data e hora, hash e
 * assinatura (Portaria MTP 671/2021, art. 79). Sem assinador ICP-Brasil, o comprovante sai marcado como NÃO ASSINADO.
 */
class ComprovantePontoService
{
    public function __construct(
        private readonly AssinadorPonto $assinador,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dados(PontoRegistro $registro): array
    {
        $registro->loadMissing(['funcionario.pessoa', 'unidade.instituicaoEnsino']);

        $unidade = $registro->unidade;
        $pessoa = $registro->funcionario->pessoa;
        $config = PontoConfiguracao::query()->where('unidade_id', $registro->unidade_id)->first();

        $conteudo = implode('|', [$registro->unidade_id, $registro->nsr, $registro->funcionario_id, $registro->momento->format('Y-m-d H:i:s'), $registro->fuso, $registro->hash]);
        $assinatura = $this->assinador->assinar($conteudo);

        return [
            'nsr' => $registro->nsr,
            'empregador' => $unidade->instituicaoEnsino?->nome ?? $unidade->nome,
            'estabelecimento' => $unidade->nome,
            'cnpj' => $unidade->cnpj ?: $unidade->instituicaoEnsino?->cnpj,
            'trabalhador' => $pessoa?->nome,
            'cpf' => Formatacao::cpf($pessoa?->cpf),
            'data_hora' => $registro->momento->format('d/m/Y H:i:s'),
            'fuso' => $registro->fuso,
            'origem' => $registro->origem->getLabel(),
            'hash' => $registro->hash,
            'hora_legal_ok' => $registro->hora_legal_ok,
            'modo' => $config?->modo->getLabel() ?? 'Controle interno (sem valor de REP)',
            'oficial' => (bool) $config?->modo->ehOficial(),
            'assinado' => $assinatura !== null,
            'assinatura' => $assinatura,
            'assinador' => $this->assinador->nome(),
        ];
    }
}
