# ADR-005 — Persistência e operação do site PHP

- Estado: aceito no escopo da solicitação para tornar o site funcional.
- Data: 23/10/2026.
- Complementa: ADR-004.

## Contexto

A fundação PHP foi convertida, mas ainda não tinha fluxos reais. O responsável
solicitou o funcionamento completo do site. O PHP local possui PDO SQLite;
PostgreSQL permanece a escolha principal para produção.

## Decisão

- Oferecer SQLite para execução local imediata e testes, com PostgreSQL via PDO
  configurável por ambiente para produção. Não substituir PostgreSQL na arquitetura
  de produção nem exigir Redis para os fluxos síncronos implementados.
- Centralizar SQL parametrizado e transações no adaptador `Database`.
- Aceites e alterações de estado bloqueiam o profissional dentro da transação:
  `BEGIN IMMEDIATE` no SQLite; `SELECT ... FOR UPDATE` no PostgreSQL.
- Não reservar o horário apenas por uma solicitação pendente. O estabelecimento
  configura o prazo de resposta; zero mantém a solicitação sem expiração automática.
  Duas solicitações pendentes podem coexistir; só uma pode ser aceita se sobrepostas.
- Reagendamento cria solicitação pendente e libera o compromisso anterior na mesma
  transação. Falhas preservam o compromisso original.
- Categorias iguais determinam equivalência para comparação, limitada a três.
- Funcionários vinculados podem operar agenda, bloqueios e conversas. Dados do
  estabelecimento, catálogo, jornadas e vínculos de equipe exigem proprietário.
- Política do parceiro é informada no cadastro e exibida antes da solicitação.
  Cancelamento não cobra multa automaticamente. Não existe pagamento online.
- Preço, duração, nome do serviço/profissional e política são revalidados e
  preservados no aceite. A interface informa essa revalidação.
- Notificações transacionais são persistidas na conta. Chat funciona por envio e
  atualização de página, com registro de leitura e contador de não lidas.
- E-mail usa arquivo privado no ambiente local e SMTP com STARTTLS em produção.
- Google usa authorization code com state e PKCE, consulta userinfo por HTTPS e
  exige e-mail verificado. Vincular uma conta local existente exige sessão local
  autenticada. Contas novas devem consentir no cadastro.
- Primeira conta administrativa exige token privado gerado no console; não há
  senha padrão nem promoção pública de usuários a administrador.

## Consequências

Os fluxos principais podem ser operados localmente sem serviços pagos ou contas
externas. Google e entrega real de e-mails exigem credenciais do operador. O
comportamento em PostgreSQL, SMTP real e Google real deve ser validado no ambiente
de implantação; os testes locais exercitam SQLite e e-mail em arquivo.

Geolocalização usa coordenadas cadastradas e distância aproximada em linha reta.
Não há geocodificação automática. Imagens ficam em armazenamento privado e são
servidas por rota que aceita somente identificadores gerados e tipos permitidos.

Produção exige HTTPS, PHP atualizado em versão suportada, PostgreSQL, configuração
de e-mail/OAuth, backups de banco e arquivos e revisão dos textos operacionais
pelo responsável antes de abrir o piloto público.
