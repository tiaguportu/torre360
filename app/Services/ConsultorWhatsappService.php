<?php

namespace App\Services;

use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Repasse de leads ao consultor responsável por WhatsApp (link `wa.me`).
 *
 * Monta a mensagem que a secretaria/gestão envia ao consultor: o contato direto do
 * interessado (link `wa.me` dele, para o consultor só clicar) e um resumo do que já
 * foi conversado. O telefone do consultor vem da Pessoa vinculada ao usuário
 * (`pessoa_user`); sem telefone, o link abre o WhatsApp sem destinatário e quem
 * enviou escolhe o contato.
 *
 * Tudo roda no render da listagem, então as relações abaixo precisam estar
 * carregadas (a tabela já faz o eager loading; `loadMissing` é só rede de segurança).
 */
class ConsultorWhatsappService
{
    /**
     * Relações usadas para montar as mensagens.
     *
     * @var list<string>
     */
    public const RELACOES = [
        'pessoa',
        'status',
        'origem',
        'usuario.pessoas',
        'dependentes.serie',
        'proximaVisita',
        'historicos.tipoContato',
    ];

    /** Teto de caracteres da mensagem antes do encode, para o link `wa.me` não estourar a URL. */
    private const LIMITE_MENSAGEM = 1500;

    private const MAX_CONTATOS_NO_RESUMO = 3;

    private const LIMITE_RELATO = 160;

    private const LIMITE_OBSERVACOES = 300;

    private const TEMPERATURAS = [
        'quente' => '🔥 Quente',
        'morno' => '🟡 Morno',
        'frio' => '🔵 Frio',
    ];

