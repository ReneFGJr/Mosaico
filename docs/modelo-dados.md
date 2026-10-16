# Modelo de dados do MOSAICO

O modelo segue o CodeIgniter 4 existente, com banco relacional e migrations da aplicação. UUIDs identificam registros sem depender de títulos ou caminhos. Relações de domínio usam chaves estrangeiras; exclusão em cascata fica limitada a associações não editoriais.

## Estrutura canônica

```text
Comunidade (table: projects)
└── Coleções (table: collections)
    └── Peças (table: pieces)
```

O nome `projects` é mantido internamente por compatibilidade, mas representa a entidade funcional **Comunidade**. Coleções referenciam a comunidade por `collections.project_id`; peças referenciam a comunidade por `pieces.project_id` e se vinculam às coleções por `piece_collections`.

## Implementado na fundação

| Entidade | Relações e garantias |
| --- | --- |
| `users` | E-mail único, nome, hash de senha e indicador de conta ativa. Autenticação e ciclo de sessão ainda não estão conectados. |
| `roles`, `permissions`, `role_permissions` | Catálogo inicial de perfis e permissões; concessões via associação muitos-para-muitos. |
| `projects` | Armazenamento legado das comunidades; código único, nome, descrição e situação. |
| `memberships` | Usuário, perfil e comunidade; a autorização consulta sempre a comunidade e nega por padrão. |
| `collections` | Coleção com código único dentro da comunidade e imagem representativa opcional armazenada fora da raiz pública. |
| `pieces` | Identidade estável, código único por comunidade, metadados iniciais, estado editorial e visibilidade independentes. |
| `piece_collections` | Associação N:N de peças e coleções. |
| `vocabularies`, `terms` | Vocabulários pertencem a uma comunidade (`project_id`); os termos mantêm identificadores estáveis, hierarquia e substituição sem apagar termos desativados. Categorias e palavras-chave não são compartilhadas entre comunidades. |
| `piece_terms` | Classificação N:N de peças com termos controlados. |

## Modelo previsto para as próximas etapas

| Grupo | Entidades e vínculos principais |
| --- | --- |
| Organização | `programs`, `courses`, `modules`, `teams`, `contributors`; comunidades, cursos, coleções e peças com relações explícitas N:N quando aplicável. |
| Ativos | `assets`, `piece_assets`, `asset_versions`, `files`, `version_files`, `renditions`; versões e bytes imutáveis, checksum SHA-256, armazenamento privado e relações de derivação. |
| Metadados | `metadata_revisions`, sinônimos e identificadores de termos; histórico de alterações atribuível e extensão tipada por formato. |
| Editorial | `deliveries`, `delivery_items`, `reviews`, `approvals`, `workflow_events`; manifesto exato e decisões ligadas à entrega. |
| Direitos e publicação | `rights`, `authorizations`, `publications`; prazo, licença, evidências privadas, canal e histórico de retirada/substituição. |
| Equipe | `tasks`, `comments`, `notifications`; tarefas e comunicação com autorização por comunidade. |
| Governança | `audit_events`, `preservation_events`, `retention_rules`, `holds`, `disposal_requests`; trilha atribuível e descarte controlado. |
| Processamento | `jobs`, `upload_sessions`, `import_batches`; idempotência, retry e recuperação de operações interrompidas. |

O esquema atual é apenas a fundação da etapa 1; não representa o repositório pronto para ingestão ou publicação. Não há ainda arquivo binário, versionamento, manifesto de entrega, publicação, retenção ou evento de preservação.
