# Modelo de dados do MOSAICO

O modelo segue o CodeIgniter 4 existente, com banco relacional e migrations da aplicação. UUIDs identificam registros sem depender de títulos ou caminhos. Relações de domínio usam chaves estrangeiras; exclusão em cascata fica limitada a associações não editoriais.

## Implementado na fundação

| Entidade | Relações e garantias |
| --- | --- |
| `users` | E-mail único, nome, hash de senha e indicador de conta ativa. Autenticação e ciclo de sessão ainda não estão conectados. |
| `roles`, `permissions`, `role_permissions` | Catálogo inicial de perfis e permissões; concessões via associação muitos-para-muitos. |
| `projects` | Código único, nome, descrição e situação. |
| `memberships` | Usuário, perfil e projeto; a autorização consulta sempre o projeto e nega por padrão. |
| `collections` | Coleção com código único dentro do projeto. |
| `pieces` | Identidade estável, código único por projeto, metadados iniciais, estado editorial e visibilidade independentes. |
| `piece_collections` | Associação N:N de peças e coleções. |
| `vocabularies`, `terms` | Vocabulários e termos com identificadores estáveis, hierarquia e substituição sem apagar termos desativados. |
| `piece_terms` | Classificação N:N de peças com termos controlados. |

## Modelo previsto para as próximas etapas

| Grupo | Entidades e vínculos principais |
| --- | --- |
| Organização | `programs`, `courses`, `modules`, `teams`, `contributors`; projetos, cursos, coleções e peças com relações explícitas N:N quando aplicável. |
| Ativos | `assets`, `piece_assets`, `asset_versions`, `files`, `version_files`, `renditions`; versões e bytes imutáveis, checksum SHA-256, armazenamento privado e relações de derivação. |
| Metadados | `metadata_revisions`, sinônimos e identificadores de termos; histórico de alterações atribuível e extensão tipada por formato. |
| Editorial | `deliveries`, `delivery_items`, `reviews`, `approvals`, `workflow_events`; manifesto exato e decisões ligadas à entrega. |
| Direitos e publicação | `rights`, `authorizations`, `publications`; prazo, licença, evidências privadas, canal e histórico de retirada/substituição. |
| Equipe | `tasks`, `comments`, `notifications`; tarefas e comunicação com autorização por projeto. |
| Governança | `audit_events`, `preservation_events`, `retention_rules`, `holds`, `disposal_requests`; trilha atribuível e descarte controlado. |
| Processamento | `jobs`, `upload_sessions`, `import_batches`; idempotência, retry e recuperação de operações interrompidas. |

O esquema atual é apenas a fundação da etapa 1; não representa o repositório pronto para ingestão ou publicação. Não há ainda arquivo binário, versionamento, manifesto de entrega, publicação, retenção ou evento de preservação.