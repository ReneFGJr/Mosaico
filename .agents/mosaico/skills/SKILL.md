---
name: criar-mosaico
description: Projetar, implementar, testar e manter o MOSAICO — Repositório de Ativos Digitais do Mais Saúde com Agente. Usar ao desenvolver banco de peças, catalogação, arquivos, versionamento, revisão, publicação, busca, rastreabilidade, segurança e preservação do MOSAICO.
---

# MOSAICO — Repositório de Ativos Digitais

## 1. Identidade, finalidade e origem

Adotar a marca **MOSAICO — Repositório de Ativos Digitais**, com vinculação institucional **Mais Saúde com Agente**. Não inventar outra expansão obrigatória para o nome.

Implementar aplicação web para cadastrar peças produzidas pelo programa e gerenciar os ativos relacionados durante produção, edição, revisão, aprovação, publicação, reaproveitamento e preservação. Abranger vídeos, áudios, imagens, cards, infográficos, animações, apresentações, documentos, roteiros, legendas, transcrições, materiais educacionais e arquivos-fonte de edição.

Considerar como origem das necessidades as competências de organização, descrição, classificação, recuperação, versionamento, preservação, segurança, rastreabilidade, controle de arquivos e orientação às equipes do Edital nº 03/2026 do Mais Saúde com Agente, especialmente os cargos de Gestor de Repositório e Organização da Informação. Tratar este documento como especificação de produto proposta: o edital não determina arquitetura, banco de dados, endpoints, SLA ou software específico.

## 2. Procedimento do agente

1. Ler AGENTS.md, estrutura do projeto, dependências, configurações e código existente antes de editar.
2. Reutilizar a arquitetura existente. Em projeto novo, propor arquitetura simples e registrar decisões em docs/arquitetura.md. Não impor framework nem reescrever o projeto sem necessidade.
3. Criar docs/requisitos-mosaico.md com identificadores deste documento, prioridade, implementação, teste e pendências. Distinguir requisito implementado, parcial e planejado.
4. Fazer incrementos executáveis com migrações, validações, interface e testes do comportamento. Não entregar somente telas simuladas.
5. Usar dados sintéticos para demonstrações. Não importar arquivos reais nem integrar serviços externos sem configuração fornecida.
6. Documentar configurações ainda desconhecidas: volume e tamanho de arquivos, usuários simultâneos, infraestrutura, autenticação institucional, público do catálogo, retenção, RPO e RTO. Usar valores configuráveis e declarar hipóteses; não bloquear tarefas independentes.
7. Não declarar preservação, segurança, acessibilidade ou conformidade normativa comprovadas sem evidências. Documentar limites e testes realizados.
8. Não executar exclusões definitivas, migrar produção ou publicar materiais externamente apenas porque esta skill foi invocada. Seguir o escopo autorizado pelo usuário.

## 3. Conceitos e invariantes

- **Peça:** produto informacional identificável, como uma videoaula ou cartilha; manter ID estável independentemente de título e localização.
- **Ativo:** recurso relacionado à peça, como roteiro, vídeo bruto, projeto editável, trilha ou versão final. Permitir ativo compartilhado por várias peças com autorização explícita.
- **Versão do ativo:** revisão lógica imutável após submissão; agregar um ou mais arquivos, autoria, data, justificativa e vínculos de derivação.
- **Arquivo:** objeto binário com identificador, chave de armazenamento, tamanho, MIME detectado e SHA-256; não confundir com peça ou versão.
- **Rendição:** derivado técnico, como miniatura ou vídeo de prévia; vincular ao arquivo de origem e ao processo que o gerou, sem substituir o original.
- **Entrega:** composição versionada dos ativos necessários para apresentar uma peça à revisão. Registrar manifesto com IDs exatos de versões e arquivos.
- **Publicação:** disponibilização de uma entrega aprovada em um canal, com data, endereço e histórico.

Separar estado editorial, visibilidade, estado técnico de processamento e condição de preservação. Nunca tornar um arquivo público somente por estar aprovado. Manter relações verificáveis entre original, derivado, versão, aprovação e publicação.

## 4. Perfis e autorização — REQ-01

