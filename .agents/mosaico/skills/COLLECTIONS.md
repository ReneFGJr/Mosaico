# COLLECTIONS.md — Diretrizes das Coleções

## Estado atual implementado

- Rotas principais: criação em `/comunidades/{id}/colecoes/nova`, seleção em `/colecoes/{id}/selecionar`, página em `/colecoes/{id}`, upload em `/colecoes/{id}/arquivos` e remoção de vínculos Draft em `/colecoes/{id}/pecas/remover-rascunhos`.
- A página oferece `Lista`, `Detalhes`, `Miniatura` e `Cards`; palavras-chave são links de filtro por `?term=` e preservam `?view=`.
- O rótulo “Peças” exibe entre parênteses a quantidade atualmente apresentada; com filtro ativo, a contagem corresponde ao resultado filtrado.
- Upload múltiplo aceita arrastar/soltar, até 20 arquivos, 100 MB por arquivo e lote do servidor até 2100 MB.
- Arquivos ficam em `writable/repository`, fora da raiz pública.
- SHA-256 repetido na mesma comunidade restaura a peça para `draft`, garante o vínculo e reaplica termos sem duplicar o arquivo.
- Na página da peça há preview privado à direita para imagem, vídeo, áudio e PDF, com fallback para outros formatos.
- Palavras-chave podem ser vinculadas ou desvinculadas da peça. Desvincular não apaga o termo global. Categorias correspondem a `vocabularies` e exigem `vocabularies.manage` para criação.
- Sessão expirada redireciona ao login; token CSRF expirado e lote acima do limite retornam mensagens amigáveis no Status do footer.

## 1. Conceito

Uma **Coleção** é um agrupamento organizado de peças, ativos e entregas dentro de uma Comunidade. Ela representa uma finalidade editorial, temática, pedagógica ou operacional comum.

Na interface em português, use sempre “Coleção” e “Coleções”. O nome técnico `collections` pode permanecer no código e no banco de dados.

Mapeamento oficial: `Coleção = collections`. Cada registro usa `collections.project_id` para apontar para a Comunidade armazenada em `projects`, e as peças são associadas por `piece_collections`.

## 2. Relações

- Toda coleção deve pertencer a exatamente uma comunidade.
- Uma comunidade pode possuir várias coleções.
- Uma coleção pode agrupar várias peças.
- Uma peça pode integrar mais de uma coleção somente quando todas pertencerem à mesma comunidade da peça.
- Coleções podem possuir hierarquia quando esse recurso for implementado.
- Não permita ciclos entre coleção superior e coleções descendentes.
- A remoção de um vínculo não deve excluir fisicamente a peça.

## 3. Identidade e metadados

Cada coleção deve possuir:

- ID estável em UUID;
- ID da comunidade;
- código único dentro da comunidade, gerado automaticamente com o acrônimo da comunidade e o UUID da coleção;
- nome obrigatório;
- descrição;
- data e autoria de criação;
- data e autoria da última alteração;
- situação e visibilidade, quando implementadas.

O código segue o formato `{ACRONIMO-DA-COMUNIDADE}-{UUID-DA-COLEÇÃO}` e não deve ser digitado pelo usuário. O UUID deve ser criado antes da inserção para que o código e a identidade da coleção sejam persistidos na mesma operação.

## 4. Criação dentro de uma comunidade

A rota canônica é:

`/comunidades/{community_id}/colecoes/nova`

O fluxo deve:

1. validar que a comunidade existe;
2. validar que o usuário pode gerenciá-la;
3. apresentar claramente o nome da comunidade no formulário;
4. gerar e validar automaticamente o código, além de validar nome e descrição no servidor;
5. criar a coleção vinculada ao ID recebido pela rota;
6. registrar auditoria;
7. redirecionar para a comunidade com mensagem de sucesso.

Nunca aceite da requisição um ID de comunidade diferente daquele definido pela rota sem nova validação de autorização.

## 5. Formulário

