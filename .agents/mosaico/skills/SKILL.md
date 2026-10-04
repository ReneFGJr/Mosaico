---
name: proneto-mosaico
description: Projetar, implementar, testar e manter o MOSAICO — Repositório de Ativos Digitais do Mais Saúde com Agente. Usar ao desenvolver banco de peças, catalogação, arquivos, versionamento, revisão, publicação, busca, rastreabilidade, segurança e preservação do MOSAICO.
---

# MOSAICO — Repositório de Ativos Digitais

## 1. Identidade, finalidade e origem

Use o logo D:\Projeto\Mosaico\public\assets\logo\logo_mosaico.png para identificar o projeto.

Use o favicon D:\Projeto\Mosaico\public\assets\logo\favicon_mosaico.ico para identificar o projeto.

O projeto **MOSAICO — Repositório de Ativos Digitais** é um projeto de **Repositório de Ativos Digitais**.

Adotar a marca **MOSAICO — Repositório de Ativos Digitais**, não inventar outra expansão obrigatória para o nome.


## 2. Conceitos e invariantes

- **Peça:** produto informacional identificável, como uma videoaula ou cartilha; manter ID estável independentemente de título e localização.
- **Ativo:** recurso relacionado à peça, como roteiro, vídeo bruto, projeto editável, trilha ou versão final. Permitir ativo compartilhado por várias peças com autorização explícita.
- **Versão do ativo:** revisão lógica imutável após submissão; agregar um ou mais arquivos, autoria, data, justificativa e vínculos de derivação.
- **Arquivo:** objeto binário com identificador, chave de armazenamento, tamanho, MIME detectado e SHA-256; não confundir com peça ou versão.
- **Rendição:** derivado técnico, como miniatura ou vídeo de prévia; vincular ao arquivo de origem e ao processo que o gerou, sem substituir o original.
- **Entrega:** composição versionada dos ativos necessários para apresentar uma peça à revisão. Registrar manifesto com IDs exatos de versões e arquivos.
- **Publicação:** disponibilização de uma entrega aprovada em um canal, com data, endereço e histórico.
- **Coleção:** agrupamento de peças, ativos e entregas com finalidade comum, como um curso ou série de vídeos; permitir hierarquia de coleções.
- **Comunidade:** agrupamento de coleções, peças, ativos e entregas com finalidade comum, como uma instituição, programa ou campanha; permitir hierarquia de comunidades.
- **Rastreamento:** registro de ações, eventos e mudanças, com data, hora, usuário e justificativa; manter trilha imutável para auditoria.
- **Preservação:** conjunto de ações e condições que garantem a integridade, autenticidade, acessibilidade e legibilidade de peças, ativos e arquivos ao longo do tempo; manter evidências verificáveis.
- **Segurança:** conjunto de ações e condições que garantem a confidencialidade, integridade, disponibilidade e autenticidade de peças, ativos e arquivos; manter evidências verificáveis.
- **Acesso:** conjunto de ações e condições que garantem a disponibilidade de peças, ativos e arquivos; manter evidências verificáveis.
- **Autorização:** conjunto de ações e condições que garantem a permissão de acesso a peças, ativos e arquivos; manter evidências verificáveis.
- **Visibilidade:** conjunto de ações e condições que garantem a percepção de peças, ativos e arquivos; manter evidências verificáveis.
- **Versionamento:** conjunto de ações e condições que garantem a rastreabilidade de peças, ativos e arquivos; manter evidências verificáveis.

## 3. Usuários e papéis
- **Administrador:** pessoa responsável por gerenciar o sistema e fornecer suporte técnico.
- **Gestor:** pessoa responsável por gerenciar comunidades, coleções, peças e ativos.
- **Autor:** pessoa responsável por criar e submeter peças e ativos.
- **Revisor:** pessoa responsável por revisar e aprovar peças e ativos.
- **Editor:** pessoa responsável por editar peças e ativos.
- **Consultor:** pessoa responsável por consultar peças e ativos.
- **Visitante:** pessoa responsável por visualizar peças e ativos.
- **Curador:** pessoa responsável por organizar e manter coleções de peças e ativos.

