# ADR-004 — Aplicação PHP com renderização no servidor

- Estado: aceito, por solicitação explícita do responsável pelo projeto.
- Data: 23/10/2026.
- Substitui: ADR-003.

## Contexto

O responsável solicitou converter o sistema para PHP de acordo com a documentação.
A versão anterior continha somente a fundação: página inicial, doze páginas
provisórias, contratos de domínio e endpoint de saúde. Os módulos NestJS estavam
vazios; não havia persistência nem fluxos de negócio implementados.

## Decisão

Converter essa fundação para PHP 8.1 ou superior, sem framework obrigatório e sem
dependência de Node.js. A interface usa templates PHP e CSS; a API mantém o prefixo
`/api/v1`. Um front controller atende ambos, com contratos em `src/Contracts` e
módulos em `src/Modules`. Composer fornece metadados, autoload PSR-4 e comandos
opcionais; o bootstrap também permite execução sem Composer.

PostgreSQL/PostGIS e Redis permanecem como infraestrutura planejada. As regras de
produto, autorização, agenda, consentimentos e chat continuam canônicas.

## Consequências

- Não é necessário instalar npm, Next.js, React ou NestJS.
- O servidor deve expor exclusivamente a pasta `public`.
- Templates escapam dados e não concentram regras de domínio.
- A migração mantém o estágio funcional existente; não representa a conclusão
  dos requisitos RF-WEB-001 a RF-WEB-012.
- A implantação da API e da interface passa a usar a mesma aplicação.
- O servidor embutido é destinado ao desenvolvimento; produção exige Apache
  ou Nginx/PHP-FPM, HTTPS e configuração operacional apropriada.
