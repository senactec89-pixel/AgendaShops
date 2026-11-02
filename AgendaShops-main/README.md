# AgendaShops

Plataforma de agendamento em PHP 8.2+, PDO e MySQL, sem Composer, Node ou serviços pagos obrigatórios. O repositório contém a aplicação real; a ilustração da landing page é identificada como tal e não alimenta os painéis.

## Arquitetura e etapas implementadas

1. Banco relacional multiestabelecimento, autenticação por sessão, CSRF e permissões.
2. Serviços de disponibilidade e reservas, com validação transacional e histórico.
3. Gestão de serviços, profissionais, horários, intervalos, exceções e clientes.
4. Página pública com consulta dinâmica, revisão, reserva e calendário pessoal.
5. Dashboard, agenda com filtros de dia/semana/mês e administração da plataforma.
6. Testes de regras e concorrência, documentação de instalação.

`public/index.php` recebe as rotas e aplica autenticação/CSRF. `app/Services/` contém as regras; `app/Views/` contém as telas. `config/bootstrap.php` carrega configuração, PDO e auxiliares. `database/database.sql` é o esquema inicial (não reaplicar sobre uma instalação existente). `tests/` contém verificações em banco descartável.

## Instalação local no XAMPP

1. Ative Apache e MySQL. PHP precisa de `pdo_mysql`, `mbstring`, `fileinfo` e `iconv`.
2. Copie `.env.example` para `.env`. Para instalar automaticamente em um banco vazio, execute `C:\xampp\php\php.exe bin/install.php`. O instalador recusa alterar um banco que já tenha tabelas. Como alternativa manual, crie um banco vazio:
   ```sql
   CREATE DATABASE agendashops CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Importe `database/database.sql` no phpMyAdmin com o banco selecionado. Pela linha de comando: `mysql -u root -p agendashops < database/database.sql` (em um shell que suporte redirecionamento de entrada).
4. Copie `.env.example` para `.env` na raiz e ajuste as credenciais. Não publique nem versione esse arquivo.
5. Defina `APP_URL=http://localhost/AgendaShops-main/public` sem barra final e mantenha `SESSION_SECURE=0` somente para HTTP local.
6. Abra **http://localhost/AgendaShops-main/public/**. Use **Criar minha agenda**, adicione profissionais, adicione serviços vinculando os profissionais e configure o expediente.
7. Configure logo, descrição, antecedência e política de confirmação em Configurações. Compartilhe o link público do dashboard.