Implementar permissões por ação e por projeto/coleção, verificadas no servidor:

| Perfil | Responsabilidades padrão |
| --- | --- |
| Administrador | Gerenciar usuários, permissões, configurações e infraestrutura |
| Gestor do repositório | Curar metadados, vocabulários, organização, preservação e rotinas |
| Produtor/editor | Criar peças, enviar arquivos e versões, preparar entregas |
| Revisor | Avaliar entregas, registrar parecer e solicitar ajustes |
| Coordenador/aprovador | Aprovar entregas e autorizar publicação |
| Publicador | Registrar ou executar publicação autorizada |
| Consulta | Localizar e acessar materiais permitidos |
| Auditor | Consultar evidências e trilhas conforme escopo, sem editar conteúdos |

Permitir acumulação de perfis conforme política local. Como padrão, impedir aprovação da própria entrega; tornar exceções explícitas, justificadas e auditadas. Não conceder a administradores aprovação editorial automática. Adotar negação por padrão e isolamento entre projetos em APIs, busca, contagens, miniaturas, downloads, exportações e tarefas assíncronas.

## 5. Banco de peças e organização — REQ-02

- Cadastrar programas, projetos, cursos, módulos, unidades, coleções, equipes e responsáveis. Permitir peça vinculada a mais de um curso/coleção.
- Cadastrar peça antes de haver arquivo, com código estável, título, resumo, tipo, finalidade pedagógica, público, tema, responsáveis, prazo e estado.
- Relacionar peças por faz-parte-de, complementa, substitui, reutiliza e deriva-de. Impedir ciclos nas relações hierárquicas.
- Manter visões por projeto, curso, tipo, assunto, etapa e responsável; não depender apenas de pastas físicas.
- Permitir classificação e indexação com vocabulários controlados, sinônimos e identificadores estáveis. Preservar histórico de fusões e termos desativados.
- Registrar origem, contexto e condições de reaproveitamento; copiar/reutilizar material sem perder proveniência nem ampliar permissões indevidamente.

## 6. Metadados e qualidade — REQ-03

Implementar dicionário de dados com definição, tipo, cardinalidade, obrigatoriedade por etapa, vocabulário e regra de validação. Permitir extensões tipadas por tipo de material, sem substituir os campos essenciais por texto livre.

| Nível | Campos essenciais |
| --- | --- |
| Peça | ID, código, título, descrição, tipo, projeto, responsáveis, criação, estado, visibilidade |
| Descrição e contexto | Curso/módulo, assunto, palavras-chave, idioma, público, finalidade, autoria e colaboradores com papéis |
| Direitos | Titular, licença/condição de uso, fonte, restrições, validade e evidências de autorização quando aplicáveis |
| Ativo e versão | Papel, número da versão, autor do envio, justificativa, origem, versão anterior e datas |
| Arquivo | Nome original, nome padronizado, MIME detectado, extensão, bytes, checksum, chave de armazenamento, estado de verificação |
| Técnicos | Duração, codec, resolução, canais, taxa de amostragem, páginas ou dimensões conforme o formato |
| Acessibilidade | Texto alternativo, legenda, transcrição, audiodescrição e resultado de verificação conforme aplicabilidade |
| Publicação | Entrega, canal, URL/ID externo, responsável, data e retirada/substituição |
| Preservação | Política, eventos de integridade, migrações, localização das cópias, retenção e impedimento de descarte |

Exigir direitos conhecidos e documentação aplicável antes da publicação. Aceitar metadados técnicos desconhecidos com motivo, sem fabricar valores. Registrar histórico de alterações de metadados com agente, data e valores anteriores/posteriores. Manter revisão de metadados separada da versão binária. Usar UTC para armazenamento e America/Sao_Paulo para exibição configurável.

## 7. Ingestão e armazenamento — REQ-04