O formulário deve conter:

- nome;
- descrição;
- token CSRF;
- ação “Criar coleção”;
- ação “Cancelar”, retornando à comunidade de origem.

Não solicite novamente a comunidade em um campo selecionável quando o fluxo já foi iniciado dentro de uma comunidade específica.

## 6. Listagem e navegação

- Liste coleções dentro da página da respectiva comunidade.
- Exiba somente o nome da coleção nos cartões; não mostre o código técnico nessa visualização.
- Exiba a descrição logo abaixo do nome, limitada a 150 caracteres e seguida de `...` apenas quando o conteúdo ultrapassar esse limite.
- Cada coleção deve possuir a ação “Selecionar Coleção”.
- A seleção deve seguir o padrão visual das Comunidades: botão azul quando selecionada, `aria-pressed`, requisição `POST`, CSRF e cookie de navegação `HttpOnly` com `SameSite=Lax`.
- Ao selecionar uma coleção, atualize também a comunidade selecionada para manter o contexto de navegação coerente.
- Após selecionar uma coleção, redirecione para sua página própria em `/colecoes/{collection_id}`.
- A página da coleção deve apresentar nome, descrição, comunidade de origem e suas peças vinculadas.
- A página da coleção deve listar diretamente suas peças em cartões, seguindo a hierarquia `Comunidade → Coleção → Peça`.
- Ofereça os modos de visualização `Lista`, `Detalhes`, `Miniatura` e `Cards` sem alterar o conjunto de peças da coleção.
- Nos modos `Detalhes` e `Cards`, mostre as palavras-chave vinculadas por `piece_terms` no lugar da descrição da peça. Quando não houver termos, mostre “Sem palavras-chave”.
- Mostre cada palavra-chave como link. Ao acioná-la, filtre somente as peças da coleção atual vinculadas ao termo, preserve o modo de visualização escolhido e apresente uma ação clara para remover o filtro.
- Permita selecionar todas as peças em estado `draft` e remover peças selecionadas ou todas as peças em Draft da coleção.
- A remoção em massa deve excluir somente o vínculo em `piece_collections`; preserve a peça, seus ativos, versões, arquivos e histórico, e registre auditoria.
- Cada card de peça deve apresentar uma miniatura em proporção `16:9`. Quando não houver rendição visual disponível, gere uma miniatura segura baseada no título e no tipo, sem expor o arquivo original privado.
- Para peças com arquivo de imagem, use uma função `findOrCreate`: reutilize a miniatura existente ou gere um JPEG `640×360`, preserve a proporção com margens, armazene em `writable/thumbnails`, calcule SHA-256 e registre a rendição vinculada ao arquivo de origem. Use o SVG de fallback somente quando não houver imagem processável.
- Disponibilize à direita uma área de upload múltiplo. Cada arquivo aceito deve criar uma Peça, um Ativo, uma Versão inicial e um registro de Arquivo, mantendo a hierarquia e impedindo arquivos órfãos.
- Mantenha `upload_max_filesize` compatível com o limite de 100 MB por arquivo e `post_max_size` compatível com o lote múltiplo. Se o corpo exceder o limite do servidor, intercepte a requisição antes do CSRF e mostre uma mensagem clara no Status do footer.
- Armazene binários em `writable/repository`, fora da raiz pública; valide extensão, MIME detectado e limite de tamanho, calcule SHA-256 e registre auditoria.
- Permita selecionar arquivos pelo diálogo ou arrastá-los para uma zona de upload acessível.
- Antes de salvar, calcule o SHA-256 no arquivo temporário. Quando o conteúdo já existir na mesma comunidade, restaure a peça existente para `draft` e garanta seu vínculo com a coleção, sem criar outro arquivo nem reportar duplicidade como erro.
- Processe cada arquivo em transação independente; uma falha não pode invalidar ou contaminar os uploads seguintes do mesmo lote.
- Preserve `original_name` exatamente como recebido após remover componentes de caminho. No armazenamento, mantenha o nome e a extensão originais, substituindo somente caracteres inválidos para o sistema de arquivos quando necessário.
- No upload em grupo, permita selecionar termos ativos existentes por checkbox e informar novas palavras-chave separadas por vírgula, ponto e vírgula ou linha.
- Crie novas palavras-chave no vocabulário controlado `keywords` e vincule todos os termos selecionados ou criados a cada peça do grupo por `piece_terms`.
- Ao restaurar um arquivo duplicado para `draft`, aplique também as palavras-chave escolhidas, sem duplicar relações existentes.
- Na página da peça, usuários com `pieces.update` podem sincronizar termos existentes; criação de termos e categorias exige `vocabularies.manage`.
- A rota `/pecas/{piece_id}/preview` deve permanecer autenticada e nunca revelar `storage_key` ou caminhos físicos.
- Enquanto houver coleção selecionada, o item “Peças” da sidebar deve apontar para a rota filtrada dessa coleção.
- No menu lateral, apresente “Peças” como link somente quando houver uma coleção selecionada no cookie de navegação. Sem coleção selecionada, mostre “Peças” em cinza, sem link, com `aria-disabled="true"` e orientação acessível para selecionar uma coleção.
- Mostre também o item “Coleção” entre “Comunidade” e “Peças”. Com uma coleção selecionada, ele deve abrir o contexto da coleção; sem seleção, deve aparecer em cinza, sem link e com `aria-disabled="true"`.
- Quando houver uma página própria da coleção, torne seu nome um link.
- Use breadcrumbs com a sequência `Comunidades / Comunidade / Coleção`.
- Não mostre coleções de outras comunidades no mesmo contexto sem indicação clara e autorização adequada.

