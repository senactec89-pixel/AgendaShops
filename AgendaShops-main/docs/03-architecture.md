# Arquitetura inicial

## Visão

```text
Web responsiva
├── Área pública e descoberta
├── Área autenticada do cliente
├── Portal do parceiro
└── Administração
          │
          ▼
API modular
├── Identidade e acesso
├── Estabelecimentos
├── Catálogo e descoberta
├── Agenda e agendamentos
├── Favoritos e avaliações
├── Mensagens e notificações
└── Administração e auditoria
          │
          ├── PostgreSQL/PostGIS
          ├── Redis e processamento assíncrono
          ├── Armazenamento de imagens
          ├── Provedor de mapas
          └── E-mail e futuras notificações
```

## Organização técnica

- `public`: front controller PHP e assets; raiz pública do servidor.
- `index.php` na raiz: abertura local, com entrega restrita dos assets e delegação ao front controller; substitui o roteador separado.
- `templates/layouts`, `templates/partials` e `templates/pages`: interface separada por responsabilidade e área.
- `config/bootstrap.php`: inicialização compartilhada.
- `config/app.php`, `config/local.php` e `.env`: configuração por ambiente.
- `config/initial-categories.php`: categorias iniciais, depois administradas por dados.
- `src/Http`: roteamento e respostas HTTP.
- `src/Contracts`: enums e objetos de transferência tipados em PHP.
- `src/Modules`: identidade, catálogo, agendamentos, relacionamento e operações.
- `database/schema.sql`: persistência relacional.
- `src/Infrastructure`: PDO e e-mail.
- `tests/configuration`, `tests/domain` e `tests/http`: verificações isoladas por tipo.
- `infra`: PostgreSQL/PostGIS, Redis e configuração local.

A aplicação usa PHP 8.1 ou superior. A API preserva `/api/v1` na mesma origem
que a interface. A migração foi autorizada pelo responsável e registrada no
[ADR-004](decisions/ADR-004-php-application.md).

### Execução e estado atual

`config/bootstrap.php` carrega classes PSR-4, configuração e helpers. A prioridade
é ambiente do processo > `.env` > `config/local.php` > `config/app.php`.
Composer é opcional. O site possui cadastro/login, recuperação de
senha, perfil, catálogo, busca, favoritos, comparação, agenda com aceite,
reagendamento, chat, avaliações, promoções, painéis e moderação.

SQLite permite execução local; PostgreSQL é configurado por `DB_DSN`, `DB_USER`
e `DB_PASSWORD` para produção. O schema é instalado explicitamente por
`php bin/console.php install`. Os testes usam banco isolado. Redis não é
necessário aos fluxos síncronos atuais.

Sessões, banco local, imagens e e-mails ficam fora de `public`, em `storage`.
Imagens são validadas e servidas por rota controlada. E-mails locais são arquivos
privados; produção usa SMTP/STARTTLS. Google precisa de client ID, client secret
e callback cadastrado. A saúde informa que a aplicação responde, sem testar
serviços externos. Configuração detalhada no README e ADR-005.

Em produção, configurar DocumentRoot como `public`, HTTPS e reescrita para
`index.php` (Apache com mod_rewrite ou Nginx/PHP-FPM). O servidor embutido PHP
serve apenas para desenvolvimento.

## Estratégia de backend

O MVP será um monólito modular. Cada módulo possuirá limites claros, mas será
implantado como uma única API. Isso reduz custo operacional e preserva a opção de
separar serviços somente quando volume e equipe justificarem.

## Consistência da agenda

A disponibilidade será calculada no servidor a partir de horário do
estabelecimento, jornada do profissional, duração do serviço, intervalos, bloqueios
e agendamentos existentes.

O aceite do parceiro deverá ocorrer em transação e impedir sobreposição. A
persistência deverá guardar o preço, duração, profissional e política aplicados no
momento da confirmação.

## Web e segurança

- Sessões protegidas por cookies `HttpOnly`, `Secure` e política `SameSite`.
- Autorização validada na API por papel e pelo estabelecimento ao qual o usuário
  pertence.
- Proteção contra CSRF, XSS, força bruta e abuso de endpoints.
- Logs sem senhas, tokens ou dados pessoais desnecessários.
- Páginas públicas renderizadas de forma compatível com SEO.
- Interface planejada para WCAG 2.2 AA e navegação por teclado.


A organização física é descrita no [mapa do projeto](07-project-structure.md) e
no [ADR-006](decisions/ADR-006-project-organization.md).