- Implementar upload individual e em lote, progresso, limites configuráveis e retomada/multipart para arquivos grandes.
- Usar armazenamento de objetos ou diretório privado fora da raiz pública; manter banco relacional para metadados. Em desenvolvimento, permitir adaptador local com o mesmo contrato.
- Armazenar com chave opaca, por exemplo projeto_uuid/ativo_uuid/versao_uuid/arquivo_uuid. Não usar nome enviado como caminho físico nem executar arquivos recebidos.
- Preservar nome original e propor nome de exportação: MSCA_CURSO_MODULO_CODIGO_TIPO_v003_20261004.ext. Validar caracteres, colisões e comprimento; não usar o nome para identificar a versão.
- Detectar MIME pelo conteúdo, aplicar lista de formatos e cotas, calcular SHA-256 no servidor, conferir bytes e verificar malware antes de disponibilizar o arquivo.
- Manter quarentena e estado recebido → verificando → disponível, com alternativas rejeitado/falhou. Falha ou indisponibilidade do scanner não liberar o arquivo silenciosamente.
- Tornar finalização idempotente; confirmar armazenamento antes de marcar arquivo disponível. Reconciliar uploads incompletos e objetos órfãos sem apagar arquivos referenciados.
- Avisar sobre duplicatas por checksum; não mesclar registros ou permissões automaticamente. Não revelar existência de arquivos de outros projetos.
- Extrair metadados e gerar miniaturas/prévias em trabalhadores isolados com limites de tempo, memória e execução. Evitar formatos ativos no navegador; sanitizar ou converter para prévia segura.
- Permitir download autorizado por streaming ou URL temporária curta; bloquear downloads em quarentena. Vincular derivados à origem e proteger ambos.

## 8. Versionamento — REQ-05

- Criar sequência única de versões por ativo em transação, com controle de concorrência e justificativa obrigatória.
- Não sobrescrever bytes de versão submetida/aprovada. Correção produzir nova versão e preservar histórico.
- Separar versão mais recente de versão aprovada; mostrar ambas claramente.
- Registrar relação de derivação e listar diferenças de metadados/arquivos; não prometer comparação visual automática de todos os formatos.
- Restaurar versão anterior criando nova versão derivada; nunca apagar o caminho intermediário.
- Usar bloqueio otimista/ETag para edições concorrentes e apresentar conflito recuperável ao usuário.

## 9. Fluxo editorial e publicação — REQ-06

Implementar transições validadas no servidor:

- Rascunho → Em produção → Em revisão.
- Em revisão → Ajustes solicitados → Em produção → Em revisão.
- Em revisão → Aprovado → Publicado.
- Publicado → Retirado, mediante justificativa e autorização.
- Permitir arquivamento administrativo conforme política, sem eliminar registros nem pressupor que a preservação foi concluída.

Registrar em cada transição usuário, instante, motivo e entrega. Vincular comentários, pareceres e aprovação ao manifesto exato da entrega. Mudança de conteúdo gerar nova entrega e nova revisão, sem invalidar retroativamente a evidência anterior; impedir publicação da entrega nova usando aprovação antiga.

Permitir revisão técnica, editorial, pedagógica, de direitos e acessibilidade conforme tipo de peça. Registrar comentários gerais e, quando cabível, marcações por página ou timecode; permitir resolução e histórico. Checklist obrigatório deve ser configurável por tipo, com justificativa para itens não aplicáveis.

Separar publicação interna de integrações externas. No núcleo, registrar canal, URL e prova de publicação; integrar Moodle, site, streaming ou outros somente quando solicitado. Impedir disponibilização pública sem aprovação, direitos válidos, integridade e condições de acessibilidade definidas. Registrar retirada, substituição e versão vigente por canal. Revogar links temporários conforme capacidade do armazenamento e declarar limite de cópias já baixadas.

## 10. Busca, acesso e reaproveitamento — REQ-07

- Implementar busca por título, código, resumo, autoria, assunto, tipo, projeto/curso, datas, estado, licença e formato; suportar acentos, paginação, ordenação e filtros combinados.
- Aplicar autorização antes de retornar resultados, facetas e totais. Revalidar acesso ao abrir ou baixar cada item.
- Implementar inicialmente busca textual do banco; introduzir mecanismo dedicado somente quando justificado por escala.
- Exibir ficha da peça com ativos, versões, prévias, relações, histórico, direitos e publicação, conforme permissão.
- Permitir localizar fontes editáveis e materiais reutilizáveis; impedir reutilização quando direitos ou prazo forem incompatíveis.
- Fazer reindexação recuperável, sem tornar o índice a fonte de verdade.

