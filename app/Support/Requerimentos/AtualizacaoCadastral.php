<?php

declare(strict_types=1);

namespace App\Support\Requerimentos;

use App\Models\AtendimentoChamado;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

/**
 * Atualização de telefone e de e-mail do responsável (Onda 80; LGPD, art. 18, III). O endereço é pedido em texto livre e a
 * secretaria o digita na ficha, porque o cadastro de endereço é estruturado (CEP, cidade, bairro) e uma correção automática
 * a partir de texto arriscaria gravar o endereço errado.
 */
final class AtualizacaoCadastral implements AplicaRequerimento
{
    private const CAMPOS_APLICAVEIS = ['telefone' => 'Telefone', 'email' => 'E-mail'];

    public function diferencas(AtendimentoChamado $chamado): array
    {
        $pessoa = $chamado->solicitante;
        $dados = $chamado->dados ?? [];
        $diferencas = [];

        foreach (self::CAMPOS_APLICAVEIS as $campo => $rotulo) {
            if (filled($dados[$campo] ?? null)) {
                $diferencas[] = ['rotulo' => $rotulo, 'antes' => $pessoa?->{$campo}, 'depois' => (string) $dados[$campo]];
            }
        }

        if (filled($dados['endereco'] ?? null)) {
            $diferencas[] = ['rotulo' => 'Endereço informado (atualizar à mão na ficha)', 'antes' => null, 'depois' => (string) $dados['endereco']];
        }

        return $diferencas;
    }

    public function opcoes(): array
    {
        return [];
    }

    public function aplicar(AtendimentoChamado $chamado, User $por, array $opcoes = []): string
    {
        $pessoa = $chamado->solicitante;

        if (! $pessoa) {
            throw new \DomainException('O pedido não tem um responsável vinculado para atualizar.');
        }

        $dados = $chamado->dados ?? [];
        $mudancas = [];

        foreach (array_keys(self::CAMPOS_APLICAVEIS) as $campo) {
            if (filled($dados[$campo] ?? null)) {
                $mudancas[$campo] = trim((string) $dados[$campo]);
            }
        }

        if ($mudancas === []) {
            throw new \DomainException('O pedido não traz telefone nem e-mail para aplicar. Atualize o endereço à mão na ficha e conclua o atendimento.');
        }

        if (isset($mudancas['email']) && Validator::make($mudancas, ['email' => 'email'])->fails()) {
            throw new \DomainException('O e-mail informado não é válido. Peça a correção à família.');
        }

        $pessoa->update($mudancas);

        return 'Atualizamos no cadastro: '.collect(array_keys($mudancas))->map(fn (string $campo): string => mb_strtolower(self::CAMPOS_APLICAVEIS[$campo]))->implode(' e ').'.';
    }
}
