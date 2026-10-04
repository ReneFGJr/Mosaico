# AGENTS.md — MOSAICO

## Escopo

Estas instruções se aplicam ao desenvolvimento e à manutenção do **MOSAICO — Repositório de Ativos Digitais**. Preserve esse nome e não invente outra expansão para a marca.

Use os arquivos abaixo na identidade do projeto:

- Logo: `public/assets/logo/logo_mosaico.png`
- Favicon: `public/assets/logo/favicon_mosaico.ico`

## Modelo de domínio

Mantenha separados os seguintes conceitos:

Estrutura canônica e mapeamento de tabelas:

```text
Comunidade (table: projects)
└── Coleções (table: collections)
    └── Peças (table: pieces)
```

- **Comunidade** é o nome funcional da entidade persistida na tabela legada `projects`.
- `collections.project_id` vincula cada coleção à sua comunidade.
- `pieces.project_id` vincula cada peça à sua comunidade.
- `piece_collections` registra a associação entre peças e coleções.
- Não renomeie tabelas ou chaves legadas sem uma migration explícita, compatível com dados existentes e com estratégia segura de reversão.

- **Comunidade:** agrupa coleções, peças, ativos e entregas; pode possuir hierarquia de comunidades.
- **Coleção:** agrupa peças, ativos e entregas; pode possuir hierarquia.
- **Peça:** produto informacional identificável, com ID estável independentemente do título ou da localização.
- **Ativo:** recurso relacionado a uma peça. Pode ser compartilhado entre peças somente com autorização explícita.
- **Versão do ativo:** revisão lógica imutável após a submissão, contendo autoria, data, justificativa, arquivos e vínculos de derivação.
- **Arquivo:** objeto binário identificado por chave de armazenamento, tamanho, MIME detectado e SHA-256.
- **Rendição:** derivado técnico vinculado ao arquivo original e ao processo gerador; nunca substitui o original.
- **Entrega:** composição versionada submetida à revisão, com manifesto dos IDs exatos das versões e dos arquivos.
- **Publicação:** disponibilização de uma entrega aprovada em um canal, com data, endereço e histórico.

Não trate peça, ativo, versão, arquivo, rendição, entrega ou publicação como entidades equivalentes.

## Regras de negócio obrigatórias

- Respeite a hierarquia principal: comunidade → coleção → peça → ativo → versão → arquivo.
- Reflita essa hierarquia na navegação: a página da Comunidade lista suas Coleções; a página da Coleção lista suas Peças.
- Uma entidade de nível superior pode possuir várias entidades do nível seguinte.
- Todo arquivo deve pertencer a uma versão específica; não permita arquivo sem versão.
- Uma entrega pertence a uma peça e deve manter vínculos rastreáveis com sua coleção e comunidade.
- Permita vários arquivos por item quando todos estiverem associados às versões correspondentes.
- Ao importar conteúdo do Google Drive, copie o arquivo para o repositório do MOSAICO e remova o vínculo externo após uma importação confirmada. Não dependa do arquivo remoto como cópia oficial.
- Gere automaticamente miniaturas para vídeos, imagens e PDFs após o upload. Armazene-as como rendições vinculadas ao original.
- Use vocabulário controlado, padronizado e hierárquico para classificar peças, ativos e coleções.
- Não exclua fisicamente peças. Oculte-as, preserve os registros e mantenha a trilha de auditoria. Somente administradores podem ocultá-las.
- Admita os estados `rascunho`, `submetido`, `aprovado`, `publicado`, `arquivado` e `excluído`. Somente administradores podem definir `arquivado` ou `excluído`.
- Exija metadados nos itens, incluindo ao menos título, descrição, autor, datas, palavras-chave, categoria e idioma quando aplicáveis.
- Somente administradores podem alterar a visibilidade de um item.
- Registre ações, eventos e alterações com data, hora, usuário e justificativa em uma trilha de auditoria imutável.
- Preserve integridade, autenticidade, disponibilidade, acessibilidade e legibilidade dos conteúdos ao longo do tempo, mantendo evidências verificáveis.

## Usuários, papéis e permissões

Considere os papéis de administrador, gestor, autor, revisor, editor, consultor, visitante e curador no domínio. Ao implementar persistência e autorização:

- Use `users` com `id`, `nome`, `email`, `senha_hash`, `papel_id`, `criado_em` e `atualizado_em`.
- Use `users_roles`, `users_permissions` e `users_roles_permissions`.
- Em `users_roles_permissions`, inclua início e fim de vigência para permissões temporárias.
- Use `users_logs` para registrar login, logout, criação, edição, submissão, aprovação, publicação e demais ações relevantes.
- Nunca armazene senha reversível; utilize hash de senha moderno e seguro.
- Proteja nome e e-mail com criptografia autenticada usando chave fornecida por configuração de ambiente. Não registre esses valores em texto puro nos logs.
- Não versione segredos nem valores reais de arquivos `.env`.
- Se `papel_id` for mantido como enum, preserve os valores definidos pelo esquema existente. Não reduza os papéis do domínio sem validar a compatibilidade com `users_roles`.
- Aplique autorização no servidor, mesmo quando a interface ocultar ações não permitidas.

## Arquivos e armazenamento

- Armazene uploads sob `public/_repository/{community}/{collection}/{item}` ou na abstração equivalente já adotada pelo projeto.
- Bloqueie execução de arquivos enviados pelo usuário.
- Valide extensão, MIME detectado, assinatura do arquivo, tamanho e nome seguro no servidor.
- Gere e persista SHA-256 para verificação de integridade.
- Prefira armazenamento fora da raiz pública. Quando a estrutura existente exigir `public/_repository`, aplique configuração explícita do servidor para impedir execução e acesso indevido.
- Trate originais como imutáveis; correções devem produzir novas versões.

