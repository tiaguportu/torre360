<?php

namespace App\Services;

use App\Models\BolsaConcedida;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\ItemFatura;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gera as faturas (entrada + parcelas) de um contrato. Extraído da ação "Gerar Faturas
 * Automaticamente" de `EditContrato` (que continua chamando este serviço) para ser
 * reutilizado pela efetivação da Rematrícula Online, que precisa gerar a cobrança sem
 * depender de um clique manual da secretaria.
 */
class GeracaoFaturasContratoService
{
    /** Teto de faturas de um plano descrito em texto livre; protege contra uma resposta da IA fora de controle. */
    public const MAX_PARCELAS_PLANO = 60;

    /**
     * Gera a fatura de entrada (se houver) e as parcelas do contrato. Remove faturas
     * existentes do contrato antes de gerar — mesmo comportamento já usado pela ação
     * manual (regenerar substitui, não soma).
     *
     * A 1ª parcela vence 5 dias úteis após a data-base; as demais, uma por mês a partir
     * daí. A fatura de entrada (quando houver) vence na própria data-base.
     *
     * A data-base é `$dataBase` quando informada; senão, `$contrato->data_aceite`; senão,
     * hoje. O aceite só existe depois da assinatura, e as faturas costumam ser geradas
     * antes dela (elas aparecem no próprio contrato), então a falta de `data_aceite` não
     * pode impedir a geração. A Rematrícula Online informa a data da confirmação pela
     * família pelo mesmo motivo.
     *
     * @return Collection<int, Fatura>
     */
    public function gerar(Contrato $contrato, int $quantidadeParcelas, float $valorEntrada, ?Carbon $dataBase = null): Collection
    {
        $dataBase ??= $contrato->data_aceite ? Carbon::parse($contrato->data_aceite) : Carbon::today();

        $entradaCentavos = (int) round($valorEntrada * 100);
        $restanteCentavos = (int) round((float) $contrato->valor_total * 100) - $entradaCentavos;

        if ($restanteCentavos < 0) {
            throw new \InvalidArgumentException('O valor de entrada não pode ser maior que o valor total do contrato.');
        }

        // Em centavos inteiros: dividir em reais com float arredondava para cima ou para baixo (100 ÷ 6 = 16,67 × 6 = 100,02)
        // e a soma das faturas deixava de bater com o contrato. Sobrando centavos, a 1ª parcela fica com eles.
        $valoresParcelas = $quantidadeParcelas > 0 ? $this->dividirEmCentavos($restanteCentavos, $quantidadeParcelas) : [];

        $dataAceite = $dataBase->copy();
        $primeiroVencimento = $this->adicionarDiasUteis($dataAceite, 5);

        $plano = [];

        if ($entradaCentavos > 0) {
            $plano[] = ['descricao' => 'Entrada', 'vencimento' => $dataAceite->toDateString(), 'valor' => $entradaCentavos / 100.0];
        }

        for ($i = 0; $i < $quantidadeParcelas; $i++) {
            $plano[] = [
                'descricao' => 'Parcela '.($i + 1).' de '.$quantidadeParcelas,
                'vencimento' => $primeiroVencimento->copy()->addMonths($i)->toDateString(),
                'valor' => $valoresParcelas[$i] / 100.0,
            ];
        }

        return $this->gerarPlano($contrato, $plano);
    }

    /**
     * Divide um total em centavos em partes iguais. Se a divisão não for exata, a primeira parte recebe todos os
     * centavos que sobram (100,00 ÷ 3 = 33,34 + 33,33 + 33,33), de modo que a soma sempre fecha com o total.
     *
     * @return list<int> centavos de cada parte, na ordem das parcelas
     */
    public function dividirEmCentavos(int $totalCentavos, int $partes): array
    {
        if ($partes < 1) {
            throw new \InvalidArgumentException('A divisão precisa de ao menos uma parte.');
        }

        $base = intdiv($totalCentavos, $partes);
        $valores = array_fill(0, $partes, $base);
        $valores[0] += $totalCentavos - $base * $partes;

        return $valores;
    }

