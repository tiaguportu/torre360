<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CategoriaDenuncia;
use App\Enums\StatusDenuncia;
use App\Enums\TipoMovimentacaoDenuncia;
use App\Models\Denuncia;
use App\Models\DenunciaComite;
use App\Models\DenunciaMovimentacao;
use App\Models\User;
use App\Notifications\DenunciaRecebidaNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Canal de ouvidoria e convivência escolar (Ondas 35 e 63).
 */
class OuvidoriaService
{
    /** Sem I, O, 0 e 1, que se confundem ao ditar o código por telefone. */
    private const ALFABETO_DO_CODIGO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Registra a manifestação. Anônima: não grava usuário, nome nem contato. Identificada: guarda os dados de contato
     * criptografados. O código de acompanhamento volta uma única vez; depois só o hash existe.
     *
     * @param  array{categoria: string, descricao: string, local_data?: ?string, envolvidos?: ?list<array{nome: string, papel: string}>}  $dados
     * @param  array{nome?: ?string, email?: ?string, telefone?: ?string}|null  $identificacao  nulo quando anônima
     * @return array{denuncia: Denuncia, codigo: string}
     */
    public function registrar(array $dados, bool $anonima, ?array $identificacao = null, ?User $autor = null): array
    {
        $categoria = CategoriaDenuncia::from($dados['categoria']);
        $codigo = $this->gerarCodigo();
        $suspeita = $categoria->exigeComunicacaoAoConselhoTutelar();

        $prazoDias = $suspeita
            ? (int) config('ouvidoria.prazo_suspeita_violencia_dias', 2)
            : (int) config('ouvidoria.prazo_dias', 10);

        $denuncia = DB::transaction(function () use ($dados, $categoria, $codigo, $anonima, $identificacao, $autor, $suspeita, $prazoDias): Denuncia {
            $denuncia = Denuncia::create([
                'codigo_hash' => Denuncia::hashDoCodigo($codigo),
                'categoria' => $categoria,
                'descricao' => trim($dados['descricao']),
                'local_data' => filled($dados['local_data'] ?? null) ? trim((string) $dados['local_data']) : null,
                'envolvidos' => $this->limparEnvolvidos($dados['envolvidos'] ?? []),
                'anonima' => $anonima,
                'identificacao' => $anonima ? null : array_filter($identificacao ?? [], fn ($v) => filled($v)),
                // Denúncia anônima nunca guarda o usuário, mesmo que a pessoa estivesse logada.
                'user_id' => $anonima ? null : $autor?->id,
                'status' => StatusDenuncia::Recebida,
                'suspeita_violencia' => $suspeita,
                'prazo_em' => now()->addDays($prazoDias),
            ]);

            $this->movimentar($denuncia, null, TipoMovimentacaoDenuncia::Recebida, 'Manifestação recebida pela ouvidoria.', true);

            return $denuncia;
        });

        $this->avisarComite($denuncia);

        return ['denuncia' => $denuncia, 'codigo' => $codigo];
    }

    /**
     * Acompanhamento sem login: protocolo e código. Qualquer divergência devolve nulo (a tela não diz qual dos dois errou).
     */
    public function consultar(string $protocolo, string $codigo): ?Denuncia
    {
        $denuncia = Denuncia::query()->where('protocolo', strtoupper(trim($protocolo)))->first();

        if (! $denuncia) {
            // Faz a mesma conta do caminho feliz para não revelar por tempo se o protocolo existe.
            hash_equals(Denuncia::hashDoCodigo('0'), Denuncia::hashDoCodigo($codigo));

            return null;
        }

        return $denuncia->codigoConfere($codigo) ? $denuncia : null;
    }

    /**
     * O que o autor pode ver no acompanhamento.
     *
     * @return Collection<int, DenunciaMovimentacao>
     */
    public function movimentacoesVisiveisAoAutor(Denuncia $denuncia): Collection
    {
        return $denuncia->movimentacoes()->where('visivel_ao_autor', true)->get();
    }