## 11. Colaboração e gestão das rotinas — REQ-08

- Implementar tarefas, responsáveis, prazos, pendências, comentários e notificações internas.
- Oferecer painel por equipe: produção, revisão, atraso, metadados incompletos, falhas de processamento e integridade.
- Disponibilizar orientações operacionais versionadas para nomenclatura, ingestão, descrição, revisão, publicação, segurança e recuperação.
- Registrar aprovação dos padrões e, quando necessário, ciência da equipe; permitir encaminhamento de incidentes ao responsável.
- Prever notificações externas por adaptador, desativadas sem configuração. Não enviar e-mails a pessoas reais durante testes.

## 12. Auditoria, proteção e governança — REQ-09

- Registrar login relevante, alterações, uploads, versões, decisões, publicação, downloads, mudanças de permissão, exportação, retirada e descarte.
- Incluir evento, usuário/serviço, instante, entidade, projeto, resultado, motivo e correlation_id; minimizar dados pessoais e nunca registrar senha, token ou URL assinada completa.
- Impedir edição de eventos pela interface/API comum. Para produção, proteger trilha em destino separado ou mecanismo de integridade com permissões restritas; declarar que administradores do banco exigem controles adicionais.
- Implementar autenticação segura, revogação de sessão, TLS em produção, validação de entrada, consultas parametrizadas, prevenção de XSS/CSRF conforme arquitetura, rate limiting e segredos fora do código.
- Permitir autenticação institucional por adaptador OIDC/SAML quando disponível. Na opção local, usar hash de senha apropriado e recuperação segura; exigir MFA para perfis privilegiados quando a infraestrutura suportar.
- Implementar retenção configurável de conteúdos, logs e dados pessoais; registrar autorizações de imagem/voz com acesso restrito, sem expor documentos no catálogo.
- Tratar solicitações de restrição, retirada e eliminação por fluxo auditado. Observar a política institucional aplicável e submeter decisões legais aos responsáveis; não assumir prazo universal nem acesso aberto para todo acervo.

## 13. Preservação, backup e recuperação — REQ-10

- Preservar originais, versões aprovadas, manifestos, direitos e eventos; planejar formatos preferenciais e migração sem destruir origem.
- Calcular checksum na ingestão, revalidar periodicamente em agenda configurável, registrar resultados e alertar sobre divergências. Isolar arquivo suspeito e recuperar de cópia verificada com evento auditado.
- Implementar backup coordenado de banco, objetos, configurações e informações necessárias para recuperar chaves; manter cópias independentes, ao menos uma isolada/imutável conforme infraestrutura.
- Documentar procedimento de restauração consistente entre banco e objetos. Testar recuperação em ambiente separado e verificar checksums, relações e permissões.
- Definir RPO/RTO com a coordenação. Como hipótese inicial de planejamento, usar RPO de 24 horas e RTO de 8 horas, sem afirmar garantia até medição.
- Distinguir backup, replicação, arquivamento e preservação. Um job concluído não comprova restauração nem preservação de longo prazo.
- Implementar descarte em duas etapas, com justificativa, prazo, aprovação e impedimentos de descarte. Manter tombstone e evento conforme política. Não remover bytes compartilhados por versões/peças ativas.

## 14. Modelo relacional mínimo — REQ-11

Criar migrações, chaves estrangeiras e índices para:

| Entidade | Relações e invariantes |
| --- | --- |
| users, teams, roles, permissions, memberships | Permissões com escopo de projeto |
| programs, projects, courses, modules, collections | Hierarquia explícita e associação de peças |
| pieces, piece_collections, piece_courses, contributors | Código estável, autoria com papel e vínculos N:N |
| assets, piece_assets | Ativos compartilháveis, papel e ordem na peça |
| asset_versions | Ativo, sequência única, versão anterior e autor |
| files, version_files, renditions | Bytes imutáveis, composição da versão e derivação |
| metadata_revisions, vocabularies, terms, piece_terms | Histórico descritivo e classificação |
| deliveries, delivery_items | Manifesto imutável de versões para revisão |
| reviews, approvals, workflow_events | Decisão vinculada à entrega, usuário e etapa |
| publications | Entrega aprovada, canal e histórico |
| rights, authorizations | Escopo de peça/ativo, condição, prazo e evidência privada |
| tasks, comments, notifications | Rotina da equipe e referências autorizadas |
| audit_events, preservation_events | Proveniência e resultados de integridade |
| retention_rules, holds, disposal_requests | Retenção e controle de descarte |
| jobs, upload_sessions, import_batches | Processamento idempotente e recuperação |

