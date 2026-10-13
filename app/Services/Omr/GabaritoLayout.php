<?php

declare(strict_types=1);

namespace App\Services\Omr;

/**
 * Value Object imutável que define a geometria exata da folha de respostas em milímetros.
 * É a única fonte de verdade para a renderização do PDF e para a detecção no motor OMR.
 */
final class GabaritoLayout
{
    public const LARGURA_A4_MM = 210.0;

    public const ALTURA_A4_MM = 297.0;

    public const RAIO_BOLHA_MM = 2.25; // 4.5 mm de diâmetro

    public const TAMANHO_MARCADOR_MM = 10.0;

    /**
     * @param  int  $totalQuestoes  Número total de questões (1 a 60)
     * @param  int  $alternativas  Quantidade de alternativas por questão (4 ou 5)
     * @param  int  $versao  Versão do layout geométrico
     */
    public function __construct(
        public readonly int $totalQuestoes = 10,
        public readonly int $alternativas = 5,
        public readonly int $versao = 1,
    ) {
        if ($this->totalQuestoes < 1 || $this->totalQuestoes > 60) {
            throw new \InvalidArgumentException('O gabarito suporta entre 1 e 60 questões em folha única.');
        }

        if ($this->alternativas !== 4 && $this->alternativas !== 5) {
            throw new \InvalidArgumentException('O gabarito deve conter 4 ou 5 alternativas por questão.');
        }
    }

    /**
     * Retorna a lista de letras das alternativas ('A', 'B', 'C', 'D' ou 'A', 'B', 'C', 'D', 'E').
     *
     * @return list<string>
     */
    public function letrasAlternativas(): array
    {
        return $this->alternativas === 4
            ? ['A', 'B', 'C', 'D']
            : ['A', 'B', 'C', 'D', 'E'];
    }

    /**
     * Coordenadas (em mm) dos 4 marcadores de canto da página A4.
     * Os marcadores possuem assimetria: o canto superior esquerdo (TL) é do tipo 'vazado',
     * enquanto os demais (TR, BR, BL) são 'solidos'.
     *
     * @return array<string, array{x: float, y: float, w: float, h: float, tipo: string}>
     */
    public function marcadores(): array
    {
        return [
            'TL' => [
                'x' => 12.0,
                'y' => 12.0,
                'w' => self::TAMANHO_MARCADOR_MM,
                'h' => self::TAMANHO_MARCADOR_MM,
                'tipo' => 'vazado', // quadrado 10mm com centro 4mm branco
            ],
            'TR' => [
                'x' => self::LARGURA_A4_MM - 12.0 - self::TAMANHO_MARCADOR_MM,
                'y' => 12.0,
                'w' => self::TAMANHO_MARCADOR_MM,
                'h' => self::TAMANHO_MARCADOR_MM,
                'tipo' => 'solido',
            ],
            'BR' => [
                'x' => self::LARGURA_A4_MM - 12.0 - self::TAMANHO_MARCADOR_MM,
                'y' => self::ALTURA_A4_MM - 12.0 - self::TAMANHO_MARCADOR_MM,
                'w' => self::TAMANHO_MARCADOR_MM,
                'h' => self::TAMANHO_MARCADOR_MM,
                'tipo' => 'solido',
            ],
            'BL' => [
                'x' => 12.0,
                'y' => self::ALTURA_A4_MM - 12.0 - self::TAMANHO_MARCADOR_MM,
                'w' => self::TAMANHO_MARCADOR_MM,
                'h' => self::TAMANHO_MARCADOR_MM,
                'tipo' => 'solido',
            ],
        ];
    }

    /**
     * Área reservada para o QR Code (em mm).
     *
     * @return array{x: float, y: float, w: float, h: float}
     */
    public function areaQrCode(): array
    {
        return [
            'x' => 28.0,
            'y' => 14.0,
            'w' => 26.0,
            'h' => 26.0,
        ];
    }

