# ADR-007 — Entrada local pela raiz e remoção de arquivos redundantes

- Estado: aceito pela solicitação explícita do responsável.
- Data: 27/10/2026.
- Complementa: ADR-006; substitui a necessidade de `public/router.php`.

## Decisão

Adicionar `index.php` na raiz para iniciar o sistema com
`php -S localhost:8000 index.php`. Essa entrada entrega apenas CSS/JavaScript
previamente permitidos e delega as páginas e a API para `public/index.php`.
O acesso direto a `/index.php` corresponde à página inicial.

Manter `public/index.php` como entrada compartilhada e DocumentRoot recomendado
para produção. Quando `public/` é a raiz do servidor embutido, esse mesmo arquivo
faz o roteamento sem precisar de um script separado. Remover `public/router.php`
e atualizar todos os comandos que dependiam dele.

Incluir `.htaccess` na raiz para instalações Apache que a utilizem como
DocumentRoot: todas as requisições passam pela entrada, inclusive as que coincidem
com arquivos privados. Isso exige reescrita e leitura de `.htaccess` habilitadas.
Não iniciar servidor estático sobre a raiz do projeto.

Remover cache gerado e obsoleto dos testes, preservando fontes, documentação,
testes, configurações locais, banco, fotos e sessões existentes.

## Validação

Executar configuração e domínio, além da suíte HTTP completa nos dois modos:
entrada pela raiz e DocumentRoot `public/`. A suíte verifica abertura por
`/index.php`, assets, fluxos do site e negação de acesso a configuração,
documentação, código e banco. A configuração Apache requer validação na hospedagem.
