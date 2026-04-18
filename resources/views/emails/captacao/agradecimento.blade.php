<x-mail::message>
# Olá, {{ $nomePessoa }}! 👋

Agradecemos o seu interesse em **{{ $nomeUnidade }}**. Ficamos muito felizes em saber que você deseja conhecer melhor nosso projeto educacional.

Nossa equipe de admissões irá analisar as informações e entrará em contato em breve para tirar suas dúvidas e, se desejar, agendar uma visita.

@if($redesSociais)
Enquanto isso, sinta-se à vontade para nos acompanhar em nossa rede social: [{{ $redesSociais }}]({{ $redesSociais }})
@endif

Atenciosamente,<br>
**Equipe {{ $nomeUnidade }}**
</x-mail::message>