    /**
     * Área para identificação do aluno e avaliação (em mm).
     * Fica no topo da folha, separada fisicamente da grade de bolhas.
     *
     * @return array{x: float, y: float, w: float, h: float}
     */
    public function areaIdentificacao(): array
    {
        return [
            'x' => 58.0,
            'y' => 14.0,
            'w' => self::LARGURA_A4_MM - 58.0 - 28.0,
            'h' => 26.0,
        ];
    }

    /**
     * Calcula o número de colunas da grade de respostas de acordo com o total de questões.
     */
    public function numeroColunas(): int
    {
        if ($this->totalQuestoes <= 15) {
            return 1;
        }

        if ($this->totalQuestoes <= 30) {
            return 2;
        }

        return 3;
    }

    /**
     * Quantidade máxima de questões por coluna.
     */
    public function questoesPorColuna(): int
    {
        $cols = $this->numeroColunas();

        return (int) ceil($this->totalQuestoes / $cols);
    }

    /**
     * Coordenadas centrais (em mm) de cada bolha da grade.
     * Estrutura: [numero_questao => [letra_alt => ['x' => float, 'y' => float, 'raio' => float]]]
     *
     * @return array<int, array<string, array{x: float, y: float, raio: float}>>
     */
    public function coordenadasBolhas(): array
    {
        $cols = $this->numeroColunas();
        $qPorCol = $this->questoesPorColuna();
        $letras = $this->letrasAlternativas();

        $inicioY = 56.0; // Início vertical da grade abaixo do cabeçalho
        $passoY = 9.0;   // Distância vertical entre questões em mm
        $passoXBolha = 7.5; // Distância entre centros de bolhas na mesma questão

        // Largura útil para distribuir as colunas
        $margemEsquerda = 22.0;
        $larguraUtil = self::LARGURA_A4_MM - (2 * $margemEsquerda);
        $larguraColuna = $larguraUtil / $cols;

        $resultado = [];

        for ($q = 1; $q <= $this->totalQuestoes; $q++) {
            $colIndex = (int) floor(($q - 1) / $qPorCol);
            $linhaIndex = ($q - 1) % $qPorCol;

            $baseXColuna = $margemEsquerda + ($colIndex * $larguraColuna);
            $centroY = $inicioY + ($linhaIndex * $passoY);

            // A coluna tem o rótulo da questão e depois as bolhas alinhadas
            $baseXBolhas = $baseXColuna + 14.0;

            $resultado[$q] = [];
            foreach ($letras as $idx => $letra) {
                $centroX = $baseXBolhas + ($idx * $passoXBolha);
                $resultado[$q][$letra] = [
                    'x' => round($centroX, 2),
                    'y' => round($centroY, 2),
                    'raio' => self::RAIO_BOLHA_MM,
                ];
            }
        }

        return $resultado;
    }

    /**
     * Serializa as propriedades do layout para array/JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'versao' => $this->versao,
            'total_questoes' => $this->totalQuestoes,
            'alternativas' => $this->alternativas,
            'largura_mm' => self::LARGURA_A4_MM,
            'altura_mm' => self::ALTURA_A4_MM,
            'raio_bolha_mm' => self::RAIO_BOLHA_MM,
            'numero_colunas' => $this->numeroColunas(),
            'questoes_por_coluna' => $this->questoesPorColuna(),
            'marcadores' => $this->marcadores(),
            'area_qr_code' => $this->areaQrCode(),
            'area_identificacao' => $this->areaIdentificacao(),
            'coordenadas_bolhas' => $this->coordenadasBolhas(),
        ];
    }

    /**
     * Reconstrói o layout a partir de array serializado.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            totalQuestoes: (int) ($data['total_questoes'] ?? 10),
            alternativas: (int) ($data['alternativas'] ?? 5),
            versao: (int) ($data['versao'] ?? 1),
        );
    }
}