Estrutura de papéis e permissões:
- Tabela 'users' com campos: id, nome, email, senha_hash, papel_id, criado_em, atualizado_em. Para senha use um criptografia segura de acordo com a chave em .env
- Tabela 'users_roles' com campos: id, nome, descricao, criado_em, atualizado_em.
- Tabela 'users_permissions' com campos: id, nome, descricao, criado_em, atualizado_em.
- Tabela 'users_roles_permissions' com campos: id, role_id, permission_id, criado_em, atualizado_em. Nessa tabela crie um campo para vigência da permissão, com data de início e fim, para permitir que uma permissão seja temporária.
- O campo e-mail, nome deixe encriptografado, para que não seja possível identificar o usuário a partir do banco de dados. Use uma criptografia segura de acordo com a chave em .env
- O campo papel_id, deixe como enum, com valores: admin, curador, leitor.
- O campo criado_em, atualizado_em, deixe como timestamp, para registrar a data de criação e atualização do usuário.
- Tabela de 'users_logs' com campos: id, user_id, acao, descricao, criado_em. Para registrar as ações dos usuários no sistema, como login, logout, criação de peça, edição de ativo, submissão de entrega, aprovação de publicação, etc.

## 4 . Funcionalidades e requisitos
- **Cadastro de usuários:** permitir que administradores cadastrem novos usuários com papéis e permissões específicas.
- **Autenticação e autorização:** implementar autenticação segura e controle de acesso baseado em papéis e permissões.
- **Gerenciamento de peças e ativos:** permitir que autores criem, editem, submetam e revisem peças e ativos, com versionamento e rastreabilidade.
- **Gerenciamento de comunidades e coleções:** permitir que gestores organizem peças e ativos em coleções e comunidades, com hierarquia e visibilidade controlada.
- **Rastreameabilidade e auditoria:** manter trilha imutável de ações, eventos e mudanças, com data, hora, usuário e justificativa, para fins de auditoria e conformidade.
- **Preservação e segurança:** implementar medidas de preservação e segurança para garantir a integridade, autenticidade, acessibilidade e legibilidade de peças, ativos e arquivos ao longo do tempo

## 5. Tecnologias e padrões
- **Banco de dados:** MySQL, com suporte a criptografia e auditor
- **APIs:** RESTful, com suporte a autenticação e autorização
- **Linguagens de programação:** PHP, com suporte a frameworks e bibliotecas BOOTSTRAP
- **Front-end:** HTML, CSS, JavaScript, com suporte a frameworks e bibliotecas BOOTSTRAP
- **Padrões de codificação:** PSR-1, PSR-2, PSR-4, PSR-7, PSR-12
- **Controle de versão:** Git, com suporte a branches, tags e pull requests
- **Documentação:** Markdown, com suporte a geração de documentação automática
- **Testes:** PHPUnit, com suporte a testes unitários, de integração e funcionais
- **Hospedagem:** Servidor web Apache ou Nginx, com suporte a HTTPS e certificados SSL
- **Monitoramento e logging:** Ferramentas de monitoramento e logging para rastrear desempenho, erros e eventos do sistema
- **Backup e recuperação:** Estratégias de backup e recuperação para garantir a disponibilidade e integridade dos dados do sistema
- **Padrões de acessibilidade:** WCAG 2.1, com suporte a leitores de tela e navegação por teclado
- **Padrões de segurança:** OWASP Top 10, com suporte a prevenção de ataques comuns, como injeção de SQL, XSS e CSRF
- **Padrões de preservação digital:** OAIS, com suporte a estratégias de preservação e migração de formatos de arquivo e possibilidade de exportação e publicação de peças e ativos em formatos abertos e interoperáveis, como PDF/A, TIFF, XML, JSON, CSV, etc em Dataverse ou DSpace
- **Interfaces UI Responsivas:** Design responsivo para suportar diferentes tamanhos de tela e dispositivos, com suporte a frameworks e bibliotecas BOOTSTRAP

