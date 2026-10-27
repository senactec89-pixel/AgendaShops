# Base de conhecimento do AgendaShops

Esta pasta é a fonte de contexto compartilhada entre pessoas e agentes. Qualquer
Codex deve começar pelo `AGENTS.md` da raiz e seguir esta ordem de leitura.

## Leitura recomendada

1. [Escopo do produto](01-product-scope.md)
2. [Requisitos funcionais iniciais](02-functional-requirements.md)
3. [Arquitetura](03-architecture.md)
4. [Roadmap](04-roadmap.md)
5. [Modelo de domínio](05-domain-model.md)
6. [Guia de colaboração](06-collaboration-guide.md)
7. [Decisões arquiteturais](decisions/README.md)
8. [Organização dos arquivos e responsabilidades](07-project-structure.md)
9. [Instalação e configuração local](08-local-setup.md)

Para localizar `index.php`, `local.php`, telas ou testes, consulte o mapa de
organização. Para iniciar o site ou ajustar banco/e-mail, consulte o guia local.

## Fonte de verdade

Os documentos numerados acima são canônicos. Os arquivos em
`reference/original/` preservam o levantamento e o protótipo anteriores, mas não
devem prevalecer sobre decisões atuais.

Diferenças conhecidas em relação aos originais:

- A marca atual é AgendaShops.
- O produto é web responsivo, não mobile nativo no primeiro ciclo.
- O nicho é beleza e bem-estar.
- A plataforma comercializa somente serviços.
- Todo agendamento depende de aceite do parceiro.
- Google faz parte dos métodos de autenticação planejados.
- Chat faz parte do escopo anterior ao lançamento público.

## Como manter o alinhamento

- Atualize a documentação no mesmo pull request que alterar uma regra.
- Registre decisões duradouras em um ADR.
- Não copie decisões apenas para conversas, issues ou mensagens privadas.
- Se uma regra ainda estiver indefinida, marque-a explicitamente como pendente.