## Tecnologias e padrões

- Banco de dados: MySQL.
- Back-end: PHP e APIs RESTful.
- Front-end: HTML, CSS, JavaScript e Bootstrap.
- Código PHP: PSR-1, PSR-4, PSR-7 e PSR-12. Não introduza código novo baseado em PSR-2 quando PSR-12 for aplicável.
- Testes: PHPUnit, cobrindo unidades, integrações e fluxos funcionais.
- Infraestrutura: Apache ou Nginx com HTTPS.
- Acessibilidade: WCAG 2.1 ou versão superior já adotada pelo projeto.
- Segurança: OWASP Top 10, incluindo proteção contra SQL injection, XSS e CSRF.
- Preservação digital: princípios OAIS e exportação interoperável, quando aplicável, em formatos como PDF/A, TIFF, XML, JSON e CSV, inclusive para Dataverse ou DSpace.

Antes de introduzir bibliotecas, frameworks, estrutura de pastas ou convenções novas, inspecione e siga o que já existe no repositório.

## Interface e experiência de uso

- Produza interfaces responsivas, acessíveis e amigáveis, com instruções passo a passo e mensagens de erro claras.
- Use Open Sans, tamanho-base de `16px`, salvo quando o design existente definir tokens equivalentes.
- Preserve a paleta de referência: fundo `#f2eadb`, texto `#333333`, primária `#007bff`, sucesso `#28a745`, erro `#dc3545`, aviso `#ffc107` e informação `#17a2b8`.
- Garanta contraste acessível; ajuste cores quando necessário para cumprir WCAG.
- Campos, checkboxes, radios e selects devem ter borda preta arredondada com raio de `4px`, respeitando estados de foco visíveis.
- Use navbar superior branca de `60px`, sidebar branca de `250px` e footer cinza-claro de `40px` quando compatíveis com a tela e o layout responsivo.
- Links devem ser legíveis, possuir estado de foco e apresentar sublinhado no hover.
- Inclua navegação clara, breadcrumbs e feedback visual. Feedback sonoro deve ser opcional e acessível.
- Não use elementos fixos de modo que ocultem conteúdo, prejudiquem telas pequenas ou impeçam navegação por teclado.

### Mensagens e área de status

- Reserve no footer uma área identificada como **Status** para mensagens temporárias do sistema.
- Mostre mensagens de sucesso, erro, aviso e validação somente nessa área; não duplique alertas no conteúdo principal, navbar, sidebar ou em notificações flutuantes.
- Exemplos como “Comunidade selecionada para a navegação.” devem aparecer exclusivamente no footer.
- Use `aria-live="polite"` para atualizações comuns e `role="alert"` para erros que exijam atenção imediata.
- Quando não houver mensagem temporária, mostre um estado neutro e curto, como “Sistema disponível.”
- Não use apenas cor para comunicar o tipo da mensagem; preserve texto explícito e legível.
- Escape todo conteúdo dinâmico antes de exibi-lo e nunca inclua dados pessoais, credenciais, tokens ou detalhes internos de exceções.
- Garanta que mensagens extensas possam ser lidas em telas pequenas sem provocar rolagem horizontal nem encobrir o conteúdo.

### Botões de cancelamento

- Apresente toda ação textual **“Cancelar”** com aparência de botão, usando as classes globais `button cancel-button`.
- Use fundo laranja escuro `#b54708`, borda da mesma cor e texto branco para manter contraste acessível.
- No hover, foco e estado ativo, use o tom `#93370d` e preserve o indicador de foco global.
- Não mostre sublinhado no botão “Cancelar” em estado normal, visitado, hover, foco ou ativo.
- “Cancelar” deve ser uma ação segura de navegação que retorna ao contexto anterior sem salvar alterações; não implemente essa ação como submissão destrutiva.
- Mantenha o rótulo explícito “Cancelar” e não dependa apenas de ícone ou cor.

## Qualidade, testes e validação

Ao alterar comportamento, inclua ou atualize testes proporcionais ao risco:

- testes unitários para funções e regras isoladas;
- testes de integração entre banco, serviços, armazenamento e APIs;
- testes funcionais dos principais fluxos de usuário;
- testes de autorização por papel e permissão;
- testes de upload, MIME, tamanho, integridade e bloqueio de execução;
- testes de desempenho para operações críticas;
- validação de dados no cliente para usabilidade e obrigatoriamente no servidor para segurança;
- verificação de acessibilidade, incluindo teclado, leitores de tela, foco e contraste.

Antes de concluir uma mudança:

1. Execute os testes e verificadores disponíveis no repositório.
2. Confirme que migrações preservam dados e possuem estratégia segura de reversão.
3. Verifique que logs não expõem dados pessoais, credenciais, chaves ou conteúdo sensível.
4. Confirme que exclusões lógicas, versionamento, auditoria e rastreabilidade continuam íntegros.
5. Documente decisões que alterem o modelo de domínio, os formatos de exportação ou as políticas de preservação.

## Conduta do agente

- Leia o código e a documentação próximos antes de editar.
- Faça mudanças pequenas, coerentes com a arquitetura existente e fáceis de revisar.
- Não apague dados, arquivos, migrações ou histórico sem solicitação explícita e confirmação do escopo.
- Preserve alterações existentes do usuário e não reverta trabalho alheio.
- Em requisitos conflitantes, priorize segurança, integridade, rastreabilidade, acessibilidade e compatibilidade com o sistema existente; registre a decisão adotada.

## Estruta do Projeto
- Comunidade (table: projects)
    +- Coleções (table: collections)
        +- Peças (table: pieces)
