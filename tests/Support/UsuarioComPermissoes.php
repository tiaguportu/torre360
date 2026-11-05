<?php

namespace Tests\Support;

use App\Models\Pessoa;
use App\Models\User;

/**
 * Usuário sem banco para testar policies: responde `can()` por uma lista de permissões e registra o
 * que foi perguntado. Papel, vínculo de professor e visão de todos os leads são informados no construtor.
 */
class UsuarioComPermissoes extends User
{
    /** @var list<string> permissões perguntadas por `can()`, na ordem */
    public array $perguntadas = [];

    /**
     * @param  list<string>  $permissoes
     * @param  list<string>  $papeis
     */
    public function __construct(
        private array $permissoes = [],
        ?int $id = null,
        private bool $professor = false,
        private array $papeis = [],
        private bool $veTodosLeads = false,
    ) {
        parent::__construct();

        if ($id !== null) {
            $this->setAttribute('id', $id);
        }
    }

    /** Responde sim a qualquer permissão: prova que uma regra nega mesmo a quem tem tudo. */
    public bool $temTudo = false;

    public static function comTudo(?int $id = null): self
    {
        $usuario = new self([], $id);
        $usuario->temTudo = true;

        return $usuario;
    }

    public function can($abilities, $arguments = []): bool
    {
        $this->perguntadas[] = (string) $abilities;

        return $this->temTudo || in_array($abilities, $this->permissoes, true);
    }

    /**
     * Liga o usuário a pessoas (já carregadas, sem consultar o banco): é por elas que policies e escopos
     * descobrem se o registro é "do próprio usuário".
     *
     * @param  list<int>  $pessoaIds
     */
    public function comPessoas(array $pessoaIds): self
    {
        $this->setRelation('pessoas', collect(array_map(
            fn (int $id) => (new Pessoa)->forceFill(['id' => $id]),
            $pessoaIds,
        )));

        return $this;
    }

    public function atuaComoProfessor(): bool
    {
        return $this->professor;
    }

    public function podeVerTodosLeads(): bool
    {
        return $this->veTodosLeads;
    }

    public function hasRole($roles, ?string $guard = null): bool
    {
        return count(array_intersect((array) $roles, $this->papeis)) > 0;
    }
}