Alternativa ao Apache, com `.env` apontando `APP_URL=http://127.0.0.1:8080`:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8080 -t public public/router.php
```

Nenhuma conta ou senha padrão é criada. As contas de estabelecimentos são criadas pelo cadastro público. Profissionais recebem acesso individual quando o proprietário informa e-mail e senha no seu formulário. Cada profissional só consulta e gerencia a própria agenda.

## Primeiro superadministrador

Execute no terminal, nunca pelo navegador:

```powershell
C:\xampp\php\php.exe bin/create-superadmin.php
```

Informe nome, e-mail e uma senha exclusiva com no mínimo 12 caracteres no prompt oculto. Não coloque a senha em argumentos ou histórico do shell. Entre normalmente e acesse `/plataforma`. O superadmin consulta contas, totais, datas de cadastro e pode bloquear/desbloquear estabelecimentos. Não tem acesso de rotina aos clientes das empresas.

## Publicação na Hostinger

Use o pacote `dist/agendashops-hostinger.zip` e siga [HOSTINGER.md](HOSTINGER.md). Ele já separa `public_html` da pasta privada, configura os caminhos automaticamente e inclui um exemplo de configuração de produção, sem credenciais locais.

A aplicação respeita `APP_TIMEZONE` (padrão America/Sao_Paulo). Esta versão usa **um fuso por instalação**. Configure também o fuso da sessão MySQL pelo mesmo horário: o bootstrap aplica o deslocamento atual do PHP por conexão.

## Regras de agenda e segurança

- Todas as referências de serviços, profissionais e clientes usam o estabelecimento autenticado; vínculos cruzados também são impedidos por chaves estrangeiras compostas.
- O cliente público é identificado pelo slug ativo, nunca por um ID de empresa enviado como autorização.
- Reservas e alterações de horários bloqueiam a linha do estabelecimento com `SELECT ... FOR UPDATE`. Após obter o bloqueio, a reserva recalcula a disponibilidade em READ COMMITTED e grava dentro da mesma transação. Isso serializa reservas da empresa, inclusive a opção de qualquer profissional. É uma escolha conservadora para hospedagem compartilhada; não é um benchmark de alto volume.
- Duração e preço são registrados na reserva. `busy_until` inclui o intervalo adicional e não muda quando o serviço é editado posteriormente.
- Expediente individual intersecta o da empresa. Exceções substituem o expediente semanal no respectivo âmbito; múltiplas exceções no mesmo dia se intersectam e um bloqueio integral sempre vence. Pausas continuam aplicadas nas exceções. Alterar expediente não cancela reservas antigas automaticamente.
- Horários de início seguem a grade contada a partir da abertura efetiva. Cancelamentos liberam ou preservam o bloqueio conforme Configurações. Status terminais não podem ser reativados; crie nova reserva.
- Senhas usam hash, sessão é regenerada após login/cadastro, alterações exigem CSRF, consultas usam prepared statements e saídas HTML são escapadas.
- Imagens são limitadas a JPG/PNG/WebP de até 2 MB e 4096×4096, com MIME verificado, nomes aleatórios e execução bloqueada no Apache. Arquivos abandonados por uma transação que falhe podem ser removidos por manutenção.
- Limites de requisições são persistidos em banco e aplicados por IP. Atrás de proxy, configure REMOTE_ADDR com o IP real apenas de proxies confiáveis. Em escala, adicione limites no proxy/WAF.
- A consulta de confirmação é protegida por token aleatório de 256 bits. Quem tiver o link poderá consultar a reserva; não compartilhe publicamente.
- Dados de um cliente já existente não são sobrescritos por um visitante que informa o mesmo telefone.

## WhatsApp e calendário

O botão WhatsApp abre uma mensagem com o modelo configurado; **o envio é manual**. Não existe API externa configurada nem promessa de envio automático. Os serviços de reserva estão separados da interface para permitir adicionar um adaptador de notificações futuro. O calendário baixa um arquivo `.ics` com horários UTC que pode ser importado no calendário do cliente.

## Testes

```powershell
C:\xampp\php\php.exe tests/run.php
```

O teste cria um banco `agendashops_test_<aleatório>`, importa o esquema e remove somente esse banco ao terminar. Exige permissão CREATE/DROP DATABASE do usuário de teste; não use o usuário restrito de produção. Não altera dados do banco da aplicação. Inclui processos PHP concorrentes com conexões distintas para disputar o mesmo horário.

Cobertura: isolamento de empresas e vínculos, sobreposição com durações diferentes, almoço, fechamento, folgas/feriados, agendas independentes, passado/limite futuro, tempo adicional, expediente individual, reagendamento, cancelamento/política, bloqueio de empresa e disputa concorrente. `tests/http-smoke.py` verifica rotas e formulários com um banco temporário e servidor PHP local.

## Limitações e trabalho antes da produção

- Não há recuperação de senha por e-mail, verificação de e-mail, cobrança de assinatura, envio automático de notificações ou sincronização bidirecional de calendários.
- A agenda oferece filtros de dia/semana/mês e cartões; não há arrastar e soltar ou edição por gesto.
- Uma empresa pode ter vários profissionais, mas nesta versão cada conta de proprietário gerencia um estabelecimento. O logo é cadastrado após a criação da conta, em Configurações.
- O catálogo e as listagens não têm paginação; recomenda-se acrescentá-la para grandes volumes.
- O aviso de privacidade é uma base funcional. O operador deve preencher identificação/contato, retenção e procedimentos de atendimento LGPD. Exclusão/anomização e backups são operações administrativas, sem automação nesta versão.
- Configure retenção/limpeza de rate_limits expirados (`DELETE FROM rate_limits WHERE expires_at < NOW()`) e imagens sem referência, monitore logs e faça backups criptografados fora do diretório público.
- Verificado em PHP 8.2.12 e MariaDB 10.4 do XAMPP. O esquema usa recursos compatíveis com MySQL 8; execute a suíte no servidor MySQL final. Não houve publicação externa ou auditoria independente de segurança.

## Verificação realizada nesta entrega

- Sintaxe dos arquivos PHP validada com PHP 8.2.12.
- 30 verificações de regras, banco e concorrência aprovadas em MariaDB 10.4.32.
- 25 verificações HTTP aprovadas usando servidor PHP e banco descartável, incluindo cadastro, papéis, CSRF, reserva pública, confirmação e ICS.
- Apache local respondeu 200 na página inicial, login e CSS; negou acesso direto a `.env` e SQL com 403.
- Não foi executada automação de navegador visual; JavaScript e responsividade precisam de conferência visual nos navegadores de destino.
