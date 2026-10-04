# COMUNIDADES.md — Diretrizes das Comunidades

## Estado atual implementado

- Rotas canônicas: `/comunidades`, `/comunidades/nova`, `/comunidades/{id}`, `/editar`, `/permissoes`, `/visibilidade`, `/excluir` e `/colecoes/nova`.
- Rotas legadas `/projetos...` apenas redirecionam para `/comunidades...`.
- A criação associa o autor como `administrator`; `memberships` relaciona comunidade, usuário e papel.
- A seleção grava `mosaico_selected_community` por 30 dias com `HttpOnly`, `SameSite=Lax` e `Secure` em HTTPS, então abre `/comunidades/{id}`.
- Na listagem, o menu contém “Editar dados”, “Permissões”, “Visibilidade” e “Excluir”; não contém “Criar coleção” nem “Ver comunidade”.
- Na página da comunidade, “Criar coleção” é o primeiro item do menu de ações e a grade mostra somente as coleções dessa comunidade.
- Exclusão é lógica: `status=excluded` e `visibility=excluded`, preservando conteúdo e auditoria.
- Sessão expirada redireciona ao login antes do CSRF; mensagens aparecem somente no Status do footer.

## 1. Conceito

Uma **Comunidade** é o nível organizacional superior do MOSAICO. Ela reúne coleções, peças, ativos e entregas relacionados a uma instituição, programa, campanha ou finalidade comum.

O termo “Comunidade” deve ser utilizado em toda interface, documentação e comunicação com o usuário. A tabela legada `projects` e o campo `project_id` podem permanecer internamente enquanto forem necessários para compatibilidade, mas nunca devem fazer a interface voltar a exibir “Projeto” como nome da entidade.

Mapeamento oficial: `Comunidade = projects`. O identificador `projects.id` é referenciado por `collections.project_id` e `pieces.project_id`.

## 2. Relações

A hierarquia principal é:

`Comunidade → Coleção → Peça → Ativo → Versão → Arquivo`

- Uma comunidade pode possuir várias coleções.
- Uma comunidade pode possuir várias peças.
- Cada coleção deve pertencer a uma comunidade.
- Cada peça deve estar vinculada a uma comunidade e pode integrar uma ou mais coleções dessa mesma comunidade.
- Uma comunidade pode possuir uma comunidade superior quando a hierarquia institucional for implementada.
- Não permita ciclos em hierarquias de comunidades.

## 3. Identidade e metadados

Cada comunidade deve possuir:

- ID estável em UUID;
- código único;
- nome obrigatório;
- descrição;
- situação operacional;
- data e autoria de criação;
- data e autoria da última alteração;
- visibilidade, quando esse controle estiver implementado.

Alterações de nome, código ou localização não devem modificar o ID estável da comunidade.

## 4. Criação

- A criação deve ocorrer pela rota `/comunidades/nova`.
- Após a criação, redirecione para a página da nova comunidade.
- Registre o evento `community.created` ou o evento legado equivalente na trilha de auditoria.
- Associe o criador à comunidade com o papel administrativo apropriado quando essa associação ainda não existir.
- Valide código e nome no servidor.
- Rejeite códigos duplicados sem expor detalhes internos do banco de dados.

## 5. Página de listagem

Em `/comunidades`, cada comunidade deve ser apresentada em um cartão com:

- código;
- nome;
- descrição resumida;
- ação “Selecionar Comunidade”.
- menu suspenso de ações com “Editar dados”, “Permissões”, “Visibilidade” e “Excluir”.

O menu de ações deve:

- permitir gerenciar acessos de usuários e papéis;
- permitir definir a visibilidade como `Público`, `Restrito` ou `Excluído`;
- executar alterações de visibilidade por `POST` com CSRF;
- executar “Excluir” como exclusão lógica, nunca como remoção física;
- solicitar confirmação antes da exclusão lógica;
- permanecer utilizável por teclado e apresentar foco visível.