    /**
     * Só dígitos, com DDI 55 quando o número veio sem ele. Retorna null se não houver número.
     */
    public function normalizarTelefone(?string $telefone): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $telefone);

        if ($digitos === '') {
            return null;
        }

        return strlen($digitos) <= 11 ? '55'.$digitos : $digitos;
    }

    /**
     * Telefone do consultor, já normalizado: prefere a Pessoa que é o próprio usuário
     * e, na falta dela, a primeira Pessoa vinculada que tenha telefone.
     */
    public function telefoneDoConsultor(?User $consultor): ?string
    {
        if (! $consultor) {
            return null;
        }

        $consultor->loadMissing('pessoas');

        $comTelefone = $consultor->pessoas->filter(fn (Pessoa $pessoa): bool => filled($pessoa->telefone));
        $pessoa = $comTelefone->firstWhere('user_id', $consultor->id) ?? $comTelefone->first();

        return $this->normalizarTelefone($pessoa?->telefone);
    }

    public function consultorTemTelefone(Interessado $interessado): bool
    {
        $interessado->loadMissing('usuario.pessoas');

        return $this->telefoneDoConsultor($interessado->usuario) !== null;
    }

    public function url(?string $telefoneDoConsultor, string $mensagem): string
    {
        $params = [];
        if (filled($telefoneDoConsultor)) {
            $params['phone'] = $telefoneDoConsultor;
        }
        $params['text'] = $mensagem;

        return 'https://api.whatsapp.com/send?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Link para o consultor responsável pelo lead, com a mensagem completa.
     */
    public function urlParaInteressado(Interessado $interessado): string
    {
        $interessado->loadMissing(self::RELACOES);

        return $this->url(
            $this->telefoneDoConsultor($interessado->usuario),
            $this->mensagemDoInteressado($interessado),
        );
    }

    /**
     * Mensagem completa de um lead: contato direto e resumo do que já foi conversado.
     */
    public function mensagemDoInteressado(Interessado $interessado): string
    {
        $interessado->loadMissing(self::RELACOES);

        $cabecalho = $this->linhasDoLead($interessado);
        $historico = $this->linhasDoHistorico($interessado);
        $observacoes = $this->linhaUnica($interessado->observacoes, self::LIMITE_OBSERVACOES);

        $mensagem = $this->compor($cabecalho, $historico, $observacoes);

        // Se estourar o teto, abre mão dos contatos mais antigos primeiro.
        while (mb_strlen($mensagem) > self::LIMITE_MENSAGEM && $historico !== []) {
            array_pop($historico);
            $mensagem = $this->compor($cabecalho, $historico, $observacoes);
        }

        return $mensagem;
    }

    /**
     * Mensagem compacta com vários leads do mesmo consultor (uma linha por lead).
     *
     * @param  Collection<int, Interessado>  $interessados
     */
    public function mensagemEmLote(User $consultor, Collection $interessados): string
    {
        $total = $interessados->count();
        $abertura = 'Olá, '.$this->primeiroNome($consultor)."! Seguem {$total} lead(s) para você dar andamento:";

        $linhas = $interessados->values()
            ->map(fn (Interessado $interessado, int $indice): string => ($indice + 1).'. '.$this->resumoDoLead($interessado))
            ->all();

        $omitidos = 0;
        $mensagem = $this->comporLote($abertura, $linhas, $omitidos);

        while (mb_strlen($mensagem) > self::LIMITE_MENSAGEM && count($linhas) > 1) {
            array_pop($linhas);
            $omitidos++;
            $mensagem = $this->comporLote($abertura, $linhas, $omitidos);
        }

        return $mensagem;
    }

    /**
     * Agrupa os leads por consultor, já com o link `wa.me` de cada grupo.
     *
     * @param  EloquentCollection<int, Interessado>  $interessados
     * @return array{grupos: Collection<int, array{consultor: User, interessados: Collection<int, Interessado>, temTelefone: bool, url: string}>, semConsultor: Collection<int, Interessado>}
     */
    public function agruparPorConsultor(EloquentCollection $interessados): array
    {
        $interessados->loadMissing(self::RELACOES);

        [$comConsultor, $semConsultor] = $interessados->partition(fn (Interessado $interessado): bool => $interessado->usuario !== null);

        $grupos = $comConsultor
            ->groupBy('usuario_id')
            ->map(function (Collection $leads): array {
                $consultor = $leads->first()->usuario;
                $telefone = $this->telefoneDoConsultor($consultor);

                return [
                    'consultor' => $consultor,
                    'interessados' => $leads->values(),
                    'temTelefone' => $telefone !== null,
                    'url' => $this->url($telefone, $this->mensagemEmLote($consultor, $leads)),
                ];
            })
            ->values();

        return ['grupos' => $grupos, 'semConsultor' => $semConsultor->values()];
    }

    /**
     * @return list<string>
     */
    private function linhasDoLead(Interessado $interessado): array
    {
        $consultor = $interessado->usuario;
        $telefoneDoLead = $this->normalizarTelefone($interessado->pessoa?->telefone);

        $linhas = [
            $consultor
                ? 'Olá, '.$this->primeiroNome($consultor).'! Segue um lead para você dar andamento:'
                : 'Segue um lead para dar andamento:',
            '',
            '*Lead:* '.($interessado->pessoa?->nome ?? 'Sem nome'),
            $telefoneDoLead
                ? '*WhatsApp do lead:* https://wa.me/'.$telefoneDoLead
                : '*WhatsApp do lead:* telefone não informado',
        ];

        $alunos = $interessado->dependentes
            ->map(fn ($dependente): string => $dependente->serie
                ? "{$dependente->nome_crianca} ({$dependente->serie->nome})"
                : (string) $dependente->nome_crianca)
            ->implode(', ');

        if ($alunos !== '') {
            $linhas[] = '*Alunos:* '.$alunos;
        }

        $situacao = array_filter([
            $interessado->status ? '*Status:* '.$interessado->status->nome : null,
            $interessado->origem ? '*Origem:* '.$interessado->origem->nome : null,
        ]);

        if ($situacao !== []) {
            $linhas[] = implode(' · ', $situacao);
        }

        $qualificacao = array_filter([
            $interessado->temperatura ? '*Temperatura:* '.(self::TEMPERATURAS[$interessado->temperatura] ?? $interessado->temperatura) : null,
            $interessado->lead_score !== null ? '*Score:* '.$interessado->lead_score : null,
        ]);

        if ($qualificacao !== []) {
            $linhas[] = implode(' · ', $qualificacao);
        }

        if ($interessado->data_proximo_contato) {
            $linhas[] = '*Próximo contato:* '.$this->formatarDataHora($interessado->data_proximo_contato)
                .($interessado->data_proximo_contato->isPast() ? ' (atrasado)' : '');
        }

        if ($interessado->proximaVisita) {
            $linhas[] = '*Visita agendada:* '.$this->formatarDataHora($interessado->proximaVisita->data_hora);
        }

        return $linhas;
    }

    /**
     * @return list<string>
     */
    private function linhasDoHistorico(Interessado $interessado): array
    {
        return $interessado->historicos
            ->sortByDesc('data_contato')
            ->take(self::MAX_CONTATOS_NO_RESUMO)
            ->map(function (HistoricoContato $contato): string {
                $tipo = $contato->tipoContato?->nome;
                $relato = $this->linhaUnica($contato->relato, self::LIMITE_RELATO);
                $resultado = $contato->resultado ? (HistoricoContato::RESULTADOS[$contato->resultado] ?? null) : null;

                return '• '.$contato->data_contato->format('d/m')
                    .($tipo ? " ({$tipo})" : '')
                    .($relato ? ": {$relato}" : '')
                    .($resultado ? " → {$resultado}" : '');
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $cabecalho
     * @param  list<string>  $historico
     */
    private function compor(array $cabecalho, array $historico, ?string $observacoes): string
    {
        $linhas = [...$cabecalho, '', '*Resumo do que já foi conversado:*'];

        array_push($linhas, ...($historico !== [] ? $historico : ['_Ainda sem contatos registrados._']));

        if ($observacoes) {
            array_push($linhas, '', '*Observações:* '.$observacoes);
        }

        return implode("\n", $linhas);
    }

    /**
     * @param  list<string>  $linhas
     */
    private function comporLote(string $abertura, array $linhas, int $omitidos): string
    {
        $mensagem = $abertura."\n\n".implode("\n", $linhas);

        if ($omitidos > 0) {
            $mensagem .= "\n\n... e mais {$omitidos} lead(s). Consulte o sistema para ver todos.";
        }

        return $mensagem;
    }

    private function resumoDoLead(Interessado $interessado): string
    {
        $telefone = $this->normalizarTelefone($interessado->pessoa?->telefone);

        return implode(' — ', array_filter([
            '*'.($interessado->pessoa?->nome ?? 'Sem nome').'*',
            $telefone ? 'https://wa.me/'.$telefone : 'sem telefone',
            $interessado->status?->nome,
            $interessado->data_proximo_contato ? 'próximo contato '.$interessado->data_proximo_contato->format('d/m H:i') : null,
        ]));
    }

    private function primeiroNome(User $consultor): string
    {
        return Str::before(trim((string) $consultor->name), ' ');
    }

    private function formatarDataHora(\DateTimeInterface $data): string
    {
        return $data->format('d/m/Y \à\s H:i\h');
    }

    /**
     * Texto em uma única linha (WhatsApp quebra a lista se o relato tiver parágrafos), truncado.
     */
    private function linhaUnica(?string $texto, int $limite): ?string
    {
        $texto = trim((string) preg_replace('/\s+/', ' ', (string) $texto));

        return $texto === '' ? null : Str::limit($texto, $limite);
    }
}
