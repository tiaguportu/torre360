<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Enriquece o HTML "legado" das ajudas (h3, ul/li, p) com emoji, seções coloridas,
 * resumo em destaque e callouts, no mesmo visual do HelpContent.
 *
 * É idempotente: conteúdo que já tem emoji/marcação nova não é alterado.
 */
class HelpEnricher
{
    /** @var array<int, array{0: string, 1: string}> padrão (regex sobre texto ASCII minúsculo) => emoji */
    private const EMOJIS = [
        ['/dica|observa/', '💡'],
        ['/atencao|cuidado|importante|alerta|nao (pode|e possivel)/', '⚠️'],
        ['/permiss|acesso|restrit|seguranca|super admin/', '🔒'],
        ['/^acoes?\b|acoes (individuais|em)/', '⚡'],
        ['/lote|massa|selecao multipla|varios/', '📦'],
        ['/novo|nova|criar|cadastr|adicionar|incluir/', '🆕'],
        ['/editar|alterar|atualizar|ajust|corrig|modificar/', '✏️'],
        ['/excluir|remover|apagar|deletar|descartar/', '🗑️'],
        ['/filtro|filtrar|busca|buscar|pesquis|localizar/', '🔎'],
        ['/importar|upload|enviar arquivo/', '⬆️'],
        ['/exportar|baixar|download|planilha/', '⬇️'],
        ['/imprimir|impressao|pdf/', '🖨️'],
        ['/boletim/', '📄'],
        ['/documento|anexo|arquivo/', '📎'],
        ['/e-?mail|avisar|aviso|notifica|lembrete/', '📧'],
        ['/whatsapp|mensagem|chat|comunica/', '💬'],
        ['/preceptor/', '🤝'],
        ['/nota|avalia|prova|conceito/', '💯'],
        ['/frequencia|chamada|presenca|falta/', '✅'],
        ['/financ|fatura|pagamento|cobranca|boleto|pix|contrato|valor|custo|mensalidade/', '💰'],
        ['/assin/', '✍️'],
        ['/turma|serie|sala|ensalamento/', '🏫'],
        ['/aluno|estudante|matricula|rematricula/', '🎓'],
        ['/professor|docente|coordenador/', '🧑‍🏫'],
        ['/calendario|agenda|horario|data|periodo|cronograma|evento/', '📅'],
        ['/relatorio|grafico|indicador|resumo|painel|dashboard|dre/', '📊'],
        ['/lead|interessado|funil|kanban|campanha|crm|captacao/', '📣'],
        ['/usuario|pessoa|cadastro|perfil|responsavel/', '👤'],
        ['/curso|disciplina|curricul|habilidade|plano/', '📚'],
        ['/status|situacao|etapa|andamento/', '🚦'],
        ['/listagem|lista|visualiz|ver |consult/', '📋'],
        ['/estrutura|configura|parametro|sistema/', '⚙️'],
        ['/o que (voce )?pode|funcionalidade|recursos/', '🎯'],
        ['/passo|como /', '🚀'],
    ];

    private const CORES = 6;

    public static function jaEnriquecido(string $html): bool
    {
        return str_contains($html, 'help-item') || str_contains($html, 'help-sec') || str_contains($html, 'help-callout');
    }

