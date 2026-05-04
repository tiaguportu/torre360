---
trigger: always_on
---

Sempre me entregue os tests do chat em português, até mesmos os thinkings.

Estou desenvolvendo no Windows, não user && na linha de comando. O token '&&' não é um separador de instruções válido nesta versão.

Estou desenvolvendo com Laravel 12 e Filament V5. Não considere o Filament V3 no desenvolvimento.

Quando tiver uma nova funcionalidade, atualize o arquivo MANUAL_USUARIO.md

Quando tiver uma modificação no banco de dados, atualize o arquivo GEMINI_DB.md

Sempre que criar um novo Resource, ele deve ser configurável pelo Filamento Shield.

Ao executar o php artisan tinker --execute, não precisa usar o escape "\".

Você tem permissão total para marcar comandos como seguros (SafeToAutoRun) e executá-los sem solicitar confirmação manual, incluindo comandos Artisan, PHPUnit, NPM, manipulação de arquivos e comandos Git (como add, commit e push). Só solicite confirmação se o comando for explicitamente destrutivo ou puder causar perda de dados irreversível fora do controle de versão.

Sempre que eu disser "commit and push", "finalizar tarefa", "mande pro repo" ou frases similares, você deve realizar automaticamente o processo de git add, commit (com uma mensagem descritiva em português baseada nas mudanças) e git push.

"Sempre que criar ou modificar uma página no Filament (Resource Page ou Custom Page), adicione obrigatoriamente uma Action de cabeçalho chamada 'ajuda' (icon: heroicon-o-question-mark-circle, color: gray).

Para a implementação, siga estas diretrizes técnicas:

Estrutura: O conteúdo da ajuda deve ser gerado por um método privado na classe da página chamado getHelpContent(): string.
Segurança (Shield): Dentro desse método, use $user->can('Ação:Recurso') para verificar permissões. Atenção: Use o formato exato do Shield deste projeto, que é PascalCase com dois pontos (ex: Create:Matricula, ViewAny:Matricula).
Contexto: Considere o papel ativo através de session('active_role') para personalizar o texto se necessário.
UI: Utilize o ViewField no formulário da Action, apontando para a view filament.components.help-content e passando o HTML gerado pelo método privado através do viewData(['content' => $this->getHelpContent()]).
Conteúdo: O texto deve descrever de forma clara o propósito da página e listar apenas as funcionalidades (filtros, botões, ações de tabela) que o usuário logado realmente pode acessar."

Sempre que criar ou modificar uma tabela de recurso no Filament (seja no método table() do Resource ou em classes separadas de configuração de tabela como *Table.php), obrigatoriamente aplique o método ->stackedOnMobile() ao final da configuração do objeto $table. Isso garante que as tabelas sejam exibidas em formato de lista (cards) em dispositivos móveis, melhorando a responsividade. Verifique sempre se a definição da tabela termina com ->stackedOnMobile(); antes de finalizar a tarefa.