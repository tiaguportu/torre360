<x-mail::message>
# Olá, {{ $nomePessoa }}! 👋

Agradecemos o seu interesse em **{{ $unidade->nome }}**. Ficamos muito felizes em saber que você deseja conhecer melhor nosso projeto educacional.

Nossa equipe de admissões irá analisar as informações e entrará em contato em breve.

@if($unidade->instagram || $unidade->facebook || $unidade->youtube)
Enquanto isso, sinta-se à vontade para nos acompanhar em nossas redes sociais:
@if($unidade->instagram)
* [Instagram]({{ $unidade->instagram }})
@endif
@if($unidade->facebook)
* [Facebook]({{ $unidade->facebook }})
@endif
@if($unidade->youtube)
* [YouTube]({{ $unidade->youtube }})
@endif
@endif

Atenciosamente,<br>
**Equipe {{ $unidade->nome }}**
</x-mail::message>