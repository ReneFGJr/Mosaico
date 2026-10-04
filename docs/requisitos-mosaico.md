# Matriz de requisitos do MOSAICO

Escopo de produto proposto a partir da skill MOSAICO e das competências do Edital nº 03/2026 do Mais Saúde com Agente. O edital não determina arquitetura, tecnologia, banco ou SLA.

Prioridades: `P0` fundamento para a primeira etapa; `P1` núcleo das etapas seguintes; `P2` extensão sob demanda. Status: `Implementado`, `Parcial` ou `Planejado`.

| ID | Prioridade | Requisito resumido | Status nesta entrega | Evidência/teste | Pendências |
| --- | --- | --- | --- | --- | --- |
| REQ-01 | P0 | Perfis, permissões por ação/projeto, negação padrão e auditoria | Parcial | `MosaicoFoundationTest`: concessão do produtor e negações por permissão/projeto/usuário | Login, sessão segura, middleware/filtros em cada endpoint, administração de usuários/perfis, auditoria e prevenção de autoaprovação. |
| REQ-02 | P0 | Projetos, peças, coleções e organização por relações | Parcial | Teste de identidade UUID, código único no projeto e vínculo de coleção planejado no schema | Hierarquia completa, múltiplos cursos, relações entre peças, responsáveis e prevenção de ciclos. |
| REQ-03 | P0 | Metadados essenciais, vocabulários e qualidade | Parcial | Teste de vocabulário, termo e classificação da peça | Dicionário completo, direitos, acessibilidade, metadados técnicos, revisões históricas e validação por etapa. |
| REQ-04 | P1 | Ingestão privada, validação, checksum, quarentena e prévias | Planejado | Sem teste nesta etapa | Adaptador de armazenamento, scanner, cotas, upload retomável e processamento isolado. |
| REQ-05 | P1 | Versões imutáveis, concorrência e proveniência | Planejado | Sem teste nesta etapa | Ativos, versões, arquivos, sequência transacional e resolução de conflito. |
| REQ-06 | P1 | Entregas, revisão, aprovação e publicação registrada | Planejado | Sem teste nesta etapa | Workflow validado, manifesto imutável, pareceres, direitos e canais de publicação. |
| REQ-07 | P1 | Busca textual autorizada, filtros, ficha e reaproveitamento | Planejado | Sem teste nesta etapa | Busca paginada, autorização antes de facetas/totais, download e regras de direitos. |
| REQ-08 | P1 | Tarefas, comentários, notificações e rotinas de equipe | Planejado | Sem teste nesta etapa | Painéis, tarefas e adaptadores de notificação desativados por padrão. |
| REQ-09 | P1 | Auditoria, autenticação segura, proteção e governança | Planejado | Hash de senha tem coluna reservada, sem fluxo de autenticação implementado | OIDC/SAML ou login local, MFA conforme suporte, trilha protegida, retenção e resposta a incidentes. |
| REQ-10 | P1 | Integridade, backup, restauração, retenção e descarte | Planejado | Sem teste nesta etapa | Rotinas verificáveis, cópias independentes, teste de restauração, política, RPO/RTO pactuados. |
| REQ-11 | P0 | Modelo relacional com chaves, índices e integridade | Parcial | Migration `CreateMosaicoFoundation` executada em SQLite nos testes | Completar entidades previstas no modelo de dados, revisar dialetos e testar migration no banco operacional. |
| REQ-12 | P2 | API versionada, OpenAPI, importação e exportação | Planejado | Sem teste nesta etapa | Definição dos consumidores e formatos de interoperabilidade. |
| REQ-13 | P1 | Interface acessível/responsiva, operação e desempenho | Planejado | Sem teste nesta etapa | Fluxos UI, WCAG 2.2 AA, filas, health checks, métricas e carga pactuada. |
| REQ-14 | P0 | Incrementos, documentação, configuração e testes reproduzíveis | Parcial | PHPUnit existente e testes de integração da fundação | CI, `.env.example` validado em instalação limpa, documentação operacional/API e testes ponta a ponta. |

## Hipóteses e pendências institucionais

- Banco de produção e versão ainda não informados; os testes usam SQLite em memória e a configuração existente indica MySQLi como padrão.
- Volume, tamanho máximo de arquivo, usuários simultâneos, infraestrutura e público do catálogo precisam ser pactuados.
- Autenticação institucional (OIDC/SAML), MFA, política de perfis globais e responsáveis pela administração permanecem indefinidos.
- Definir política de licença, retenção, acesso público/restrito, autorização de imagem/voz e requisitos de acessibilidade por tipo.
- Definir RPO/RTO com a coordenação. RPO de 24 h e RTO de 8 h são apenas hipóteses de planejamento da skill, não garantias.
- O cenário de 100 mil peças, 20 usuários e p95 de 2 s depende de massa, infraestrutura e critério de medição a pactuar.
- Esta entrega não importa conteúdo real, publica materiais, configura armazenamento de produção ou comprova segurança, preservação, acessibilidade ou conformidade normativa.