    /**
     * Grava um plano de faturas já resolvido (ver `resolverPlano()`), substituindo as faturas do contrato. É o
     * ponto único de gravação: o parcelamento por entrada + parcelas e o descrito em texto livre (IA) passam
     * por aqui, então a proteção de faturas pagas e o desconto de bolsa valem para os dois.
     *
     * @param  list<array{descricao: string, vencimento: string, valor: float}>  $plano
     * @return Collection<int, Fatura>
     */
    public function gerarPlano(Contrato $contrato, array $plano): Collection
    {
        $faturasProtegidas = $contrato->faturas()->protegida()->count();
        if ($faturasProtegidas > 0) {
            throw new \InvalidArgumentException(
                "O contrato possui {$faturasProtegidas} fatura(s) com pagamento registrado ou cobrança emitida e não pode ser regenerado. Ajuste essas faturas manualmente."
            );
        }

        $percentualBolsa = $this->percentualBolsaAtiva($contrato);

        return DB::transaction(function () use ($contrato, $plano, $percentualBolsa): Collection {
            $contrato->faturas()->each(function (Fatura $fatura): void {
                $fatura->itens()->delete();
                $fatura->delete();
            });

            $faturas = collect();

            foreach ($plano as $parcela) {
                $fatura = Fatura::create([
                    'contrato_id' => $contrato->id,
                    'vencimento' => $parcela['vencimento'],
                    'status' => 'pendente',
                ]);

                ItemFatura::create([
                    'fatura_id' => $fatura->id,
                    'descricao' => $parcela['descricao'],
                    'valor_unitario' => $parcela['valor'],
                    'quantidade' => 1,
                    'desconto' => $percentualBolsa,
                    'tipo_desconto' => $percentualBolsa > 0 ? 'percentual' : 'absoluto',
                ]);

                $faturas->push($fatura);
            }

            return $faturas;
        });
    }