## 6. Estrutura de pastas e arquivos
- **D:\Projeto\Mosaico\public\assets\logo:** pasta para armazenar os arquivos de logo e favicon do projeto.
- **D:\Projeto\Mosaico\public\assets\css:** pasta para armazenar os arquivos de estilo CSS do projeto.
- **D:\Projeto\Mosaico\public\assets\js:** pasta para armazenar os arquivos de script JavaScript do projeto.
- **D:\Projeto\Mosaico\public\assets\img:** pasta para armazenar os arquivos de imagem do projeto.
- **D:\Projeto\Mosaico\public\assets\fonts:** pasta para armazenar os arquivos de fonte do projeto.
- **D:\Projeto\Mosaico\public\_repository\{community}\{collection}\{Item}:** pasta para armazenar os arquivos enviados pelos usuários, com subpastas para cada tipo de ativo e versão. Não possibilita executar arquivos, para evitar vulnerabilidades de segurança. Permitir apenas upload de arquivos de mídia, documentos e outros formatos seguros.

## 7. Interfaces e usabilidade
- **Padrão de Cores:** Utilizar cores consistentes com a identidade visual do projeto, garantindo contraste adequado e acessibilidade.
- **Tipografia:** Utilizar fontes legíveis e consistentes com a identidade visual do projeto, garantindo hierarquia visual e legibilidade.
- **Navegação:** Implementar navegação intuitiva e consistente, com menus claros e breadcrumbs para facilitar a localização de peças, ativos e coleções
- **Formulários:** Implementar formulários claros e consistentes, com validação de campos e mensagens de erro amigáveis, garantindo a usabilidade e acessibilidade. Todas as bordas dos imputs, checkboxes, radio buttons e selects devem ter bordas arredondadas, na cor preta,com raio de 4px, para manter a consistência visual.
- **Feedback de ações:** Fornecer feedback visual e sonoro para ações do usuário, como submissão de formulários, aprovação de peças e ativos, e erros do sistema, garantindo a compreensão das ações realizadas.
- **NavBar:** Implementar uma barra de navegação fixa no topo da página, com links para as principais seções do sistema, como peças, ativos, coleções e comunidades. A navbar deve ter um fundo branco, com altura de 60px, e os links devem ter cor preta e efeito hover com sublinhado.
- **Footer:** Implementar um rodapé fixo na parte inferior da página, com informações de contato, links para redes sociais e direitos autorais. O footer deve ter um fundo cinza claro, com altura de 40px, e os links devem ter cor preta e efeito hover com sublinhado.
- ** Crie um menu lateral (sidebar) para navegação entre as seções do sistema, com links para peças, ativos, coleções, comunidades e configurações. A sidebar deve ter um fundo branco, com largura de 250px, e os links devem ter cor preta e efeito hover com sublinhado.
- **Cores CSS**: Utilize cores como #f2eadb para fundo, #333333 para texto, #007bff para links e botões, #28a745 para sucesso, #dc3545 para erro, #ffc107 para aviso e #17a2b8 para informação.
- **Fonte:** Utilize a fonte Open Sans, com tamanho de 16px, e cor preta.

## 8. Testes e validação
- **Testes unitários:** Implementar testes unitários para funções e métodos individuais, garantindo que cada componente do sistema funcione corretamente de forma isolada.
- **Testes de integração:** Implementar testes de integração para verificar a interação entre diferentes componentes do sistema, garantindo que eles funcionem corretamente em conjunto.
- **Testes funcionais:** Implementar testes funcionais para verificar o comportamento do sistema como um todo, garantindo que ele atenda aos requisitos e expectativas dos usuários.
- ** Testes de usabilidade:** Realizar testes de usabilidade com usuários reais, coletando feedback sobre a experiência de uso, navegabilidade e acessibilidade do sistema, e implementando melhorias com base nesse feedback.
- **Testes de segurança:** Realizar testes de segurança para identificar vulnerabilidades e falhas no sistema, implementando medidas de proteção e mitigação de riscos.
- **Testes de desempenho:** Realizar testes de desempenho para avaliar a velocidade, escalabilidade e capacidade do sistema, implementando otimizações e melhorias conforme necessário.
- **Validação de dados:** Implementar validação de dados no lado do cliente e do servidor, garantindo que os dados inseridos pelos usuários estejam corretos, completos e consistentes, e que não comprometam a integridade do sistema.
- **Validação de acessibilidade:** Implementar validação de acessibilidade, garantindo que o sistema esteja em conformidade com os padrões de acessibilidade, como WCAG 2.1, e que seja utilizável por pessoas com diferentes habilidades e necessidades, incluindo suporte a leitores de tela, navegação por teclado e contraste adequado de cores.

