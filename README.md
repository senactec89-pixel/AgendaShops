# AgendaShops

Plataforma web de descoberta e agendamento de serviços de beleza e bem-estar.

## Escopo confirmado

- Marca: **AgendaShops**.
- Nicho: beleza e bem-estar.
- Oferta: somente serviços; não haverá catálogo de produtos ou e-commerce.
- Agendamentos dependem da aceitação do parceiro.
- Cadastro tradicional e autenticação com Google.
- Chat entre cliente e parceiro será concluído antes do lançamento público.

## Estrutura

```text
apps/
  web/          Marketplace, área do cliente, portal do parceiro e administração
  api/          API central e regras de domínio
packages/
  contracts/    Tipos e contratos compartilhados
  ui/           Tokens e futura biblioteca visual
  typescript-config/
docs/           Escopo, requisitos, arquitetura e roadmap
infra/          Serviços locais de infraestrutura
```

## Desenvolvimento local

1. Duplique `.env.example` como `.env` e preencha as credenciais necessárias.
2. Execute `npm install`.
3. Inicie a infraestrutura com `docker compose -f infra/docker-compose.yml up -d`.
4. Inicie a API com `npm run dev:api`.
5. Em outro terminal, inicie o site com `npm run dev:web`.

Aplicação web: `http://localhost:3000`  
API: `http://localhost:3333/api/v1`  
Saúde da API: `http://localhost:3333/api/v1/health`

## Estado atual

Esta entrega contém a fundação estrutural do projeto. Fluxos reais de autenticação,
persistência, catálogo, agendamento e chat ainda não estão implementados.

