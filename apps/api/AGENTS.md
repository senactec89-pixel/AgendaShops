# Instruções da API

Estas instruções complementam o `AGENTS.md` da raiz e valem para `apps/api`.

## Plataforma obrigatória

- A API é executada em Node.js.
- Usar TypeScript e NestJS.
- Manter contrato HTTP versionado sob `/api/v1`.
- PostgreSQL é a fonte autoritativa; Redis não armazena dados permanentes.

## Organização

- Controllers traduzem HTTP e não concentram regra de negócio.
- Casos de uso ou services coordenam operações.
- Regras de domínio devem ser independentes de controllers e provedores externos.
- Persistência deve ficar atrás de repositories ou adaptadores claros.
- Um módulo não deve acessar tabelas internas de outro módulo sem contrato
  explícito.
- Integrações externas devem ser substituíveis e configuradas por ambiente.

## Segurança e dados

- Validar payload, parâmetros, limites e formatos na borda da API.
- Aplicar autenticação e autorização no servidor em toda operação protegida.
- Verificar `businessId` ou vínculo equivalente em ações de parceiro.
- Não registrar senhas, tokens, códigos de recuperação ou dados pessoais
  desnecessários.
- Usar hash apropriado para senhas e cookies/sessões revogáveis.
- Operações críticas devem receber idempotency key quando retries puderem gerar
  duplicidade.

## Agenda

- A disponibilidade é calculada no servidor.
- Considerar funcionamento, jornada, duração, intervalos, folgas, bloqueios,
  solicitações que reservem horário e agendamentos existentes.
- Aceitar uma solicitação exige transação e proteção contra sobreposição.
- Toda transição de estado deve validar estado de origem, ator e motivo.
- Manter histórico de transições; não reescrever eventos passados.
- A política e o prazo de bloqueio da solicitação pendente ainda são decisões
  abertas. Não escolher valores definitivos sem atualizar a documentação.

## Mensagens

- No primeiro ciclo, conversa deve estar vinculada a solicitação ou agendamento.
- Eventos importantes de agenda podem gerar mensagens de sistema imutáveis.
- Planejar moderação, denúncia e bloqueio desde o contrato, mesmo que a interface
  seja entregue depois.

## Testes prioritários

- Dois aceites simultâneos para o mesmo horário.
- Autorização entre estabelecimentos diferentes.
- Idempotência de criação e aceite.
- Transições inválidas de estado.
- Cálculo de duração atravessando intervalos ou fim de jornada.
- Expiração, cancelamento e reagendamento.
