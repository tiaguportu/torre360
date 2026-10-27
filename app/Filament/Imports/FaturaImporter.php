<?php

declare(strict_types=1);

namespace App\Filament\Imports;

use App\Enums\StatusFatura;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Support\CsvSanitizer;
use App\Support\FaturaImportParser;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

class FaturaImporter extends Importer
{
    protected static ?string $model = Fatura::class;

    /**
     * @var list<int|string>
     */
    public static array $resetImports = [];

    protected ?string $parsedVencimento = null;

    protected ?string $parsedDescricao = null;

    protected ?float $parsedValor = null;

    protected ?float $parsedDesconto = null;

    protected ?string $parsedTipoDesconto = null;

    protected ?StatusFatura $parsedStatus = null;

    protected bool $shouldSkipSave = false;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('id')
                ->label('Nº')
                ->guess(['Nº', 'nº', 'No', 'no', 'numero', 'número', 'id', 'ID', 'codigo'])
                ->example('1')
                ->rules(['nullable', 'integer'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('aluno')
                ->label('Aluno')
                ->guess(['aluno', 'nome do aluno', 'estudante'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('vencimento')
                ->label('Vencimento')
                ->guess(['vencimento', 'data de vencimento', 'data_vencimento', 'dt_vencimento'])
                ->example('15/03/2026')
                ->requiredMapping()
                ->rules(['required'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('descricao')
                ->label('Descrição')
                ->guess(['descricao', 'descrição', 'historico', 'item'])
                ->example('Mensalidade 01/12')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('valor')
                ->label('Valor')
                ->guess(['valor', 'valor_bruto', 'valor bruto', 'vlr_unitario'])
                ->example('1234.56')
                ->requiredMapping()
                ->rules(['required'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('desconto')
                ->label('Desconto')
                ->guess(['desconto', 'valor desconto', 'desc'])
                ->example('0.00')
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('tipo_desconto')
                ->label('Tipo de desconto')
                ->guess(['tipo de desconto', 'tipo_desconto', 'tipo desc', 'tipo_desc'])
                ->example('absoluto')
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('status')
                ->label('Status')
                ->guess(['status', 'situacao', 'situação'])
                ->example('pendente')
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('valor_a_pagar')
                ->label('Valor a pagar')
                ->guess(['valor a pagar', 'valor_a_pagar', 'valor liquido'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('valor_pago')
                ->label('Valor pago')
                ->guess(['valor pago', 'valor_pago'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('saldo')
                ->label('Saldo')
                ->guess(['saldo', 'valor restante', 'valor_restante'])
                ->fillRecordUsing(fn () => null),
        ];
    }

    public function resolveRecord(): Fatura
    {
        $contratoId = $this->options['contrato_id'] ?? null;
        if (! $contratoId) {
            throw new \InvalidArgumentException('O identificador do contrato (contrato_id) é obrigatório nas opções do importador.');
        }

        $contrato = Contrato::find($contratoId);
        if (! $contrato) {
            throw new RowImportFailedException("Contrato #{$contratoId} não encontrado.");
        }

        // 1. Parsing e validação de Vencimento
        $rawVencimento = $this->data['vencimento'] ?? null;
        $vencimento = FaturaImportParser::parseStrictDate($rawVencimento);
        if (! $vencimento) {
            throw new RowImportFailedException("Data de vencimento inválida: '{$rawVencimento}'. Formato esperado: dd/mm/aaaa ou aaaa-mm-dd (data válida).");
        }

        // 2. Parsing e validação de Descrição
        $rawDescricao = $this->data['descricao'] ?? null;
        if (blank($rawDescricao)) {
            throw new RowImportFailedException('A descrição do item da fatura é obrigatória.');
        }
        $descricao = CsvSanitizer::desanitize(trim((string) $rawDescricao));
        if (mb_strlen($descricao) > 255) {
            throw new RowImportFailedException('A descrição do item da fatura não pode ter mais de 255 caracteres.');
        }

        // 3. Parsing e validação de Valor
        $rawValor = $this->data['valor'] ?? null;
        $valor = FaturaImportParser::parseMoney($rawValor);
        if ($valor === null) {
            throw new RowImportFailedException("Valor inválido: '{$rawValor}'. Informe um valor numérico maior ou igual a zero.");
        }

        // 4. Parsing e validação de Desconto
        $rawDesconto = $this->data['desconto'] ?? null;
        $desconto = blank($rawDesconto) ? 0.0 : FaturaImportParser::parseMoney($rawDesconto);
        if ($desconto === null) {
            throw new RowImportFailedException("Desconto inválido: '{$rawDesconto}'. Informe um valor numérico maior ou igual a zero.");
        }

        // 5. Parsing e validação de Tipo de Desconto
        $rawTipoDesconto = $this->data['tipo_desconto'] ?? null;
        $tipoDesconto = FaturaImportParser::parseTipoDesconto($rawTipoDesconto);
        if ($tipoDesconto === null) {
            throw new RowImportFailedException("Tipo de desconto inválido: '{$rawTipoDesconto}'. Aceitos: 'absoluto', 'R$', 'relativo', '%', 'percentual'.");
        }

        // 6. Parsing e validação de Status
        $rawStatus = $this->data['status'] ?? null;
        $status = FaturaImportParser::parseStatus($rawStatus);

        $this->parsedVencimento = $vencimento;
        $this->parsedDescricao = $descricao;
        $this->parsedValor = $valor;
        $this->parsedDesconto = $desconto;
        $this->parsedTipoDesconto = $tipoDesconto;
        $this->parsedStatus = $status;
        $this->shouldSkipSave = false;

        $id = ! empty($this->data['id']) ? (int) $this->data['id'] : null;

        if ($id) {
            $fatura = Fatura::with(['itens', 'transacoes'])->find($id);
            if (! $fatura) {
                throw new RowImportFailedException("Fatura Nº {$id} não encontrada.");
            }

            if ((int) $fatura->contrato_id !== (int) $contratoId) {
                throw new RowImportFailedException("A fatura Nº {$id} não pertence ao contrato da tela.");
            }

            // Verifica se algum campo mudou
            $vencimentoMudou = $fatura->vencimento?->toDateString() !== $vencimento;
            $statusMudou = ($fatura->status instanceof StatusFatura ? $fatura->status->value : (string) $fatura->status) !== $status->value;

            $item = $fatura->itens->first();
            $descricaoMudou = $item ? ($item->descricao !== $descricao) : true;
            $valorMudou = $item ? (abs((float) $item->valor_unitario - $valor) >= 0.001) : true;
            $descontoMudou = $item ? (abs((float) $item->desconto - $desconto) >= 0.001) : true;
            $tipoDescontoMudou = $item ? ($item->tipo_desconto !== $tipoDesconto) : true;

            $algumCampoMudou = $vencimentoMudou || $statusMudou || $descricaoMudou || $valorMudou || $descontoMudou || $tipoDescontoMudou;

            if (! $algumCampoMudou) {
                $this->shouldSkipSave = true;

                return $fatura;
            }

            // Se mudou algum campo, aplica as salvaguardas rigorosas
            if ($fatura->transacoes->isNotEmpty()) {
                throw new RowImportFailedException("A fatura Nº {$id} possui transações bancárias e não pode ser alterada via importação.");
            }

            if (! empty($fatura->gateway_id)) {
                throw new RowImportFailedException("A fatura Nº {$id} possui integração com gateway de pagamento e não pode ser alterada via importação.");
            }

            if ($fatura->itens->count() !== 1) {
                throw new RowImportFailedException("A fatura Nº {$id} possui {$fatura->itens->count()} itens. Apenas faturas com exatamente 1 item podem ser alteradas via importação.");
            }

            if ((float) $item->quantidade !== 1.0) {
                throw new RowImportFailedException("O item da fatura Nº {$id} possui quantidade diferente de 1 e não pode ser alterado via importação.");
            }

            $fatura->vencimento = $vencimento;
            $fatura->status = $status;

            return $fatura;
        }

        // Criação de nova fatura: salvaguarda de duplicidade
        $duplicada = Fatura::where('contrato_id', $contratoId)
            ->whereDate('vencimento', $vencimento)
            ->whereHas('itens', function ($query) use ($descricao, $valor): void {
                $query->where('descricao', $descricao)
                    ->where('valor_unitario', $valor);
            })
            ->exists();

        if ($duplicada) {
            throw new RowImportFailedException('Fatura idêntica já existe para este contrato com o mesmo vencimento, descrição e valor.');
        }

        $fatura = new Fatura;
        $fatura->contrato_id = $contratoId;
        $fatura->vencimento = $vencimento;
        $fatura->status = $status;

        return $fatura;
    }

    public function saveRecord(): void
    {
        if ($this->shouldSkipSave) {
            return;
        }

        DB::transaction(function (): void {
            $this->record->save();

            $itemData = [
                'descricao' => $this->parsedDescricao,
                'valor_unitario' => $this->parsedValor,
                'quantidade' => 1,
                'desconto' => $this->parsedDesconto,
                'tipo_desconto' => $this->parsedTipoDesconto,
            ];

            if ($this->record->wasRecentlyCreated) {
                $this->record->itens()->create($itemData);
            } else {
                $item = $this->record->itens()->first();
                if ($item) {
                    $item->update($itemData);
                } else {
                    $this->record->itens()->create($itemData);
                }
            }

            /** @var Contrato|null $contrato */
            $contrato = $this->record->contrato ?? Contrato::find($this->options['contrato_id'] ?? null);
            if ($contrato && $contrato->jaEnviadoAssinafy()) {
                $contrato->resetAssinafyState();
                if ($this->import->exists) {
                    Cache::put("import_{$this->import->getKey()}_reset_assinafy", true, now()->addHour());
                    static::$resetImports[] = $this->import->getKey();
                }
            }
        });
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'A importação de faturas foi concluída e '.Number::format($import->successful_rows).' '.str('linha')->plural($import->successful_rows).' foram importadas.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam ao importar.';
        }

        $wasReset = Cache::get("import_{$import->getKey()}_reset_assinafy", false)
            || in_array($import->getKey(), static::$resetImports, true);

        if ($wasReset) {
            $body .= ' Assinatura Resetada: o contrato precisará ser enviado novamente.';
        }

        return $body;
    }
}