    /**
     * @return array{resumo: ?string, html: string}
     */
    public static function enriquecer(string $html): array
    {
        if (trim($html) === '' || self::jaEnriquecido($html)) {
            return ['resumo' => null, 'html' => $html];
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $anterior = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="help-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        $raiz = $doc->getElementById('help-root');
        if (! $raiz) {
            return ['resumo' => null, 'html' => $html];
        }

        self::desembrulhar($raiz);
        $resumo = self::extrairResumo($raiz);
        self::montarSecoes($doc, $raiz);
        self::converterNotas($doc, $raiz);

        $saida = '';
        foreach ($raiz->childNodes as $filho) {
            $saida .= $doc->saveHTML($filho);
        }

        return ['resumo' => $resumo, 'html' => $saida];
    }

    public static function emojiPara(string $texto, string $padrao = '🔹'): string
    {
        $texto = trim($texto);

        if ($texto === '' || self::comecaComEmoji($texto)) {
            return '';
        }

        $ascii = Str::lower(Str::ascii($texto));

        foreach (self::EMOJIS as [$regex, $emoji]) {
            if (preg_match($regex, $ascii)) {
                return $emoji;
            }
        }

        return $padrao;
    }

    private static function comecaComEmoji(string $texto): bool
    {
        return (bool) preg_match('/^[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}]/u', $texto);
    }

    /** O primeiro parágrafo (antes de qualquer título) vira o resumo do cabeçalho. */
    private static function extrairResumo(DOMElement $raiz): ?string
    {
        foreach ($raiz->childNodes as $no) {
            if (! $no instanceof DOMElement) {
                continue;
            }
            if ($no->tagName === 'p' && ! self::ehNota($no)) {
                $texto = trim(preg_replace('/\s+/', ' ', $no->textContent));
                $raiz->removeChild($no);

                return $texto !== '' ? $texto : null;
            }

            return null;
        }

        return null;
    }

    private static function montarSecoes(DOMDocument $doc, DOMElement $raiz): void
    {
        $secao = null;
        $indice = 0;

        // Sem nenhum título: cria um para agrupar o conteúdo em uma seção colorida
        if ((new DOMXPath($doc))->query('//h2|//h3|//h4')->length === 0 && $raiz->childNodes->length > 0) {
            $titulo = $doc->createElement('h3', '📖 Sobre esta tela');
            $raiz->insertBefore($titulo, $raiz->firstChild);
        }

        foreach (iterator_to_array($raiz->childNodes) as $no) {
            if ($no instanceof DOMElement && in_array($no->tagName, ['h2', 'h3', 'h4'], true)) {
                $secao = $doc->createElement('section');
                $secao->setAttribute('class', 'help-sec help-c'.($indice % self::CORES));
                $indice++;

                $raiz->replaceChild($secao, $no);
                self::prefixarTitulo($doc, $no);
                $secao->appendChild($no);

                continue;
            }

            if ($secao) {
                $raiz->removeChild($no);
                $secao->appendChild($no);
            }
        }

        foreach (iterator_to_array((new DOMXPath($doc))->query('//section[contains(@class,"help-sec")]/ul')) as $lista) {
            self::converterLista($doc, $lista, true);
        }

        foreach (iterator_to_array((new DOMXPath($doc))->query('//section[contains(@class,"help-sec")]/ol')) as $lista) {
            self::converterPassos($doc, $lista);
        }
    }

    /** Remove <div> que envolve todo o conteúdo (ex: `<div class="space-y-3">…</div>`). */
    private static function desembrulhar(DOMElement $raiz): void
    {
        $elementos = array_filter(iterator_to_array($raiz->childNodes), fn ($n) => $n instanceof DOMElement);

        if (count($elementos) === 1 && reset($elementos)->tagName === 'div') {
            $div = reset($elementos);
            while ($div->firstChild) {
                $raiz->insertBefore($div->firstChild, $div);
            }
            $raiz->removeChild($div);
        }
    }

    private static function converterPassos(DOMDocument $doc, DOMElement $lista): void
    {
        $lista->setAttribute('class', 'help-steps');
        $n = 0;

        foreach (iterator_to_array($lista->childNodes) as $li) {
            if (! $li instanceof DOMElement || $li->tagName !== 'li') {
                continue;
            }

            $li->setAttribute('class', 'help-step');
            $corpo = $doc->createElement('div');
            foreach (iterator_to_array($li->childNodes) as $filho) {
                $corpo->appendChild($filho);
            }
            $numero = $doc->createElement('span', (string) ++$n);
            $numero->setAttribute('class', 'help-step-n');
            $li->appendChild($numero);
            $li->appendChild($corpo);
        }
    }

    private static function prefixarTitulo(DOMDocument $doc, DOMElement $h3): void
    {
        $emoji = self::emojiPara($h3->textContent, '📌');
        if ($emoji !== '') {
            $h3->insertBefore($doc->createTextNode($emoji.' '), $h3->firstChild);
        }
    }

    private static function converterLista(DOMDocument $doc, DOMElement $lista, bool $principal): void
    {
        $lista->setAttribute('class', $principal ? 'help-list' : 'help-sublist');

        foreach (iterator_to_array($lista->childNodes) as $li) {
            if (! $li instanceof DOMElement || $li->tagName !== 'li') {
                continue;
            }

            $rotulo = '';
            foreach ($li->childNodes as $filho) {
                if ($filho instanceof DOMElement && $filho->tagName === 'strong') {
                    $rotulo = $filho->textContent;
                    break;
                }
            }
            $emoji = self::emojiPara($rotulo !== '' ? $rotulo : $li->textContent, $principal ? '🔹' : '▫️');

            foreach (iterator_to_array($li->childNodes) as $filho) {
                if ($filho instanceof DOMElement && $filho->tagName === 'ul') {
                    self::converterLista($doc, $filho, false);
                }
            }

            if (! $principal) {
                if ($emoji !== '') {
                    $li->insertBefore($doc->createTextNode($emoji.' '), $li->firstChild);
                }

                continue;
            }

            $li->setAttribute('class', 'help-item');
            $corpo = $doc->createElement('div');
            foreach (iterator_to_array($li->childNodes) as $filho) {
                $corpo->appendChild($filho);
            }
            if ($emoji !== '') {
                $span = $doc->createElement('span', $emoji);
                $span->setAttribute('class', 'help-item-emoji');
                $li->appendChild($span);
            }
            $li->appendChild($corpo);
        }
    }

    private static function ehNota(DOMNode $p): bool
    {
        return $p instanceof DOMElement
            && $p->getElementsByTagName('small')->length > 0
            && preg_match('/^\s*(dica|atenção|atencao|importante|nota|observação|observacao)\b/iu', $p->textContent) === 1;
    }

    /** `<p><small>Dica: …</small></p>` e `<blockquote>` viram callouts. */
    private static function converterNotas(DOMDocument $doc, DOMElement $raiz): void
    {
        $xp = new DOMXPath($doc);

        foreach (iterator_to_array($xp->query('//p[small]|//blockquote')) as $no) {
            if ($no->tagName === 'p' && ! self::ehNota($no)) {
                continue;
            }

            $texto = trim(preg_replace('/\s+/', ' ', $no->textContent));
            $aviso = preg_match('/^(atenção|atencao|importante|cuidado)/iu', $texto) === 1;

            $caixa = $doc->createElement('div');
            $caixa->setAttribute('class', 'help-callout '.($aviso ? 'help-warn' : 'help-tip'));
            $caixa->appendChild($doc->createElement('span', $aviso ? '⚠️' : '💡'));
            $caixa->appendChild($doc->createElement('p', htmlspecialchars($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $no->parentNode->replaceChild($caixa, $no);
        }
    }
}
