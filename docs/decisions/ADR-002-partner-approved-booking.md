# ADR-002 — Agendamento exige aceite do parceiro

- Estado: aceito.
- Data: 23/10/2026.

## Contexto

O parceiro precisa confirmar que poderá atender antes que o cliente receba uma
confirmação definitiva.

## Decisão

Toda nova solicitação começa em `PENDING_PARTNER_APPROVAL`. O parceiro poderá
aceitar ou recusar. A aceitação revalida a disponibilidade no servidor e somente
então muda o estado para `CONFIRMED`.

## Consequências

- A interface deve distinguir solicitação de agendamento confirmado.
- Cliente e parceiro recebem notificações das transições.
- O backend deve impedir dois aceites conflitantes.
- Prazo de resposta, expiração e bloqueio provisório do horário permanecem
  configuráveis até decisão de produto específica.