Usar UUIDs ou IDs estáveis, timestamps, integridade referencial e transações. Restringir exclusão em cascata de registros editoriais, arquivos e evidências. Definir unicidade de códigos por projeto, sequência por ativo e identificadores de importação. Não usar JSON sem validação para substituir relações essenciais. Derivar aprovado de decisão válida, não de booleano editável.

## 15. API, importação e interoperabilidade — REQ-12

- Expor API versionada com documentação OpenAPI, autenticação, autorização, paginação, filtros, erros consistentes e limites.
- Cobrir peças, ativos, versões, uploads/finalização, entregas, revisão, aprovação, publicação, busca, vocabulários, usuários e auditoria; não expor mutação de eventos imutáveis.
- Exigir idempotency key para operações suscetíveis a repetição; tratar concorrência com transação e controle de versão.
- Importar CSV/JSON e arquivos por lote com simulação, validação, relatório por item, mapeamento de campos e retomada. Impedir duplicatas silenciosas.
- Exportar metadados em CSV/JSON e pacote ZIP com manifesto, IDs, relações, versões selecionadas e checksums; aplicar permissões e evitar caminhos inseguros e fórmulas executáveis em CSV.
- Mapear campos descritivos para Dublin Core quando houver demanda de intercâmbio. Usar conceitos de proveniência e eventos de preservação compatíveis com PREMIS como orientação, sem declarar certificação.
- Tratar OAI-PMH, DOI, IIIF, OCR, transcrição automática, busca semântica e IA como extensões futuras sob demanda. Não enviar arquivos a serviços de IA externos por padrão.

## 16. Interface e requisitos não funcionais — REQ-13

- Usar português brasileiro, design responsivo, navegação por teclado, rótulos de campos, foco visível, mensagens acionáveis e contraste adequado.
- Cobrir painel, catálogo, ficha da peça, ingestão em lote, histórico de versões, revisão, gestão de tarefas, vocabulários, administração e auditoria.
- Mostrar estados vazios, progresso, falhas e possibilidade de tentar novamente; não apresentar sucesso antes de confirmação no servidor.
- Oferecer legenda/transcrição e texto alternativo conforme formato. Planejar verificação de acessibilidade baseada em WCAG 2.2 AA; não declarar conformidade apenas por ferramentas automáticas.
- Processar tarefas pesadas em fila com retry limitado, backoff, deduplicação, timeout e fila de falhas. Garantir recuperação após reinício e prioridade configurável.
- Implementar health checks, métricas de fila, armazenamento, erros e backup, com alertas e logs estruturados.
- Definir limites configuráveis para upload, cota e concorrência. Como cenário inicial proposto, verificar busca com 100 mil peças e 20 usuários concorrentes, p95 até 2 segundos; ajustar a meta à infraestrutura e registrar massa, hardware e medição.
- Evitar carregar vídeo completo em memória; suportar entrega por intervalos quando necessária. Separar custo de processamento de prévias do tempo de ingestão.

## 17. Implementação e entregáveis — REQ-14

Em projeto novo, preferir monólito modular com banco relacional, armazenamento privado e trabalhador assíncrono; separar módulos de catálogo, ingestão, editorial, acesso e preservação. Escolher linguagem e framework conforme manutenção pela equipe e infraestrutura. Não tornar Kubernetes, microsserviços ou Elasticsearch requisitos obrigatórios.

Entregar código, migrações, dados sintéticos, .env.example sem segredos, configuração de desenvolvimento reproduzível, testes, documentação de API e instruções de instalação/operação. Documentar rotinas da equipe, backup/restauração, recuperação de falhas e matriz de permissões. Fixar dependências e excluir arquivos reais, segredos e uploads do Git.

