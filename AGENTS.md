# AgendaShops — contexto obrigatório para agentes

Este arquivo é a entrada principal para qualquer Codex ou agente que trabalhe
neste repositório. Leia também [docs/README.md](docs/README.md) antes de alterar
produto, domínio, arquitetura ou experiência do usuário.

## Ordem de autoridade

Quando houver conflito, siga esta ordem:

1. Pedido explícito e atual do responsável pelo projeto.
2. Este `AGENTS.md` e arquivos `AGENTS.md` mais específicos da pasta alterada.
3. Documentos canônicos em `docs/`.
4. Documentos originais em `docs/reference/original/`, usados apenas como
   referência histórica.

Os documentos originais ainda usam as marcas STYLE e Find your Style, descrevem
um aplicativo mobile e mostram produtos físicos. Esses pontos foram substituídos
pelas decisões atuais abaixo.

## Produto

- Nome oficial: **AgendaShops**.
- Mercado: beleza e bem-estar.
- Oferta: exclusivamente serviços; não criar produtos, estoque, carrinho, frete
  ou pedidos.
- Experiência principal: encontrar, comparar, solicitar, conversar e agendar.
- Canais iniciais: aplicação web responsiva; aplicativos nativos estão fora do
  primeiro ciclo.
- Idioma inicial: Português do Brasil.
- Perfis: cliente, proprietário/parceiro, funcionário do parceiro e
  administrador.

## Regras de produto que não podem ser alteradas silenciosamente

- Todo agendamento depende da aceitação do parceiro.
- Uma solicitação começa como `PENDING_PARTNER_APPROVAL`, nunca como confirmada.
- A aceitação deve revalidar a disponibilidade no backend e impedir conflito de
  agenda de forma transacional.
- O cliente pode comparar até três opções equivalentes.
- Localização do navegador é opcional; busca manual deve continuar disponível.
- Avaliação somente pode ser criada a partir de atendimento concluído.
- O chat entre cliente e parceiro deve existir antes do lançamento público e ser
  vinculado a uma solicitação ou agendamento no primeiro ciclo.
- Preço, duração, profissional e política aplicados devem ser preservados no
  histórico da confirmação.
- Categorias de beleza e bem-estar são configuráveis por dados, não fixadas no
  código da interface.

## Escopo técnico

- Monorepo com npm workspaces.
- Frontend: Next.js, React e TypeScript em `apps/web`.
- API: Node.js, NestJS e TypeScript em `apps/api`.
- Banco principal: PostgreSQL com PostGIS.
- Redis: cache, limites, filas, notificações e suporte a tempo real quando cada
  necessidade for implementada.
- Contratos compartilhados: `packages/contracts`.
- Design system compartilhado: `packages/ui`.

Não trocar tecnologias estruturais sem registrar a decisão em `docs/decisions/`
e obter confirmação do responsável pelo projeto.

## Convenções gerais

- Identificadores de código, nomes de arquivos técnicos e contratos em inglês.
- Textos da interface e documentação de produto em Português do Brasil.
- Valores monetários persistidos em centavos, nunca em ponto flutuante.
- Datas de eventos persistidas de forma inequívoca; preservar o fuso do
  estabelecimento para exibição e regras operacionais.
- Segredos ficam em variáveis de ambiente e nunca entram no Git.
- Toda entrada externa deve ser validada no backend.
- Autorização deve considerar papel e pertencimento ao estabelecimento.
- Mudança de regra de negócio exige atualização do documento correspondente.
- Não apresentar placeholders como funcionalidade concluída.

## Qualidade mínima

- Escrever testes para autorização, disponibilidade, concorrência, transições de
  estado e promoções quando esses módulos forem implementados.
- Garantir acessibilidade por teclado, foco visível, nomes acessíveis e contraste
  compatível com WCAG 2.2 AA.
- Não duplicar regras de negócio no frontend.
- Manter o endpoint de saúde e respostas de erro sem dados sensíveis.
- Antes de entregar, executar os checks disponíveis e informar qualquer validação
  que não pôde ser realizada.
## Documentação obrigatória por tipo de mudança

- Escopo ou persona: atualizar `docs/01-product-scope.md`.
- Regra funcional: atualizar `docs/02-functional-requirements.md`.
- Limite técnico ou integração: atualizar `docs/03-architecture.md`.
- Ordem de entrega: atualizar `docs/04-roadmap.md`.
- Entidade ou relacionamento: atualizar `docs/05-domain-model.md`.
- Decisão duradoura: criar ou atualizar um ADR em `docs/decisions/`.

