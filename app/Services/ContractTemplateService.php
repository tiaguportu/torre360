<?php

namespace App\Services;

use App\Models\Configuracao;
use App\Models\Contrato;
use App\Models\Pessoa;
use App\Models\TemplateContrato;
use App\Models\TipoVinculo;
use App\Models\Unidade;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

class ContractTemplateService
{
    public function process(Contrato $contrato, string $html): string
    {
        // Carrega relações necessárias caso não estejam presentes
        $contrato->loadMissing([
            'matricula.pessoa.responsaveis',
            'matricula.turma.serie.curso.unidade.representantesLegais',
            'responsaveisFinanceiros.pessoa.enderecos',
            'faturas',
        ]);

        $unidade = $contrato->matricula?->turma?->serie?->curso?->unidade;
        $aluno = $contrato->matricula?->pessoa;

        // Mapa de nomes de vínculos para busca rápida no pivô
        $tiposVinculo = TipoVinculo::all()->pluck('nome', 'id');

        // Pré-processa o template: resolve escapes do editor e compila as macros customizadas {{!! variavel !!}}
        $html = $this->preprocessBlade($html, $contrato, $aluno, $unidade, $tiposVinculo);

        // Gera as variáveis de fallback clássicas para manter compatibilidade retroativa com templates antigos
        $tabelaFaturas = $this->generateFaturasTableFallback($contrato);
        $tabelaAluno = $this->generateAlunoTableFallback($contrato);
        $infoResponsaveis = $this->generateResponsaveisInfo($contrato);
        $assinaturasRepresentantes = $this->generateAssinaturasUnidade($unidade);
        $assinaturasResponsaveis = $this->generateAssinaturasResponsaveis($contrato);
        $assinaturaPai = $this->generateAssinaturaParente($aluno, 'Pai', $tiposVinculo, $contrato);
        $assinaturaMae = $this->generateAssinaturaParente($aluno, 'Mãe', $tiposVinculo, $contrato);
        $assinaturaResponsavelFinanceiro = $this->generateAssinaturaResponsavelFinanceiro($contrato, $aluno, $tiposVinculo);
        $assinaturaResponsavelLegalUnidade = $this->generateAssinaturasUnidade($unidade);

        try {
            $renderedHtml = Blade::render($html, [
                'contrato' => $contrato,
                'unidade' => $unidade,
                'aluno' => $aluno,
                'responsaveis' => $contrato->responsaveisFinanceiros,
                'faturas' => $contrato->faturas,
                // Variáveis do Blade para templates antigos
                'tabelaFaturas' => $tabelaFaturas,
                'tabelaAluno' => $tabelaAluno,
                'infoResponsaveis' => $infoResponsaveis,
                'assinaturasRepresentantes' => $assinaturasRepresentantes,
                'assinaturasResponsaveis' => $assinaturasResponsaveis,
                'assinaturaPai' => $assinaturaPai,
                'assinaturaMae' => $assinaturaMae,
                'assinaturaResponsavelFinanceiro' => $assinaturaResponsavelFinanceiro,
                'assinaturaResponsavelLegalUnidade' => $assinaturaResponsavelLegalUnidade,
            ]);
        } catch (\Throwable $e) {
            if (app()->runningUnitTests()) {
                throw $e;
            }
            logger()->error('Erro ao renderizar template de contrato com Blade: '.$e->getMessage());

            $renderedHtml = $html;
        }

        // Substitui chaves de paginação por elementos HTML para tratamento via CSS no DomPDF
        $renderedHtml = str_replace(
            ['{PAGINA_ATUAL}', '{PAGE_NUM}', '{TOTAL_PAGINAS}', '{PAGE_COUNT}'],
            [
                '<span class="page-number"></span>',
                '<span class="page-number"></span>',
                '<span class="page-count"></span>',
                '<span class="page-count"></span>',
            ],
            $renderedHtml
        );

        // Processa imagens locais no HTML convertendo-as para Base64 para correta exibição no PDF
        return $this->processHtmlImages($renderedHtml);
    }