Executar em etapas:

1. Fundação: modelo, autenticação, permissões, projetos, peças e vocabulários.
2. Ingestão: armazenamento privado, verificação, metadados, versões e prévias.
3. Produção: entregas, revisão, aprovação, tarefas e publicação registrada.
4. Recuperação: busca, filtros, download e exportação autorizados.
5. Operação: auditoria, integridade, backup, restauração, retenção e documentação.

Manter todas as etapas como escopo obrigatório do núcleo; marcar funcionalidades futuras separadamente. Nunca publicar uma versão operacional com autorização, integridade ou recuperação essenciais apenas simuladas.

## 18. Critérios de aceite e verificação

Criar testes de integração e ponta a ponta que comprovem:

- **AC-01:** produtor cadastrar peça, enviar roteiro e vídeo e recuperar suas relações e metadados.
- **AC-02:** segunda versão manter primeira intacta; download de cada uma conferir SHA-256.
- **AC-03:** revisão rejeitada permitir ajustes; entrega modificada exigir nova aprovação; aprovação concorrente não publicar manifesto diferente.
- **AC-04:** usuário de outro projeto não acessar peça, arquivo, prévia, faceta, exportação ou URL temporária; testar também ID direto na API.
- **AC-05:** arquivo malicioso, MIME incompatível e falha do scanner permanecerem indisponíveis; testar limites e tentativa de path traversal.
- **AC-06:** interrupção/repetição de upload não criar versões duplicadas nem marcar arquivo ausente como disponível.
- **AC-07:** conflito de edição preservar alterações e exigir resolução explícita.
- **AC-08:** publicação sem licença/autorização necessária ser bloqueada; retirada preservar histórico e interromper acesso futuro controlado pelo sistema.
- **AC-09:** exportação/importação validar manifesto e checksums; importar lote repetido sem duplicação silenciosa.
- **AC-10:** alteração de metadados, permissão e aprovação produzir trilha atribuível que usuário comum não consiga editar.
- **AC-11:** corrupção deliberada de arquivo de teste gerar alerta; restauração recuperar bytes corretos e registrar evento.
- **AC-12:** restauração de backup em ambiente limpo recuperar banco, arquivos, relações, permissões e versões; medir RPO/RTO observado.
- **AC-13:** descarte com impedimento ativo ou referência compartilhada ser bloqueado; cumprir política e registrar decisão.
- **AC-14:** usuário realizar busca, upload e revisão por teclado; conferir rótulos, foco, mensagens e acessibilidade das prévias.
- **AC-15:** executar carga do cenário pactuado, documentar latência e falhas e verificar recuperação das tarefas após reinício.

Antes de concluir incremento, executar verificações apropriadas e atualizar matriz de requisitos. Informar o que funciona, o que foi testado, limitações e pendências. Não chamar protótipo de repositório pronto para produção.

## 19. Uso no VS Code com Codex

Salvar este arquivo como `.agents/skills/criar-mosaico/SKILL.md` dentro da raiz do projeto. Manter o frontmatter e o nome SKILL.md exatamente como fornecidos. A pasta pode conter somente este arquivo; agentes/openai.yaml é opcional.

Abrir a raiz do projeto no VS Code e, no painel do Codex, invocar:

> Use $criar-mosaico para implementar o MOSAICO. Inspecione o projeto, registre o modelo de dados e a matriz de requisitos e implemente a primeira etapa com testes. Preserve a arquitetura existente e indique hipóteses e pendências.

Se o agente não listar a skill, instruir explicitamente a leitura de `.agents/skills/criar-mosaico/SKILL.md`. Para usar outra extensão de IA, informar esse caminho diretamente e verificar a forma de descoberta suportada por ela; salvar um arquivo não significa que toda extensão o executará automaticamente.

Versionar a pasta de skill no Git do projeto. Usar AGENTS.md na raiz para regras permanentes da equipe, comandos de execução e referência a esta skill; não copiar requisitos inteiros em vários arquivos. Esta skill orienta o agente de programação e não é um plugin executado pelo repositório MOSAICO.