## 7. Peças e conteúdo

- Ao associar uma peça, valide que ela pertence à mesma comunidade da coleção.
- Não duplique a peça para incluí-la em outra coleção; crie uma relação explícita.
- Preserve a identidade estável e o histórico da peça.
- Registre inclusões e remoções de peças na auditoria.
- Contagens devem respeitar visibilidade e autorização.

## 8. Permissões

- Negue acesso por padrão.
- Somente usuários autorizados na comunidade podem criar ou alterar coleções.
- A consulta deve respeitar a visibilidade da comunidade e da coleção.
- Arquivamento, restauração e alteração de visibilidade devem ser restritos a papéis autorizados.
- Valide permissões no servidor em todas as operações.

## 9. Exclusão e preservação

- Evite exclusão física de coleções com peças, entregas ou eventos associados.
- Prefira arquivamento ou ocultação com justificativa.
- Não exclua peças ao arquivar uma coleção.
- Preserve trilhas de auditoria e vínculos históricos.

## 10. Auditoria

Registre ao menos:

- criação e edição;
- mudança de situação ou visibilidade;
- inclusão e remoção de peças;
- mudança de hierarquia;
- arquivamento e restauração.

Os eventos devem identificar usuário, data, coleção, comunidade e justificativa quando aplicável.

## 11. Acessibilidade e interface

- Use rótulos associados aos campos.
- Mostre erros próximos aos campos e também em resumo no início do formulário.
- Preserve o valor preenchido quando a validação falhar.
- Garanta foco visível e navegação completa por teclado.
- Em telas pequenas, empilhe campos e ações.
- Use mensagens claras: “Coleção criada”, “Código já utilizado nesta comunidade” e equivalentes.

## 12. Critérios de validação

Antes de concluir mudanças em Coleções, confirme que:

- toda coleção pertence a uma comunidade válida;
- a criação iniciada em uma comunidade mantém esse vínculo;
- códigos são únicos por comunidade;
- peças de outra comunidade não podem ser associadas;
- ações exigem autenticação e autorização;
- CSRF está ativo nas operações de escrita;
- criação e alterações geram auditoria;
- arquivamento não remove peças ou histórico;
- o fluxo funciona por teclado e em dispositivos móveis.