## Regras de negócio
- **Regra de negócio 1:** Deve-se ter uma Comunidade, que pode ter várias Coleções, que podem ter várias Peças, que podem ter vários Ativos, que podem ter várias Versões, que podem ter vários Arquivos.
- **Regra de negócio 2:** Deve-se ter uma coleção, que pode ter várias Peças, que podem ter vários Ativos, que podem ter várias Versões, que podem ter vários Arquivos.
- **Regra de negócio 3:** Deve-se ter uma Peça, que pode ter vários Ativos, que podem ter várias Versões, que podem ter vários Arquivos.
- **Regra de negócio 4:** Deve-se ter um Ativo, que pode ter várias Versões, que podem ter vários Arquivos.
- **Regra de negócio 5:** Deve-se ter uma Versão, que pode ter vários Arquivos.
- **Regra de negócio 6:** Deve-se ter um Arquivo, que pertence a uma Versão, que pertence a um Ativo, que pertence a uma Peça, que pertence a uma Coleção, que pertence a uma Comunidade.
- **Regra de negócio 7:** Deve-se ter uma Entrega, que pertence a uma Peça, que pertence a uma Coleção, que pertence a uma Comunidade.
- **Regra de negócio 8:** Poderá subir vários arquivos para um mesmo item, mas cada arquivo deve pertencer a uma versão específica do item. Não é permitido subir arquivos sem associá-los a uma versão.
- **Regra de negócio 9:** Poder-a ser importado os dados de um link do GoogleDrive, mas o arquivo importado deve ser copiado para o repositório do MOSAICO e não deve permanecer no GoogleDrive. O link do GoogleDrive deve ser removido após a importação.
- **Regra de negócio 10:** Poderá ser importado os dados de um link do GoogleDrive, mas o arquivo importado deve ser copiado para o repositório do MOSAICO e não deve permanecer no GoogleDrive. O link do GoogleDrive deve ser removido após a importação.
- **Regra de negócio 11:** Deve-se criar um miniatura para cada arquivo de vídeo, imagem e PDF, para facilitar a visualização e identificação dos arquivos. A miniatura deve ser gerada automaticamente após o upload do arquivo e deve ser armazenada no repositório do MOSAICO.
- **Regra de negócio 12:** Deve-se ter um vocabulário controlado para classificar as Peças, Ativos e Coleções, com termos padronizados e hierárquicos, para facilitar a busca e a organização dos conteúdos.
- **Regra de negócio 13:** Não se pode excluir peças, elas ficaram ocultas no sistema, mas não serão removidas do banco de dados, para manter a rastreabilidade e a preservação dos conteúdos. Apenas administradores podem ocultar peças.
- **Regra de negócio 14:** Os itens poderão ter os statuses: rascunho, submetido, aprovado, publicado, arquivado e excluído. Apenas administradores podem alterar o status de um item para arquivado ou excluído.
- **Regra de negócio 15:** Nos items podem ser adicionados metadados, como título, descrição, autor, data de criação, data de modificação, palavras-chave, categoria, idioma e outros campos relevantes para a identificação e organização dos conteúdos. Os metadados devem ser obrigatórios para todos os itens e devem ser preenchidos pelos autores no momento da criação ou edição do item.
- **Regra de negócio 16:** Apenas administradores podem alterar a visibilidade de um item.
- **Regra de negócio 17:** Deixe a iterface user-friendly, com mensagens de erro claras e instruções passo a passo para guiar os usuários na criação, edição, submissão e aprovação de peças e ativos.