    public function complementarPeloAutor(Denuncia $denuncia, string $texto): DenunciaMovimentacao
    {
        if ($denuncia->status->estaEncerrada()) {
            throw new \DomainException('A manifestação já foi encerrada. Faça uma nova manifestação se houver fato novo.');
        }

        return $this->movimentar($denuncia, null, TipoMovimentacaoDenuncia::ComplementoDoAutor, $texto, true);
    }

    public function movimentar(Denuncia $denuncia, ?User $por, TipoMovimentacaoDenuncia $tipo, string $texto, ?bool $visivelAoAutor = null): DenunciaMovimentacao
    {
        return DenunciaMovimentacao::create([
            'denuncia_id' => $denuncia->id,
            'user_id' => $por?->id,
            'tipo' => $tipo,
            'texto' => trim($texto),
            'visivel_ao_autor' => $visivelAoAutor ?? $tipo->visivelAoAutorPorPadrao(),
        ]);
    }

    /**
     * Assume a apuração (a atualização é atômica) e muda a situação para "em apuração".
     */
    public function assumir(Denuncia $denuncia, User $por): bool
    {
        $atualizadas = Denuncia::query()
            ->whereKey($denuncia->id)
            ->whereNull('responsavel_user_id')
            ->update(['responsavel_user_id' => $por->id, 'status' => StatusDenuncia::EmApuracao->value]);

        $denuncia->refresh();

        if ($atualizadas === 1) {
            $this->movimentar($denuncia, $por, TipoMovimentacaoDenuncia::Apuracao, 'Apuração iniciada.', false);
        }

        return $atualizadas === 1;
    }

    public function encerrar(Denuncia $denuncia, User $por, StatusDenuncia $status, string $conclusao): Denuncia
    {
        if (! $status->estaEncerrada()) {
            throw new \DomainException('Escolha concluída ou arquivada.');
        }

        if (blank($conclusao)) {
            throw new \DomainException('Escreva a conclusão: o autor recebe a devolutiva pelo acompanhamento.');
        }

        DB::transaction(function () use ($denuncia, $por, $status, $conclusao): void {
            $denuncia->forceFill([
                'status' => $status,
                'conclusao' => trim($conclusao),
                'encerrada_em' => now(),
                'responsavel_user_id' => $denuncia->responsavel_user_id ?: $por->id,
            ])->save();

            $this->movimentar($denuncia, $por, TipoMovimentacaoDenuncia::Encerramento, trim($conclusao), true);
        });

        return $denuncia->refresh();
    }

    private function avisarComite(Denuncia $denuncia): void
    {
        $membros = DenunciaComite::query()
            ->where(fn ($q) => $q->whereNull('categoria')->orWhere('categoria', $denuncia->categoria->value))
            ->pluck('user_id')
            ->unique();

        User::query()->whereIn('id', $membros)->ativos()->get()
            ->each(fn (User $usuario) => $usuario->notify(new DenunciaRecebidaNotification($denuncia->id)));
    }

    private function gerarCodigo(): string
    {
        $alfabeto = self::ALFABETO_DO_CODIGO;
        $codigo = '';

        for ($i = 0; $i < 10; $i++) {
            $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }

        return substr($codigo, 0, 5).'-'.substr($codigo, 5);
    }

    /**
     * @return list<array{nome: string, papel: string}>|null
     */
    private function limparEnvolvidos(mixed $envolvidos): ?array
    {
        if (! is_array($envolvidos)) {
            return null;
        }

        $limpos = [];

        foreach ($envolvidos as $envolvido) {
            $nome = Str::limit(trim((string) ($envolvido['nome'] ?? '')), 120, '');

            if ($nome !== '') {
                $limpos[] = ['nome' => $nome, 'papel' => (string) ($envolvido['papel'] ?? 'nao_informado')];
            }
        }

        return $limpos === [] ? null : $limpos;
    }
}
