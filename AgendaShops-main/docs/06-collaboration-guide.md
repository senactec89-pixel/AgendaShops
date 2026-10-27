# Guia de colaboração

## Antes de começar

1. Atualize a branch local a partir de `main`.
2. Leia o `AGENTS.md` da raiz e o arquivo específico da área alterada.
3. Consulte os documentos indicados em `docs/README.md`.
4. Confirme se a tarefa altera escopo ou uma decisão ainda pendente.

## Branches

Use branches curtas e descritivas:

- `feat/customer-sign-up`
- `feat/partner-booking-approval`
- `fix/booking-timezone`
- `docs/cancellation-policy`

Evite desenvolvimento direto em `main` quando houver mais de uma pessoa
trabalhando no projeto.

## Commits

Prefira commits pequenos e com intenção clara:

- `feat: add customer email sign-up`
- `fix: prevent overlapping booking approval`
- `docs: define pending booking expiration`
- `test: cover cross-business authorization`

## Pull requests

Cada pull request deve informar:

- Problema resolvido.
- Escopo incluído e excluído.
- Como testar.
- Alterações de banco ou variáveis de ambiente.
- Documentos atualizados.
- Riscos conhecidos ou decisões pendentes.

## Uso do Codex

Uma solicitação útil deve citar a área e o resultado esperado. Exemplo:

> Leia o AGENTS.md e implemente RF-WEB-010 e RF-WEB-011 na API. Não defina o
> prazo de expiração; mantenha-o configurável. Adicione testes de concorrência.

O Codex deve ser solicitado a verificar os arquivos relevantes, preservar mudanças
de outras pessoas e atualizar a documentação quando uma regra mudar.

## Checklist antes do merge

- A implementação respeita o escopo somente de serviços.
- Estados pendentes não aparecem como confirmados.
- Autorização foi validada no backend.
- Testes do fluxo crítico foram adicionados ou atualizados.
- Nenhum segredo foi incluído.
- Documentação e variáveis de ambiente estão atualizadas.
- Checks disponíveis foram executados e seus resultados registrados.
