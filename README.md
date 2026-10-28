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

`php spark mosaico:bootstrap COMUNIDADE "Comunidade inicial" admin@example.org "Administrador"`

No PowerShell, se preferir fornecer a senha pela variável de ambiente do processo, defina `$env:MOSAICO_BOOTSTRAP_PASSWORD = '...'` antes do comando e remova-a depois com `Remove-Item Env:MOSAICO_BOOTSTRAP_PASSWORD`. Não use uma senha real em histórico de comandos ou documentação.

Os testes usam SQLite em memória: `vendor/bin/phpunit`. A autenticação local é um adaptador inicial; OIDC/SAML, MFA, recuperação de senha e políticas institucionais ainda precisam ser definidos.

## Ações das coleções

O menu lateral apresenta um sumário de palavras-chave agrupadas por categoria e com a quantidade de peças por termo. Os checkboxes combinam os termos com a regra “todas as palavras selecionadas”. Os filtros preservam a busca e o modo de visualização e ficam lembrados na sessão por coleção ou comunidade. “Limpar filtros” remove a seleção. Links antigos com `term` continuam válidos; a seleção múltipla usa `terms[]`, validada conforme a comunidade.

Nas comunidades, os cards de coleção oferecem **Ações → Editar / Trocar imagem / Deletar** para usuários com `projects.manage`. Deletar exige confirmação e usa exclusão lógica (`collections.deleted_at`), preservando peças, vínculos, arquivos e auditoria. Coleções ocultas deixam de aparecer na navegação e não podem ser abertas, selecionadas ou modificadas pelas rotas de coleção. Execute `php spark migrate` após atualizar o projeto.

## Descrição de imagens

Execute `php spark migrate` para criar a tabela de descrições. Nas peças com prévia de imagem, usuários com a permissão `pieces.update` podem usar **Descrever Imagem**. A descrição é salva por arquivo, sem alterar o resumo da peça; versões novas não reutilizam a descrição da versão anterior. O texto gerado por IA deve ser revisado antes do uso.

O servidor envia uma cópia JPEG da imagem, com proporção preservada e maior lado de até 1600 pixels, para `MOSAICO_OLLAMA_URL` (padrão: `https://ollama.brapci.inf.br`), via `POST /api/chat`. Não envia nomes, caminhos ou metadados do usuário. O modelo padrão é `qwen3.5:2b`; configure outro modelo de visão em `MOSAICO_OLLAMA_VISION_MODEL`, se necessário. Requer GD e cURL, aceita originais JPEG, PNG, GIF (primeiro quadro) e WebP de até 20 MB e 40 megapixels, e aguarda até 120 segundos pelo Ollama. Em caso de falha, a descrição anterior é preservada. A conexão HTTPS verifica certificados e usa `MOSAICO_CA_BUNDLE` quando configurado.

O botão **Detectar pessoas** usa o mesmo serviço para estimar a quantidade e listar até 20 pessoas distinguíveis, descrevendo posições, roupas e ações visíveis. O resultado estruturado é validado antes de salvar. Usuários com `pieces.update` podem associar nomes manualmente a cada item; esses nomes não são enviados ao Ollama nem copiados para outras análises ou imagens. Novas detecções preservam os registros anteriores, e a página apresenta a análise mais recente do arquivo atual. Detecção e associação manual exigem POST com CSRF e geram eventos de auditoria sem registrar os nomes nos detalhes do evento. Execute `php spark migrate` para criar `piece_people_detections`.

Os nomes associados usam um controle de autoridade por comunidade (`person_authorities`), com identificador estável, nome preferido e qualificador opcional para distinguir homônimos. Na imagem, selecione um registro existente ou preencha um novo nome e qualificador; diferenças apenas de espaços ou maiúsculas reutilizam o mesmo registro. `piece_person_authorities` vincula cada pessoa da análise ao registro escolhido. A migração converte as associações manuais anteriores, preservando seus textos originais. A seleção é validada no servidor para impedir vínculos com autoridades de outra comunidade. Os nomes são escolhidos manualmente e não são enviados ao modelo.


## Administração de usuários

Acesse `/admin/users` pelo menu do usuário, em **Gerenciar usuários**. O administrador do sistema pode cadastrar, editar, desativar e reativar contas, alterar senhas e configurar perfis por comunidade e acessos específicos às coleções (consulta ou consulta e envio). Desativar preserva o histórico. O administrador não pode desativar a própria conta nem remover seu próprio perfil administrativo.

Execute `php spark migrate` para adicionar o perfil de administrador do sistema. A conta administrativa original da instalação (`admin@mosaico.local`) recebe esse perfil; administradores de comunidade continuam restritos às respectivas comunidades e não recebem acesso à administração global de usuários.

**Acessar como** inicia uma sessão com os acessos do usuário selecionado. Um aviso informa a identidade atual e oferece **Voltar à minha conta**. A administração global de usuários fica bloqueada durante esse acesso. O início, o término e as ações realizadas nesse período registram a identidade administrativa na auditoria. Todas as alterações e trocas de identidade exigem POST com CSRF.