    /**
     * Transforma as parcelas descritas pela IA (ou por qualquer outra origem) em um plano fechado: cada parcela com
     * descrição, vencimento e valor em reais. A conta é feita aqui, em centavos, e nunca pelo modelo.
     *
     * Cada parcela traz `vencimento` (AAAA-MM-DD) e, opcionalmente, `valor` (reais) ou `percentual` (do valor total
     * do contrato). As parcelas sem nenhum dos dois dividem igualmente o que sobra do total; o resto de centavos
     * fica na primeira delas (a de vencimento mais cedo), como em `gerar()`. Sem parcelas "livres", a soma tem de
     * fechar com o valor do contrato.
     *
     * @param  list<array<string, mixed>>  $parcelas
     * @return list<array{descricao: string, vencimento: string, valor: float}>
     *
     * @throws \InvalidArgumentException com mensagem própria para exibir ao operador
     */
    public function resolverPlano(Contrato $contrato, array $parcelas, ?Carbon $referencia = null): array
    {
        $totalCentavos = (int) round((float) $contrato->valor_total * 100);

        if ($totalCentavos <= 0) {
            throw new \InvalidArgumentException('O contrato não tem valor total para ser dividido em faturas.');
        }

        $quantidade = count($parcelas);

        if ($quantidade < 1 || $quantidade > self::MAX_PARCELAS_PLANO) {
            throw new \InvalidArgumentException('O parcelamento precisa ter entre 1 e '.self::MAX_PARCELAS_PLANO.' faturas.');
        }

        $referencia ??= Carbon::today();
        $maisCedo = $referencia->copy()->subYears(2)->startOfDay();
        $maisTarde = $referencia->copy()->addYears(6)->endOfDay();

        $linhas = [];
        $somaDefinida = 0;

        foreach (array_values($parcelas) as $indice => $parcela) {
            $vencimento = $this->normalizarVencimento($parcela['vencimento'] ?? null);

            if ($vencimento === null) {
                throw new \InvalidArgumentException('A parcela '.($indice + 1).' não tem um vencimento válido.');
            }

            if ($vencimento->lt($maisCedo) || $vencimento->gt($maisTarde)) {
                throw new \InvalidArgumentException('O vencimento '.$vencimento->format('d/m/Y').' (parcela '.($indice + 1).') está longe demais da data de hoje para ser plausível. Confira o ano na instrução.');
            }

            $descricao = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($parcela['descricao'] ?? ''))));
            $descricao = $descricao !== '' ? mb_substr($descricao, 0, 120) : 'Parcela '.($indice + 1).' de '.$quantidade;

            $centavos = null;

            if (isset($parcela['valor']) && is_numeric($parcela['valor'])) {
                $centavos = (int) round((float) $parcela['valor'] * 100);
            } elseif (isset($parcela['percentual']) && is_numeric($parcela['percentual'])) {
                $percentual = (float) $parcela['percentual'];

                if ($percentual <= 0 || $percentual > 100) {
                    throw new \InvalidArgumentException('O percentual da parcela '.($indice + 1).' precisa estar entre 0 e 100.');
                }

                $centavos = (int) round($totalCentavos * $percentual / 100);
            }

            if ($centavos !== null) {
                if ($centavos <= 0) {
                    throw new \InvalidArgumentException('O valor da parcela '.($indice + 1).' precisa ser maior que zero.');
                }

                $somaDefinida += $centavos;
            }

            $linhas[] = ['descricao' => $descricao, 'vencimento' => $vencimento, 'centavos' => $centavos, 'ordem' => $indice];
        }

        // Ordenar antes de distribuir: "primeira parcela" é a de vencimento mais cedo, não a que a IA listou primeiro.
        usort($linhas, fn (array $a, array $b): int => [$a['vencimento']->timestamp, $a['ordem']] <=> [$b['vencimento']->timestamp, $b['ordem']]);

        $livres = array_keys(array_filter($linhas, fn (array $linha): bool => $linha['centavos'] === null));

        if ($livres !== []) {
            $saldo = $totalCentavos - $somaDefinida;

            if ($saldo < count($livres)) {
                throw new \InvalidArgumentException('As parcelas com valor definido já consomem todo o valor do contrato; não sobra saldo para dividir entre as demais.');
            }

            foreach ($this->dividirEmCentavos($saldo, count($livres)) as $posicao => $centavos) {
                $linhas[$livres[$posicao]]['centavos'] = $centavos;
            }
        } else {
            // Percentuais redondos (33,33% × 3) deixam alguns centavos de fora: até 1 centavo por parcela vai para a primeira.
            $diferenca = $totalCentavos - $somaDefinida;

            if (abs($diferenca) > $quantidade) {
                throw new \InvalidArgumentException(sprintf(
                    'A soma das parcelas (R$ %s) não fecha com o valor do contrato (R$ %s). Descreva também a parcela que deve receber o restante, ou os valores que somam o total.',
                    number_format($somaDefinida / 100, 2, ',', '.'),
                    number_format($totalCentavos / 100, 2, ',', '.'),
                ));
            }

            $linhas[0]['centavos'] += $diferenca;

            if ($linhas[0]['centavos'] <= 0) {
                throw new \InvalidArgumentException('Os valores informados não deixam saldo para a primeira parcela.');
            }
        }

        return array_map(fn (array $linha): array => [
            'descricao' => $linha['descricao'],
            'vencimento' => $linha['vencimento']->toDateString(),
            'valor' => $linha['centavos'] / 100.0,
        ], $linhas);
    }

    /** Aceita só AAAA-MM-DD de uma data que existe no calendário (2027-02-30 não vira 02/03 em silêncio). */
    private function normalizarVencimento(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($valor), $partes)) {
            return null;
        }

        if (! checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1])) {
            return null;
        }

        return Carbon::create((int) $partes[1], (int) $partes[2], (int) $partes[3])->startOfDay();
    }

    /**
     * Percentual de bolsa vigente hoje para a matrícula do contrato (0 se não houver
     * matrícula vinculada ou nenhuma bolsa aprovada ativa). Aplicado automaticamente em
     * cada item de fatura gerado, para o financeiro não precisar lançar o desconto à mão.
     */
    public function percentualBolsaAtiva(Contrato $contrato): int
    {
        $matricula = $contrato->matricula;

        if (! $matricula) {
            return 0;
        }

        return BolsaConcedida::percentualAtivoPara($matricula);
    }

    /**
     * Avança N dias úteis a partir de uma data, pulando sábados e domingos.
     */
    public function adicionarDiasUteis(Carbon $data, int $dias): Carbon
    {
        $resultado = $data->copy();
        $adicionados = 0;

        while ($adicionados < $dias) {
            $resultado->addDay();

            if (! $resultado->isWeekend()) {
                $adicionados++;
            }
        }

        return $resultado;
    }
}
