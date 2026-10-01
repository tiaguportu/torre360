<?php

namespace App\Support;

/**
 * Construtor fluente do HTML exibido no modal de Ajuda (help-content.blade.php).
 *
 * Gera blocos com emoji (seções, itens, passos, dicas e alertas) já estilizados
 * pelo CSS do modal. Todo texto é escapado; os itens condicionais por permissão
 * ficam a cargo de quem chama.
 */
class HelpContent
{
    private string $html = '';

    private function __construct(
        private readonly string $icone,
        private readonly string $titulo,
        private readonly ?string $resumo = null,
    ) {}

    public static function make(string $icone, string $titulo, ?string $resumo = null): self
    {
        return new self($icone, $titulo, $resumo);
    }

    /**
     * Seção com lista de tópicos. Cada item: [emoji, título, descrição] ou
     * [título, descrição]. Itens nulos/falsos são ignorados (facilita `can()`).
     *
     * @param  array<int, array<int, string>|false|null>  $itens
     */
    public function secao(string $titulo, array $itens): self
    {
        $li = '';

        foreach (array_filter($itens) as $item) {
            $emoji = count($item) >= 3 ? $item[0] : '🔹';
            [$nome, $texto] = count($item) >= 3 ? [$item[1], $item[2]] : [$item[0], $item[1] ?? ''];

            $li .= '<li class="help-item"><span class="help-item-emoji">'.e($emoji).'</span>'
                .'<div><strong>'.e($nome).'</strong>'
                .($texto !== '' ? '<p>'.e($texto).'</p>' : '')
                .'</div></li>';
        }

        if ($li !== '') {
            $this->html .= '<h3>'.e($titulo).'</h3><ul class="help-list">'.$li.'</ul>';
        }

        return $this;
    }

    /**
     * Passo a passo numerado.
     *
     * @param  array<int, string>  $passos
     */
    public function passos(string $titulo, array $passos): self
    {
        $li = '';

        foreach (array_values(array_filter($passos)) as $i => $passo) {
            $li .= '<li class="help-step"><span class="help-step-n">'.($i + 1).'</span><span>'.e($passo).'</span></li>';
        }

        if ($li !== '') {
            $this->html .= '<h3>'.e($titulo).'</h3><ol class="help-steps">'.$li.'</ol>';
        }

        return $this;
    }

    public function dica(string $texto, string $emoji = '💡'): self
    {
        $this->html .= '<div class="help-callout help-tip"><span>'.e($emoji).'</span><p>'.e($texto).'</p></div>';

        return $this;
    }

    public function alerta(string $texto, string $emoji = '⚠️'): self
    {
        $this->html .= '<div class="help-callout help-warn"><span>'.e($emoji).'</span><p>'.e($texto).'</p></div>';

        return $this;
    }

    /**
     * Imagem ilustrativa (caminho relativo a public/, ex: images/ajuda/cursos.png).
     */
    public function imagem(string $caminhoPublico, ?string $legenda = null): self
    {
        $this->html .= '<figure class="help-figure"><img src="'.e(asset($caminhoPublico)).'" alt="'.e($legenda ?? $this->titulo).'" loading="lazy">'
            .($legenda ? '<figcaption>'.e($legenda).'</figcaption>' : '')
            .'</figure>';

        return $this;
    }

    /**
     * Parágrafo livre (texto escapado).
     */
    public function texto(string $texto): self
    {
        $this->html .= '<p>'.e($texto).'</p>';

        return $this;
    }

    public function icone(): string
    {
        return $this->icone;
    }

    public function titulo(): string
    {
        return $this->titulo;
    }

    public function resumo(): ?string
    {
        return $this->resumo;
    }

    public function render(): string
    {
        return $this->html;
    }
}
