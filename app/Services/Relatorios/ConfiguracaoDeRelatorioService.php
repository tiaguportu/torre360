<?php

declare(strict_types=1);

namespace App\Services\Relatorios;

use App\Contracts\FonteDeRelatorio;
use App\Enums\FormatoRelatorio;
use App\Enums\FrequenciaAgendamento;
use App\Models\RelatorioSalvo;
use App\Models\User;

/**
 * Valida e normaliza o que a pessoa configurou num relatório (Onda 72) antes de gravar: a fonte precisa ser uma que ela pode
 * ver, as colunas e os filtros precisam existir na fonte, o agendamento precisa estar completo e os destinatários precisam
 * poder ver os dados da fonte. Devolve os campos prontos para gravar, com a próxima execução calculada.
 */
class ConfiguracaoDeRelatorioService
{
    public function __construct(
        private readonly CatalogoDeRelatorios $catalogo,
        private readonly RelatorioExecutorService $executor,
    ) {}

    /**
     * @param  array<string, mixed>  $dados  o formulário
     * @return array<string, mixed>
     *
     * @throws \DomainException
     */
    public function normalizar(array $dados, User $por, ?RelatorioSalvo $atual = null): array
    {
        $fonte = $this->catalogo->da((string) ($dados['fonte'] ?? ''));

        if (! $fonte) {
            throw new \DomainException('Escolha a fonte dos dados.');
        }

        // Quem edita um relatório que já existe pode manter a fonte mesmo sem acesso hoje? Não: sem acesso, nada a salvar.
        if (! $this->catalogo->usuarioPode($por, $fonte)) {
            throw new \DomainException("Você não tem permissão para os dados de \"{$fonte->titulo()}\".");
        }

        $colunas = array_map(fn ($c): string => $c->chave, $this->executor->colunasValidas($fonte, array_values((array) ($dados['colunas'] ?? []))));

        if ($colunas === []) {
            throw new \DomainException('Escolha ao menos uma coluna.');
        }

        $ordenarPor = $dados['ordenar_por'] ?? null;
        $ordenarPor = is_string($ordenarPor) && in_array($ordenarPor, $colunas, true) ? $ordenarPor : null;

        $formatoInformado = $dados['formato'] ?? null;
        $formato = $formatoInformado instanceof FormatoRelatorio ? $formatoInformado : (FormatoRelatorio::tryFrom((string) $formatoInformado) ?? FormatoRelatorio::Csv);

        $resultado = [
            'nome' => mb_substr(trim((string) ($dados['nome'] ?? '')), 0, 120),
            'fonte' => $fonte->chave(),
            'colunas' => $colunas,
            'filtros' => $this->executor->filtrosValidos($fonte, (array) ($dados['filtros'] ?? [])),
            'ordenar_por' => $ordenarPor,
            'direcao' => ($dados['direcao'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
            'formato' => $formato,
            'criado_por_user_id' => $atual?->criado_por_user_id ?? $por->id,
        ];

        if ($resultado['nome'] === '') {
            throw new \DomainException('Dê um nome ao relatório.');
        }

        return $resultado + $this->agendamento($dados, $fonte, $atual);
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function agendamento(array $dados, FonteDeRelatorio $fonte, ?RelatorioSalvo $atual): array
    {
        if (! ($dados['agendado'] ?? false)) {
            return ['agendado' => false, 'frequencia' => null, 'dia' => null, 'hora' => null, 'destinatarios' => null, 'proxima_execucao_em' => null];
        }

        $frequenciaInformada = $dados['frequencia'] ?? null;
        $frequencia = $frequenciaInformada instanceof FrequenciaAgendamento ? $frequenciaInformada : FrequenciaAgendamento::tryFrom((string) $frequenciaInformada);

        if (! $frequencia) {
            throw new \DomainException('Escolha a frequência do agendamento.');
        }

        $hora = trim((string) ($dados['hora'] ?? ''));
        $hora = strlen($hora) >= 5 ? substr($hora, 0, 5) : $hora;

        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora) !== 1) {
            throw new \DomainException('Informe a hora do agendamento (por exemplo, 07:30).');
        }

        $dia = $frequencia->usaDia() ? (int) ($dados['dia'] ?? 0) : null;

        if ($frequencia === FrequenciaAgendamento::Semanal && ($dia < 1 || $dia > 7)) {
            throw new \DomainException('Escolha o dia da semana.');
        }

        if ($frequencia === FrequenciaAgendamento::Mensal && ($dia < 1 || $dia > 28)) {
            throw new \DomainException('Escolha o dia do mês (de 1 a 28).');
        }

        $ids = array_values(array_unique(array_map('intval', array_filter((array) ($dados['destinatarios'] ?? [])))));

        if ($ids === []) {
            throw new \DomainException('Escolha quem recebe o aviso quando o relatório ficar pronto.');
        }

        $semAcesso = User::query()->whereIn('id', $ids)->get()->reject(fn (User $u): bool => $this->catalogo->usuarioPode($u, $fonte));

        if ($semAcesso->isNotEmpty() || User::query()->whereIn('id', $ids)->count() !== count($ids)) {
            $nomes = $semAcesso->pluck('name')->implode(', ');

            throw new \DomainException('Só recebe o aviso quem pode ver os dados desta fonte e está ativo'.($nomes !== '' ? " (confira: {$nomes})" : '').'.');
        }

        $simulado = new RelatorioSalvo(['frequencia' => $frequencia, 'dia' => $dia, 'hora' => $hora]);
        $mudouAgenda = ! $atual
            || ! $atual->agendado
            || $atual->frequencia !== $frequencia
            || (int) $atual->dia !== (int) $dia
            || $atual->hora !== $hora;

        return [
            'agendado' => true,
            'frequencia' => $frequencia,
            'dia' => $dia,
            'hora' => $hora,
            'destinatarios' => $ids,
            // Só recalcula quando a agenda mudou: salvar de novo para ajustar o nome não pode adiar nem adiantar a próxima rodada.
            'proxima_execucao_em' => $mudouAgenda ? $simulado->calcularProximaExecucao(now()) : $atual->proxima_execucao_em,
        ];
    }
}
