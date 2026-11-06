<?php

declare(strict_types=1);

namespace App\Support\Qualificacao;

/**
 * Um campo do roteiro de qualificação do lead: o que a escola precisa saber da família antes de marcar a visita.
 *
 * `nivel` diz onde o dado mora: `familia` na tabela `interessado_qualificacao` (um valor para o lead inteiro) e `aluno`
 * em `interessado_dependente` (um valor por aluno, porque irmãos podem estar em séries e escolas diferentes).
 *
 * `pergunta`, `ordem`, `gate` e `ativo` são os que a secretaria personaliza (ver {@see Roteiro::todos()}): o texto
 * sugerido da pergunta, a posição na conversa, se o campo é necessário para marcar a visita e se entra no roteiro.
 */
final class CampoQualificacao
{
    public function __construct(
        public readonly string $chave,
        public readonly string $nivel,
        public readonly string $dimensao,
        public readonly string $rotulo,
        public readonly string $pergunta,
        public readonly bool $gate = false,
        public readonly bool $perguntarAutomaticamente = true,
        public readonly int $ordem = 0,
        public readonly bool $ativo = true,
    ) {}

    public function ehDoAluno(): bool
    {
        return $this->nivel === Roteiro::NIVEL_ALUNO;
    }

    public function ehDaFamilia(): bool
    {
        return $this->nivel === Roteiro::NIVEL_FAMILIA;
    }
}
