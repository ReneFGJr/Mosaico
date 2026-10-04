# Mosaico
Repositório de Ativos Digitais

Aplicação CodeIgniter 4 para organização de peças digitais do programa Mais Saúde com Agente.

## Desenvolvimento

1. Instale as dependências com `composer install`.
2. Copie `.env.example` para `.env` e configure o banco de desenvolvimento.
3. Execute `php spark migrate`.
4. Defina `MOSAICO_BOOTSTRAP_PASSWORD` no ambiente do processo com uma senha de pelo menos 12 caracteres e execute:

	`php spark mosaico:bootstrap PROJETO "Projeto inicial" admin@example.org "Gestor inicial"`

5. Inicie com `php spark serve` e acesse `/login`.

No PowerShell, defina a senha com `$env:MOSAICO_BOOTSTRAP_PASSWORD = '...'` antes do comando e remova-a da sessão após o uso com `Remove-Item Env:MOSAICO_BOOTSTRAP_PASSWORD`. Não use uma senha real em histórico de comandos ou documentação.

Os testes usam SQLite em memória: `vendor/bin/phpunit`. A autenticação local é um adaptador inicial; OIDC/SAML, MFA, recuperação de senha e políticas institucionais ainda precisam ser definidos.
