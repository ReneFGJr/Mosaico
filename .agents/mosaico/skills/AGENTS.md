# AGENTS.md — MOSAICO

## Estado atual obrigatório

As regras desta seção descrevem a implementação vigente e prevalecem sobre referências históricas incompatíveis existentes neste documento.

- Framework: CodeIgniter 4, PHP 8.3, MySQL em produção e SQLite em memória nos testes PHPUnit.
- Front-end: HTML semântico, CSS próprio e JavaScript progressivo; Bootstrap não é uma dependência obrigatória atual.
- Estrutura: `Comunidade (projects) → Coleção (collections) → Peça (pieces) → Ativo (assets) → Versão (asset_versions) → Arquivo (repository_files)`.
- `piece_collections` associa peças e coleções; `piece_terms` associa peças e termos; `terms.vocabulary_id` organiza termos em categorias de `vocabularies`.
- Arquivos privados ficam em `writable/repository`; miniaturas ficam em `writable/thumbnails`; nunca exponha `storage_key` ou caminho físico.
- `renditions` registra derivados técnicos. Imagens processáveis geram miniatura JPEG `640×360`; outros formatos usam SVG seguro de fallback.
- `/pecas/{id}/preview` serve, por rota autenticada, previews inline de imagens seguras, MP4/WebM, MP3/WAV/OGG e PDF. Arquivos grandes devem ser transmitidos em blocos, com suporte ao cabeçalho HTTP `Range`, sem carregá-los integralmente na memória. Outros formatos exibem fallback.
- Upload múltiplo aceita até 20 arquivos, 1 GB por arquivo e lote configurado até 10 GB. Extensões atuais: PDF, PNG, JPG/JPEG, GIF, WebP, MP4, WebM, AVI, MP3, WAV, OGG, DOCX, XLSX, PPTX, TXT e CSV.
- Duplicidade é verificada por SHA-256 na mesma comunidade. Conteúdo existente é restaurado para `draft`, ligado à coleção e recebe os termos selecionados, sem criar outro binário.
- A navbar mede `72px` no desktop e `64px` até `850px`. A sidebar mede `250px`. O footer possui mínimo de `56px`.
- Mensagens temporárias aparecem somente no Status do footer.
- Sessão expirada redireciona para `/login` antes do CSRF. CSRF inválido volta à página com mensagem amigável, sem tela técnica.
- Cookies `mosaico_selected_community` e `mosaico_selected_collection` guardam somente UUIDs por 30 dias, com `HttpOnly`, `SameSite=Lax` e `Secure` em HTTPS; cookies não concedem autorização.
- Na página da peça, usuários com `pieces.update` sincronizam vínculos de termos; somente `vocabularies.manage` permite criar palavras-chave e categorias.
- O esquema vigente de acesso usa `users`, `roles`, `permissions`, `role_permissions` e `memberships`. A auditoria usa `audit_events`.

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
- Gere miniaturas persistidas para imagens processáveis. Para vídeo, áudio, PDF e documentos, mantenha o fallback seguro até existir um gerador específico implementado e testado.
- Use vocabulário controlado, padronizado e hierárquico para classificar peças, ativos e coleções.
- Não exclua fisicamente peças. Oculte-as, preserve os registros e mantenha a trilha de auditoria. Somente administradores podem ocultá-las.
- Na persistência, preserve os estados vigentes `draft`, `submitted`, `approved`, `published`, `archived` e `deleted`; traduza os rótulos apenas na interface.
- Exija metadados nos itens, incluindo ao menos título, descrição, autor, datas, palavras-chave, categoria e idioma quando aplicáveis.
- Somente administradores podem alterar a visibilidade de um item.
- Registre ações, eventos e alterações com data, hora, usuário e justificativa em uma trilha de auditoria imutável.
- Preserve integridade, autenticidade, disponibilidade, acessibilidade e legibilidade dos conteúdos ao longo do tempo, mantendo evidências verificáveis.

## Usuários, papéis e permissões

O esquema atual usa `users`, `roles`, `permissions`, `role_permissions`, `memberships` e `audit_events`. Preserve os papéis `administrator`, `repository_manager`, `producer_editor`, `reviewer`, `coordinator_approver`, `publisher`, `consultation` e `auditor`, além das permissões `projects.manage`, `pieces.create`, `pieces.read`, `pieces.update` e `vocabularies.manage`.
- Nunca armazene senha reversível; utilize hash de senha moderno e seguro.
- Proteja nome e e-mail com criptografia autenticada usando chave fornecida por configuração de ambiente. Não registre esses valores em texto puro nos logs.
- Não versione segredos nem valores reais de arquivos `.env`.
- Não reduza nem renomeie papéis e permissões sem migration e validação de compatibilidade com `memberships` e `role_permissions`.
- Aplique autorização no servidor, mesmo quando a interface ocultar ações não permitidas.

## Arquivos e armazenamento

- Armazene uploads em `writable/repository/{community}/{collection}/{item}` ou na abstração privada equivalente já adotada pelo projeto.
- Bloqueie execução de arquivos enviados pelo usuário.
- Valide extensão, MIME detectado, assinatura do arquivo, tamanho e nome seguro no servidor.
- Gere e persista SHA-256 para verificação de integridade.
- Mantenha armazenamento fora da raiz pública e entregue conteúdo somente por controller autenticado e autorizado.
- Trate originais como imutáveis; correções devem produzir novas versões.

## Tecnologias e padrões

- Banco de dados: MySQL.
- Back-end: PHP e APIs RESTful.
- Front-end: HTML semântico, CSS próprio e JavaScript progressivo.
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
- Preserve os tokens atuais: fundo `#f2eadb`, superfície `#ffffff`, texto `#292927`, primária `#075ea8`, primária escura `#064b84`, sucesso `#237a3b`, erro `#b42318` e foco/aviso `#f0b429`.
- Garanta contraste acessível; ajuste cores quando necessário para cumprir WCAG.
- Campos, checkboxes, radios e selects devem ter borda preta arredondada com raio de `4px`, respeitando estados de foco visíveis.
- Use navbar branca de `72px` no desktop e `64px` no mobile, sidebar branca de `250px` e footer cinza-claro com mínimo de `56px`.
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
