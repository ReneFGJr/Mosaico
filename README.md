# Mosaico
Repositório de Ativos Digitais

Aplicação CodeIgniter 4 para organização de peças digitais do programa Mais Saúde com Agente.

## Criar ambiente MySQL
### Criar Database
CREATE DATABASE mosaico
   CHARACTER SET utf8mb4
   COLLATE utf8mb4_unicode_ci;

### Criar Usuário
CREATE USER 'mosaico'@'localhost'
   IDENTIFIED BY 'SUA_SENHA_FORTE_AQUI';

### Privilégios para o usuario
GRANT ALL PRIVILEGES ON mosaico.*
   TO 'mosaico'@'localhost';

FLUSH PRIVILEGES;

## Desenvolvimento

1. Instale as dependências com `composer install`.
2. Copie `.env.example` para `.env`, configure o banco de desenvolvimento e defina `MOSAICO_BOOTSTRAP_PASSWORD` com uma senha de pelo menos 12 caracteres.
3. Execute `php spark migrate`.
4. Crie o usuário padrão:

`php spark db:seed AdminUserSeeder`

   Acesse `/login` com:

   - **Usuário:** `admin@mosaico.local`
   - **Senha:** o valor configurado em `MOSAICO_BOOTSTRAP_PASSWORD` no `.env`

   A seed cria apenas a conta de usuário e não altera uma conta existente. Para criar também a comunidade inicial e conceder a ela o papel de administrador, use o comando de bootstrap em vez da seed:

`php spark mosaico:bootstrap COMUNIDADE "Comunidade inicial" admin@example.org "Gestor inicial"`

No PowerShell, se preferir fornecer a senha pela variável de ambiente do processo, defina `$env:MOSAICO_BOOTSTRAP_PASSWORD = '...'` antes do comando e remova-a depois com `Remove-Item Env:MOSAICO_BOOTSTRAP_PASSWORD`. Não use uma senha real em histórico de comandos ou documentação.

Os testes usam SQLite em memória: `vendor/bin/phpunit`. A autenticação local é um adaptador inicial; OIDC/SAML, MFA, recuperação de senha e políticas institucionais ainda precisam ser definidos.