Na listagem geral não mostre “Nova coleção” nem “Criar coleção”. A criação deve partir da página da comunidade e carregar explicitamente o ID na URL `/comunidades/{community_id}/colecoes/nova`.

A seleção de comunidade deve ocorrer por `POST` com CSRF, salvar somente o UUID em cookie de navegação `HttpOnly` e `SameSite=Lax`, validar o UUID novamente no servidor sempre que for utilizado e abrir a comunidade selecionada. O cookie não concede autorização e não deve armazenar dados pessoais.

No menu lateral, mostre o item singular “Comunidade”. Quando houver uma comunidade selecionada no cookie de navegação, o item deve apontar para `/comunidades` e mostrar todas as comunidades disponíveis, não diretamente para a comunidade selecionada. Sem seleção, mostre o item em cinza, sem link, com `aria-disabled="true"` e orientação acessível para selecionar uma comunidade.

Não use um seletor ambíguo quando a ação partir do cartão de uma comunidade específica.

## 6. Página da comunidade

A página `/comunidades/{community_id}` deve apresentar:

- identificação e descrição da comunidade;
- suas coleções em cartões, com seleção contextual;
- ação para criar coleção;
- breadcrumbs para retornar à lista de comunidades.
- menu suspenso com “Criar coleção” como primeiro item, seguido de “Editar dados”, “Visibilidade” e “Excluir”.

“Editar dados” deve abrir um formulário preenchido com código, nome e descrição. Alterações devem validar permissões, manter o UUID da comunidade e gerar evento de auditoria.

Ao exibir conteúdo interno de uma comunidade, não misture coleções, peças ou ativos pertencentes a outra comunidade. A grade de seleção de comunidades é a exceção explícita, pois apresenta apenas as comunidades disponíveis ao usuário.

A grade principal da página deve listar somente as coleções pertencentes à comunidade.

## 7. Permissões

- Negue acesso por padrão.
- Verifique permissões no servidor e no contexto da comunidade.
- Administradores e gestores autorizados podem criar e editar comunidades.
- Administradores e gestores autorizados podem gerenciar acessos e visibilidade.
- Somente administradores podem executar a ação “Excluir”.
- A criação de coleções exige permissão de gerenciamento na comunidade correspondente.
- Ocultar uma ação na interface não substitui a validação no servidor.
- Permissões temporárias devem respeitar início e fim de vigência.

## 8. Exclusão e preservação

- Não exclua fisicamente uma comunidade que possua conteúdo ou histórico.
- Use arquivamento ou ocultação com justificativa e auditoria.
- Preserve relações com coleções, peças, ativos, entregas e eventos históricos.
- Antes de arquivar, informe claramente os impactos sobre acesso e visibilidade.

## 9. Auditoria

Registre ao menos:

- criação;
- edição de metadados;
- mudança de situação ou visibilidade;
- associação e remoção de membros;
- criação de coleções;
- arquivamento e restauração.

Cada evento deve conter data, hora, usuário, entidade, ID e contexto suficiente para auditoria, sem expor dados pessoais desnecessários.

## 10. Acessibilidade e interface

- Use o título “Comunidades”.
- Empregue cartões semânticos e títulos em ordem hierárquica correta.
- Não aninhe links ou botões interativos dentro de outro link.
- Garanta foco visível, navegação por teclado e área clicável adequada.
- Em telas pequenas, empilhe as ações sem provocar rolagem horizontal.
- Mensagens de sucesso e erro devem identificar a operação realizada.

## 11. Critérios de validação

Antes de concluir mudanças em Comunidades, confirme que:

- o termo “Projeto” não aparece como nome da entidade na interface;
- a listagem permite selecionar a comunidade, e a criação de coleção parte da página da comunidade;
- a nova coleção fica vinculada à comunidade correta;
- códigos duplicados são rejeitados;
- rotas exigem autenticação e autorização;
- ações relevantes geram auditoria;
- dados de outras comunidades não vazam em listas, buscas ou contagens;
- as URLs legadas, se mantidas, redirecionam sem duplicar conteúdo.
