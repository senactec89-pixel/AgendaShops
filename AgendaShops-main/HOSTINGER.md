# Publicação na Hostinger

O pacote `dist/agendashops-hostinger.zip` contém duas pastas irmãs:

```text
diretório do domínio/
├── agendashops-private/   # código, banco, configuração e sessões privados
└── public_html/          # index.php, assets, uploads e .htaccess
```

Não coloque `agendashops-private` dentro de `public_html`. O index já encontra automaticamente essa pasta irmã; não precisa editar caminhos PHP. O destino de uploads também acompanha `public_html` automaticamente.

## Instalação

1. No hPanel selecione o domínio e PHP 8.2 ou superior. Habilite pdo_mysql, mbstring, fileinfo e iconv.
2. Faça backup de qualquer site existente. Pelo gerenciador de arquivos, extraia o ZIP no diretório **pai de public_html**, preservando as duas pastas irmãs e os arquivos ocultos `.htaccess`. Não sobrescreva um site existente sem backup. Se sua ferramenta só permitir upload em public_html, envie o conteúdo público ali e mova a pasta privada para o diretório pai pelo gerenciador.
3. Crie um banco e usuário MySQL no hPanel. Anote os nomes completos, incluindo o prefixo da conta, host e senha.
4. No phpMyAdmin, selecione o banco vazio e importe `agendashops-private/database/database.sql`. Não importe sobre uma instalação com dados. Não envie o SQL para public_html.
5. Copie `agendashops-private/.env.example` para `agendashops-private/.env`. Substitua APP_URL pelo domínio real com https e todas as credenciais do banco. Use o host fornecido no painel. Mantenha APP_ENV=production e SESSION_SECURE=1. A configuração local e suas senhas não estão no ZIP.
6. Ative o SSL e o redirecionamento obrigatório para HTTPS no painel da hospedagem. A aplicação não confia em cabeçalhos de proxy para redirecionar; essa configuração pertence à hospedagem. Não abra a instalação por HTTP com cookies seguros ativados.
7. Permissões: pastas 755, arquivos 644; .env 600 ou 640 conforme o usuário PHP. Garanta escrita pelo PHP em agendashops-private/storage e public_html/uploads. As sessões são criadas em storage/sessions. Nunca use 777.
8. Abra https://seu-dominio.com/ e cadastre seu estabelecimento. Adicione profissionais, serviços e horários; faça uma reserva de teste e confira a agenda.
9. Se tiver SSH, no diretório privado execute `php bin/check.php`. Para o administrador da plataforma execute `php bin/create-superadmin.php`, que pede uma senha oculta. Sem terminal, o painel de estabelecimento funciona pelo cadastro; a criação do superadmin continua sendo uma operação privada, sem instalador web.

As páginas `/entrar`, `/cadastrar` e `/agendar/slug` dependem do `.htaccess` fornecido. Remova/mova um eventual index.html de boas-vindas do provedor após fazer backup, caso ele esteja sendo exibido no lugar da aplicação.

## Atualizações e dados existentes

O ZIP contém o esquema inicial, sem dados. Para migrar uma instalação já utilizada, exporte o banco atual e importe esse backup no destino em vez do esquema inicial; copie também as imagens existentes de public/uploads para public_html/uploads. Não copie sessões locais. Ao atualizar arquivos no servidor, preserve o .env, o banco e as imagens. O gerador de pacote exclui intencionalmente credenciais, uploads de usuários, logs, sessões e testes.

## Verificação final

- A página inicial, login, cadastro e CSS abrem no domínio HTTPS.
- Cadastro, login e reserva pública persistem no banco.
- Duas reservas para o mesmo profissional e horário não são aceitas.
- Upload de logo e imagem funciona e aparece na página pública.
- `/agendashops-private/.env`, `/.env` e `/database/database.sql` não expõem arquivos.
- Se houver erro 500, consulte storage/php-error.log (privado) e o log PHP do painel. Não ative display_errors em produção. Erro 404 nas rotas normalmente indica .htaccess ausente ou pasta de extração incorreta.
- Faça backup periódico de banco e uploads e ajuste o aviso de privacidade com os dados do operador antes de abrir ao público.

## Gerar novamente o pacote

Na raiz do repositório: `python bin/package-hostinger.py`. Não exige Python na Hostinger: ele é usado apenas para montar o ZIP localmente.

Esta preparação não publica o site nem valida a conta remota. Domínio, credenciais, importação do banco, SSL e permissões finais dependem da sua hospedagem.

Referências oficiais: [importar banco pelo phpMyAdmin](https://www.hostinger.com/support/1884149-how-to-import-a-database-with-phpmyadmin-in-hostinger/) e [configuração de PHP](https://www.hostinger.com/support/php/).