    protected function preprocessBlade(string $content, Contrato $contrato, ?Pessoa $aluno, ?Unidade $unidade, Collection $tiposVinculo): string
    {
        // Remove macros legadas do tipo {{MACRO}} ou {{MACRO.SUB}}
        $content = preg_replace('/\{\{[A-Z_]+(?:\.[A-Z_]+)*\}\}/', '', $content);

        // Decodifica entidades HTML em todo o template duas vezes (resolve escapes do TinyMCE de forma global)
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');

        // Substitui non-breaking spaces por espaço comum
        $content = str_replace(chr(194).chr(160), ' ', $content);
        $content = str_replace('&nbsp;', ' ', $content);

        // Processa as macros dinâmicas no formato {{!! $variavel !!}} (com cifrão e em camelCase)
        $content = preg_replace_callback('/\{\{!!\s*\$?(\w+)\s*!!\}\}/', function ($matches) use ($contrato, $aluno, $unidade, $tiposVinculo) {
            $variableNameCamel = $matches[1];

            // Converte camelCase para snake_case
            $variableNameSnake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $variableNameCamel));
            $configKey = 'template_contrato_'.$variableNameSnake;

            // Busca a configuração correspondente no banco de dados
            $config = Configuracao::where('campo', $configKey)->first();

            if ($config && ! empty($config->valor)) {
                try {
                    // Renderiza o template da macro customizada usando Blade
                    return Blade::render($config->valor, [
                        'contrato' => $contrato,
                        'matricula' => $contrato->matricula,
                        'aluno' => $aluno,
                        'unidade' => $unidade,
                        'responsaveis' => $contrato->responsaveisFinanceiros,
                        'faturas' => $contrato->faturas,
                    ]);
                } catch (\Throwable $e) {
                    logger()->error("Erro ao renderizar macro customizada {$configKey}: ".$e->getMessage());

                    // Retorna um bloco visual destacado com o erro para que o administrador saiba o que aconteceu
                    $errorMessage = htmlspecialchars($e->getMessage());

                    return "<div style='border: 2px dashed #ef4444; padding: 15px; margin: 15px 0; background-color: #fef2f2; color: #b91c1c; font-family: monospace; font-size: 13px; border-radius: 6px; text-align: left;'>"
                        ."<strong>Erro ao renderizar a macro customizada \${$variableNameCamel} (Configuração: {$configKey}):</strong><br>"
                        .$errorMessage
                        .'</div>';
                }
            }

            // Fallback para as variáveis padrões do sistema
            return $this->getFallbackHtmlForVariable($variableNameSnake, $contrato, $aluno, $unidade, $tiposVinculo);
        }, $content);

        return $content;
    }

    protected function getFallbackHtmlForVariable(string $variableName, Contrato $contrato, ?Pessoa $aluno, ?Unidade $unidade, Collection $tiposVinculo): string
    {
        switch ($variableName) {
            case 'tabela_fatura':
                return $this->generateFaturasTableFallback($contrato);
            case 'tabela_aluno':
                return $this->generateAlunoTableFallback($contrato);
            case 'info_responsaveis':
                return $this->generateResponsaveisInfo($contrato);
            case 'assinaturas_representantes':
                return $this->generateAssinaturasUnidade($unidade);
            case 'assinaturas_responsaveis':
                return $this->generateAssinaturasResponsaveis($contrato);
            case 'assinatura_pai':
                return $this->generateAssinaturaParente($aluno, 'Pai', $tiposVinculo, $contrato);
            case 'assinatura_mae':
                return $this->generateAssinaturaParente($aluno, 'Mãe', $tiposVinculo, $contrato);
            case 'assinatura_responsavel_financeiro':
                return $this->generateAssinaturaResponsavelFinanceiro($contrato, $aluno, $tiposVinculo);
            case 'assinatura_responsavel_legal_unidade':
                return $this->generateAssinaturasUnidade($unidade);
            default:
                return '';
        }
    }

    protected function generateAssinaturaBlock(string $titulo, ?string $extra = null, ?string $cpf = null): string
    {
        $extraFormatado = $extra ? " ({$extra})" : '';
        $cpfValor = $cpf ?: '___________________________';

        return '<div style="margin-top: 50px; margin-bottom: 30px;">'
            .'_______________________________________________<br>'
            .$titulo.$extraFormatado.'<br><br>'
            ."CPF nº {$cpfValor}"
            .'</div>';
    }

    protected function generateAssinaturasUnidade(?Unidade $unidade): string
    {
        if (! $unidade || $unidade->representantesLegais->isEmpty()) {
            return $this->generateAssinaturaBlock('CONTRATADA', 'Escola Torre de Marfim');
        }

        $html = '';
        foreach ($unidade->representantesLegais as $rep) {
            $cargo = $rep->pivot->cargo ?? 'Representante Legal';
            $html .= $this->generateAssinaturaBlock('CONTRATADA', "{$rep->nome} - {$cargo}", $rep->cpf);
        }

        return $html;
    }

    protected function generateAssinaturasResponsaveis(Contrato $contrato): string
    {
        $aluno = $contrato->matricula?->pessoa;
        $tiposVinculo = TipoVinculo::all()->pluck('nome', 'id');

        $paiId = null;
        $maeId = null;
        $html = '';

        if ($aluno) {
            // Assinatura do Pai
            $pai = $aluno->responsaveis->first(function ($resp) use ($tiposVinculo) {
                return $tiposVinculo->get($resp->pivot->tipo_vinculo_id) === 'Pai';
            });
            if ($pai) {
                $paiId = $pai->id;
                $html .= $this->generateAssinaturaParente($aluno, 'Pai', $tiposVinculo, $contrato);
            }

            // Assinatura da Mãe
            $mae = $aluno->responsaveis->first(function ($resp) use ($tiposVinculo) {
                return $tiposVinculo->get($resp->pivot->tipo_vinculo_id) === 'Mãe';
            });
            if ($mae) {
                $maeId = $mae->id;
                $html .= $this->generateAssinaturaParente($aluno, 'Mãe', $tiposVinculo, $contrato);
            }
        }

        // Assinatura de Terceiros que sejam Responsáveis Financeiros
        foreach ($contrato->responsaveisFinanceiros as $rf) {
            if ($rf->pessoa && $rf->pessoa_id !== $paiId && $rf->pessoa_id !== $maeId) {
                $html .= $this->generateAssinaturaBlock(
                    'CONTRATANTE-ADERENTE',
                    $rf->pessoa->nome.' - Responsável Financeiro',
                    $rf->pessoa->cpf
                );
            }
        }

        return $html;
    }

    protected function generateAssinaturaParente(?Pessoa $aluno, string $vinculoNome, Collection $tiposVinculo, Contrato $contrato): string
    {
        if (! $aluno) {
            return $this->generateAssinaturaBlock('CONTRATANTE-ADERENTE', $vinculoNome);
        }

        $parente = $aluno->responsaveis->first(function ($resp) use ($vinculoNome, $tiposVinculo) {
            return $tiposVinculo->get($resp->pivot->tipo_vinculo_id) === $vinculoNome;
        });

        $extra = $vinculoNome;
        if ($parente) {
            $isResponsavelFinanceiro = $contrato->responsaveisFinanceiros->contains('pessoa_id', $parente->id);
            $extra = $parente->nome.' - '.$vinculoNome;
            if ($isResponsavelFinanceiro) {
                $suffix = $vinculoNome === 'Mãe' ? 'e Responsável Financeira' : 'e Responsável Financeiro';
                $extra .= ' '.$suffix;
            }
        }

        return $this->generateAssinaturaBlock(
            'CONTRATANTE-ADERENTE',
            $extra,
            $parente?->cpf
        );
    }

    protected function generateAssinaturaResponsavelFinanceiro(Contrato $contrato, ?Pessoa $aluno, Collection $tiposVinculo): string
    {
        $paiId = null;
        $maeId = null;

        if ($aluno) {
            $pai = $aluno->responsaveis->first(function ($resp) use ($tiposVinculo) {
                return $tiposVinculo->get($resp->pivot->tipo_vinculo_id) === 'Pai';
            });
            $paiId = $pai ? $pai->id : null;

            $mae = $aluno->responsaveis->first(function ($resp) use ($tiposVinculo) {
                return $tiposVinculo->get($resp->pivot->tipo_vinculo_id) === 'Mãe';
            });
            $maeId = $mae ? $mae->id : null;
        }

        $html = '';
        foreach ($contrato->responsaveisFinanceiros as $rf) {
            if ($rf->pessoa && $rf->pessoa_id !== $paiId && $rf->pessoa_id !== $maeId) {
                $html .= $this->generateAssinaturaBlock(
                    'CONTRATANTE-ADERENTE',
                    $rf->pessoa->nome.' - Responsável Financeiro',
                    $rf->pessoa->cpf
                );
            }
        }

        return $html;
    }

    protected function generateAlunoTableFallback(Contrato $contrato): string
    {
        $mat = $contrato->matricula;
        if (! $mat) {
            return 'Nenhum aluno vinculado a este contrato.';
        }

        $aluno = $mat->pessoa;
        $nome = $aluno?->nome ?? '-';
        $nascimento = $aluno?->data_nascimento ? Carbon::parse($aluno->data_nascimento)->format('d/m/Y') : '-';
        $cpf = $aluno?->cpf ?? '-';
        $turma = $mat->turma?->nome ?? '-';
        $serie = $mat->turma?->serie?->nome ?? '-';

        $html = '<table style="width: 100%; border-collapse: collapse; border: 1pt solid black; margin: 10px 0;">';
        $html .= '<tr>';
        $html .= '<td style="border: 1pt solid black; padding: 5px; font-weight: bold; background-color: #f2f2f2; width: 30%;">Nome Completo</td>';
        $html .= '<td style="border: 1pt solid black; padding: 5px;">'.$nome.'</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="border: 1pt solid black; padding: 5px; font-weight: bold; background-color: #f2f2f2;">Data de Nascimento</td>';
        $html .= '<td style="border: 1pt solid black; padding: 5px;">'.$nascimento.'</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="border: 1pt solid black; padding: 5px; font-weight: bold; background-color: #f2f2f2;">CPF</td>';
        $html .= '<td style="border: 1pt solid black; padding: 5px;">'.$cpf.'</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="border: 1pt solid black; padding: 5px; font-weight: bold; background-color: #f2f2f2;">Turma</td>';
        $html .= '<td style="border: 1pt solid black; padding: 5px;">'.$turma.'</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="border: 1pt solid black; padding: 5px; font-weight: bold; background-color: #f2f2f2;">Série/Ano</td>';
        $html .= '<td style="border: 1pt solid black; padding: 5px;">'.$serie.'</td>';
        $html .= '</tr>';
        $html .= '</table>';

        return $html;
    }

    protected function generateResponsaveisInfo(Contrato $contrato): string
    {
        $info = [];
        foreach ($contrato->responsaveisFinanceiros as $rf) {
            $p = $rf->pessoa;
            if (! $p) {
                continue;
            }

            $end = $p->enderecos->first();
            $enderecoStr = $end ? "{$end->logradouro}, {$end->numero} - {$end->bairro}, {$end->cidade?->nome}/{$end->cidade?->estado?->sigla}" : '_______';

            $info[] = "<strong>{$p->nome}</strong>, CPF: {$p->cpf}, residente em {$enderecoStr}.";
        }

        return implode('<br>', $info);
    }

    protected function generateFaturasTableFallback(Contrato $contrato): string
    {
        $faturas = $contrato->faturas->sortBy('vencimento');

        if ($faturas->isEmpty()) {
            return 'Nenhuma fatura encontrada.';
        }

        $html = '<table style="width: 100%; border-collapse: collapse; border: 1pt solid black;">';
        $html .= '<thead><tr style="background-color: #f2f2f2;">';
        $html .= '<th style="border: 1pt solid black; padding: 5px;">Parcela</th>';
        $html .= '<th style="border: 1pt solid black; padding: 5px;">Vencimento</th>';
        $html .= '<th style="border: 1pt solid black; padding: 5px;">Valor Original</th>';
        $html .= '<th style="border: 1pt solid black; padding: 5px;">Valor com Desconto</th>';
        $html .= '</tr></thead><tbody>';

        $i = 1;
        foreach ($faturas as $fatura) {
            $html .= '<tr>';
            $html .= '<td style="border: 1pt solid black; padding: 5px; text-align: center;">'.$i++.'</td>';
            $html .= '<td style="border: 1pt solid black; padding: 5px; text-align: center;">'.Carbon::parse($fatura->vencimento)->format('d/m/Y').'</td>';
            $html .= '<td style="border: 1pt solid black; padding: 5px; text-align: right;">R$ '.number_format($fatura->valor_bruto, 2, ',', '.').'</td>';
            $html .= '<td style="border: 1pt solid black; padding: 5px; text-align: right;">R$ '.number_format($fatura->valor, 2, ',', '.').'</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    public function generatePdfFromOdt(Contrato $contrato, TemplateContrato $template): string
    {
        if (empty($template->arquivo_odt) || ! Storage::disk('local')->exists($template->arquivo_odt)) {
            throw new \Exception("Arquivo ODT do template de contrato #{$template->id} não encontrado.");
        }

        // Carrega relações necessárias caso não estejam presentes
        $contrato->loadMissing([
            'matricula.pessoa.responsaveis',
            'matricula.turma.serie.curso.unidade.representantesLegais',
            'responsaveisFinanceiros.pessoa.enderecos',
            'faturas',
        ]);

        $tempDir = storage_path('app/temp');
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $odtFileName = 'contrato_'.$contrato->id.'_'.uniqid().'.odt';
        $tempOdtPath = $tempDir.DIRECTORY_SEPARATOR.$odtFileName;

        // Copia o ODT original do storage para o local temporário
        $odtStream = Storage::disk('local')->get($template->arquivo_odt);
        file_put_contents($tempOdtPath, $odtStream);

        // Processa os arquivos XML dentro do ZIP do ODT
        $zip = new \ZipArchive;
        if ($zip->open($tempOdtPath) === true) {
            $xmlFiles = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (str_ends_with($filename, '.xml') &&
                    ! in_array($filename, ['settings.xml', 'meta.xml', 'manifest.xml']) &&
                    ! str_contains($filename, 'META-INF/')) {
                    $xmlFiles[] = $filename;
                }
            }

            foreach ($xmlFiles as $filename) {
                $xmlContent = $zip->getFromName($filename);
                if ($xmlContent !== false) {
                    $processedXml = $this->processOdtXml($xmlContent, $contrato);
                    $zip->addFromString($filename, $processedXml);
                }
            }

            $zip->close();
        } else {
            throw new \Exception('Não foi possível abrir o arquivo ODT temporário.');
        }

        // Configura o PHPWord para usar o renderizador DomPDF (que já é dependência do projeto)
        Settings::setPdfRendererName(Settings::PDF_RENDERER_DOMPDF);
        Settings::setPdfRendererPath(base_path('vendor/dompdf/dompdf'));

        $pdfFileName = pathinfo($odtFileName, PATHINFO_FILENAME).'.pdf';
        $tempPdfPath = $tempDir.DIRECTORY_SEPARATOR.$pdfFileName;

        try {
            // Carrega o documento ODT temporário processado
            $phpWord = IOFactory::load($tempOdtPath, 'ODText');

            // Cria o escritor PDF e salva o documento
            $pdfWriter = IOFactory::createWriter($phpWord, 'PDF');
            $pdfWriter->save($tempPdfPath);

            if (! file_exists($tempPdfPath)) {
                throw new \Exception('Arquivo PDF temporário não foi gerado.');
            }

            $pdfContent = file_get_contents($tempPdfPath);
        } catch (\Throwable $e) {
            @unlink($tempOdtPath);
            @unlink($tempPdfPath);
            throw new \Exception('Erro ao converter ODT para PDF via PHPWord: '.$e->getMessage());
        }

        // Limpa arquivos temporários
        @unlink($tempOdtPath);
        @unlink($tempPdfPath);

        return $pdfContent;
    }

    protected function processOdtXml(string $xml, Contrato $contrato): string
    {
        $aluno = $contrato->matricula?->pessoa;
        $unidade = $contrato->matricula?->turma?->serie?->curso?->unidade;

        // 1. Limpa tags XML de dentro das diretivas do Blade e decodifica entidades HTML/XML
        $xml = preg_replace_callback('/@(if|unless|isset|empty|foreach|forelse|while|switch|case|default|break|continue|php|end[a-zA-Z_]+)(?:\s*\((?:(?>[^()]+)|(?R))*\))?/s', function ($matches) {
            $cleaned = preg_replace('/<[^>]*>/', '', $matches[0]);

            return htmlspecialchars_decode($cleaned, ENT_QUOTES);
        }, $xml);

        // Escapa arrobas que não sejam diretivas do Blade para evitar erros de compilação
        $xml = preg_replace('/@(?!if\b|else\b|elseif\b|unless\b|isset\b|empty\b|foreach\b|forelse\b|while\b|switch\b|case\b|default\b|break\b|continue\b|php\b|end[a-zA-Z_]+\b)/', '{{ "@" }}', $xml);

        // Garante que o estilo inline "Negrito" exista nos estilos automáticos do XML
        if (str_contains($xml, '<office:automatic-styles>')) {
            $styleDefinition = '<style:style style:name="Negrito" style:family="text"><style:text-properties fo:font-weight="bold" style:font-weight-asian="bold" style:font-weight-complex="bold"/></style:style>';
            if (! str_contains($xml, 'style:name="Negrito"')) {
                $xml = str_replace('<office:automatic-styles>', '<office:automatic-styles>'.$styleDefinition, $xml);
            }
        }

        // 2. Limpa tags XML de dentro das expressões {{...}} e decodifica entidades HTML/XML
        $xml = preg_replace_callback('/\{\{(.*?)\}\}/s', function ($matches) {
            $cleaned = preg_replace('/<[^>]*>/', '', $matches[1]);
            $decoded = htmlspecialchars_decode($cleaned, ENT_QUOTES);

            return '{{'.$decoded.'}}';
        }, $xml);

        // Processa as macros dinâmicas no formato {{!! algumaCoisa !!}} no XML do ODT
        $tiposVinculo = TipoVinculo::all()->pluck('nome', 'id');
        $xml = $this->preprocessOdtMacros($xml, $contrato, $aluno, $unidade, $tiposVinculo);

        // 3. Limpa tags XML de dentro das expressões ${...} e as converte para a sintaxe Blade padrão
        $xml = preg_replace_callback('/\$\{(.*?)\}/s', function ($matches) {
            $cleaned = preg_replace('/<[^>]*>/', '', $matches[1]);
            $decoded = htmlspecialchars_decode($cleaned, ENT_QUOTES);
            $parts = explode('.', $decoded);
            $expression = '$'.array_shift($parts);
            foreach ($parts as $part) {
                $expression .= '->'.$part;
            }

            return '{{ '.$expression.' }}';
        }, $xml);

        // 4. Processamento da Tabela Dinâmica de Faturas (roda antes do Blade para substituir os placeholders)
        $xml = $this->processOdtFaturasTable($xml, $contrato);

        // Preparar variáveis para o Blade
        $aluno = $contrato->matricula?->pessoa;
        $unidade = $contrato->matricula?->turma?->serie?->curso?->unidade;
        $responsavel = $contrato->responsaveisFinanceiros->first()?->pessoa;

        $bladeData = [
            'contrato' => $contrato,
            'aluno' => $aluno,
            'unidade' => $unidade,
            'responsavel' => $responsavel,
            'responsaveis' => $contrato->responsaveisFinanceiros,
            'faturas' => $contrato->faturas,
        ];

        // 5. Renderizar o XML completo usando Blade
        try {
            $xml = Blade::render($xml, $bladeData);
        } catch (\Throwable $e) {
            if (app()->runningUnitTests()) {
                throw $e;
            }
            logger()->error('Erro ao renderizar content.xml completo com Blade: '.$e->getMessage());
        }

        // 6. Converter quebras de linha em conteúdo de texto para tags de quebra de linha do ODT
        $xml = preg_replace('/([^>])\r?\n([^<])/', '$1<text:line-break/>$2', $xml);

        return $xml;
    }

    protected function processOdtFaturasTable(string $xml, Contrato $contrato): string
    {
        $pattern = '/<table:table-row[^>]*>(?:(?!<\/table:table-row>).)*?\[fatura\.(?:parcela|vencimento|valor|valor_original)\].*?<\/table:table-row>/s';

        if (preg_match($pattern, $xml, $matches)) {
            $rowTemplate = $matches[0];
            $faturas = $contrato->faturas->sortBy('vencimento');

            $newRowsXml = '';
            $i = 1;
            foreach ($faturas as $fatura) {
                $processedRow = $rowTemplate;

                $valorFormatado = 'R$ '.number_format($fatura->valor, 2, ',', '.');
                $valorOriginalFormatado = 'R$ '.number_format($fatura->valor_bruto, 2, ',', '.');
                $vencimentoFormatado = Carbon::parse($fatura->vencimento)->format('d/m/Y');

                $processedRow = str_replace('[fatura.parcela]', $i++, $processedRow);
                $processedRow = str_replace('[fatura.vencimento]', $vencimentoFormatado, $processedRow);
                $processedRow = str_replace('[fatura.valor]', htmlspecialchars($valorFormatado, ENT_QUOTES, 'UTF-8'), $processedRow);
                $processedRow = str_replace('[fatura.valor_original]', htmlspecialchars($valorOriginalFormatado, ENT_QUOTES, 'UTF-8'), $processedRow);

                $newRowsXml .= $processedRow;
            }

            if ($newRowsXml === '') {
                $newRowsXml = str_replace(
                    ['[fatura.parcela]', '[fatura.vencimento]', '[fatura.valor]', '[fatura.valor_original]'],
                    ['-', 'Nenhuma fatura', '-', '-'],
                    $rowTemplate
                );
            }

            $xml = str_replace($rowTemplate, $newRowsXml, $xml);
        }

        return $xml;
    }

    protected function preprocessOdtMacros(string $content, Contrato $contrato, ?Pessoa $aluno, ?Unidade $unidade, Collection $tiposVinculo): string
    {
        $content = preg_replace_callback('/\{\{!!\s*\$?(\w+)\s*!!\}\}/', function ($matches) use ($contrato, $aluno, $unidade, $tiposVinculo) {
            $variableNameCamel = $matches[1];
            $variableNameSnake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $variableNameCamel));
            $configKey = 'template_contrato_'.$variableNameSnake;

            $config = Configuracao::where('campo', $configKey)->first();
            $resolvedHtml = '';

            if ($config && ! empty($config->valor)) {
                try {
                    $resolvedHtml = Blade::render($config->valor, [
                        'contrato' => $contrato,
                        'matricula' => $contrato->matricula,
                        'aluno' => $aluno,
                        'unidade' => $unidade,
                        'responsaveis' => $contrato->responsaveisFinanceiros,
                        'faturas' => $contrato->faturas,
                    ]);
                } catch (\Throwable $e) {
                    logger()->error("Erro ao renderizar macro customizada {$configKey} no ODT: ".$e->getMessage());
                    $resolvedHtml = $e->getMessage();
                }
            } else {
                $resolvedHtml = $this->getFallbackHtmlForVariable($variableNameSnake, $contrato, $aluno, $unidade, $tiposVinculo);
            }

            // Escapa todo o HTML de forma segura antes de converter as tags básicas
            $escapedHtml = htmlspecialchars($resolvedHtml, ENT_QUOTES, 'UTF-8', false);

            // Converter tags básicas de quebra de linha
            $odtContent = preg_replace('/&lt;br\s*\/?&gt;/i', '<text:line-break/>', $escapedHtml);
            $odtContent = preg_replace('/&lt;\/div&gt;/i', '<text:line-break/>', $odtContent);
            $odtContent = preg_replace('/&lt;\/p&gt;/i', '<text:line-break/>', $odtContent);

            // Converter tags de negrito para o estilo "Negrito"
            $odtContent = preg_replace('/&lt;strong&gt;/i', '<text:span text:style-name="Negrito">', $odtContent);
            $odtContent = preg_replace('/&lt;\/strong&gt;/i', '</text:span>', $odtContent);
            $odtContent = preg_replace('/&lt;b&gt;/i', '<text:span text:style-name="Negrito">', $odtContent);
            $odtContent = preg_replace('/&lt;\/b&gt;/i', '</text:span>', $odtContent);

            // Remove quaisquer outras tags HTML escapadas que restarem (como tabelas, links etc.)
            $odtContent = preg_replace('/&lt;[^&]*&gt;/', '', $odtContent);

            return $odtContent;
        }, $content);

        return $content;
    }

    /**
     * Processa todas as imagens locais no HTML e converte os caminhos locais (/storage/...)
     * para Data-URI Base64, garantindo a renderização correta das imagens no DomPDF.
     */
    public function processHtmlImages(string $html): string
    {
        return preg_replace_callback('/<img\s+[^>]*src=["\']([^"\']+)["\'][^>]*>/i', function ($matches) {
            $src = $matches[1];

            $localPath = null;
            if (str_starts_with($src, '/storage/')) {
                $localPath = public_path(substr($src, 1));
            } elseif (str_contains($src, '/storage/')) {
                $parts = explode('/storage/', $src);
                $localPath = public_path('storage/'.end($parts));
            }

            if ($localPath && file_exists($localPath)) {
                try {
                    $mimeType = mime_content_type($localPath) ?: 'image/jpeg';
                    $data = file_get_contents($localPath);
                    $base64 = 'data:'.$mimeType.';base64,'.base64_encode($data);

                    return str_replace($src, $base64, $matches[0]);
                } catch (\Throwable $e) {
                    logger()->error('Erro ao converter imagem local do contrato para base64: '.$e->getMessage());
                }
            }

            return $matches[0];
        }, $html);
    }
}
