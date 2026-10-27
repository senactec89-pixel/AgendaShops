# Organização dos arquivos

## Mapa do projeto

```text
AgendaShops-main/
├── AGENTS.md                       Regras do projeto
├── README.md                       Apresentação e comandos principais
├── index.php                       Entrada local pela raiz
├── .htaccess                       Reescrita protegida quando Apache usa a raiz
├── composer.json                   Requisitos, autoload e comandos opcionais
├── start.ps1                       Inicialização local no Windows
├── .env.example                    Modelo de variáveis de ambiente
├── public/                         Única pasta exposta pelo servidor
│   ├── index.php                   Entrada HTTP do site e da API
│   ├── .htaccess                   Reescrita de URLs no Apache
│   └── assets/
│       ├── css/app.css              Estilos
│       └── js/app.js                Interações do navegador
├── config/
│   ├── bootstrap.php               Inicialização compartilhada por web e console
│   ├── app.php                     Configuração padrão versionada
│   ├── local.example.php           Modelo seguro da configuração local
│   ├── local.php                   Ajustes deste computador; ignorado no Git
│   └── initial-categories.php       Dados iniciais de categorias
├── src/
│   ├── Contracts/                  Contratos e enums
│   ├── Domain/                     Validação e erros de domínio
│   ├── Http/                       Aplicação HTTP, controllers, respostas e formulários
│   ├── Infrastructure/             Configuração, PDO e envio de e-mail
│   ├── Modules/                    Casos de uso e regras de negócio
│   └── Support/helpers.php         Escape HTML e formatação de valores/datas
├── templates/
│   ├── layouts/main.php            Cabeçalho, navegação e rodapé compartilhados
│   ├── partials/service-card.php   Cartão reutilizável de serviço
│   └── pages/
│       ├── public/                 Início, busca, serviço, estabelecimento, comparação e termos
│       ├── auth/account.php        Cadastro, login, recuperação e instalação
│       ├── customer/               Perfil e favoritos
│       ├── shared/                 Agendamentos, mensagens e erros
│       ├── partner/dashboard.php   Portal do parceiro
│       └── admin/                  Painel e inspeção de conversas
├── database/schema.sql             Schema relacional
├── bin/console.php                 Instalação, administrador e expiração
├── storage/                        Banco local, sessões, imagens e e-mails privados
├── tests/
│   ├── configuration/run.php       Precedência e validação da configuração
│   ├── domain/run.php              Regras, persistência e concorrência
│   ├── domain/concurrency-worker.php
│   ├── http/checks.py              Testes completos pelas rotas HTTP
│   └── http/fixture.php             Preparação isolada dos testes HTTP
├── infra/docker-compose.yml        PostgreSQL/PostGIS e Redis opcionais
└── docs/                           Documentação canônica, ADRs e referências originais
```

## Onde alterar cada parte

| Necessidade | Arquivo ou pasta |
| --- | --- |
| Ajustar configuração deste computador | `config/local.php` |
| Ajustar valores padrão sem segredos | `config/app.php` |
| Entender a inicialização do PHP | `config/bootstrap.php` |
| Abrir localmente pela raiz | `index.php` |
| Entender a entrada compartilhada de uma requisição | `public/index.php` |
| Adicionar ou revisar rotas/telas | `src/Http/SiteController.php` |
| Alterar uma regra de negócio | Módulo correspondente em `src/Modules/` |
| Alterar cabeçalho e navegação | `templates/layouts/main.php` |
| Alterar uma tela | Área correspondente em `templates/pages/` |
| Alterar estilos ou interações | `public/assets/css/` e `public/assets/js/` |
| Alterar entidades persistidas | `database/schema.sql` e `docs/05-domain-model.md` |
| Consultar requisitos | `docs/02-functional-requirements.md` |
| Executar localmente | `start.ps1` e [guia local](08-local-setup.md) |

## Caminho de execução

```text
index.php (local) ou public/index.php (DocumentRoot public/)
  → config/bootstrap.php
    → autoload + Configuration + src/Support/helpers.php
  → src/Http/Application.php
    → src/Http/SiteController.php
      → src/Modules + src/Infrastructure
      → templates/pages + templates/layouts/main.php
  → resposta HTTP
```

O `index.php` da raiz delega para `public/index.php`; `local.php` fica em `config/`.
Não copie configuração, documentação, testes ou banco para `public/`.

As URLs de páginas e API foram preservadas. Os assets agora estão em
`/assets/css/app.css` e `/assets/js/app.js`. As antigas pastas vazias `apps/` e
`packages/`, herdadas da base Node.js, foram removidas.

O roteador `public/router.php` foi removido por redundância. A entrada da raiz
atende ao desenvolvimento; `public/index.php` também pode ser usado como roteador
quando a raiz pública for `public/`. Caches antigos dos testes foram removidos.
