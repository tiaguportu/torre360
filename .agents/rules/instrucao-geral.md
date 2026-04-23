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