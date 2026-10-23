# ORBITA engineering rules

- Production catalogue DDL and existing data are immutable during migration. Never run legacy migrations, migrate:fresh, seeds, old import journals, price replacements, or bulk media deletion.
- Canonical service migrations live in `ms/orbita-db/database/migrations`; they only target `orbita_` tables, including `orbita_migrations`. Migrations and recovery imports run only from the standalone local Laravel service ms/orbita-db via the SSH tunnel described in Development/VPS/18-local-database.md, never from API or Docker images. orbita-db has no Dockerfile/image; it owns its Composer runtime, private local connection settings and recovery commands. API contains no canonical migrations and performs read-only release readiness checks.
- Keep catalogue writes disabled until an exported schema has been inspected, approved fingerprint matches live metadata, and every writer uses the common allocator. Never weaken the gate to make a test or demo pass.
- Project key is 1, established on the server. Do not accept client database/connection names, morph aliases, or tenant keys as authority.
- New workers and old importers share global per-table IDs. Rollback does not remove service tables or restore an incompatible writer.
- Sessions belong to each request; never store an SSR user's identity in module-global Svelte state. No credentials or secret tokens in browser variables.
- Use Decimal strings and integer minor units for money. Active legacy prices are authoritative; promotional static copy does not set purchase price.
- Public queries use GET. GraphQL mutation, checkout, uploads and auth retain CSRF validation. Webhook exemption is limited to the provider-verified route.
- Checkout and notification outbox commit together. Redirect from payment is not proof of payment; verify against the authenticated provider API.
- Preserve the incumbent ORBITA design, content and URLs. Screenshots of desktop and mobile accompany visual review; no unrelated rebrand.
- Dependencies use stable compatible releases and committed lockfiles. Run typechecks, builds, PHP tests, browser scenarios and schema/migration guards appropriate to changes.
- Production DB, SMTP, S3 and payment providers are not test targets. Tests use synthetic fixtures and fake adapters.

- Each service builds from its own `ms/orbita-*` directory with its own dependency manifest and lockfile; no root npm workspace. Front/admin local packages are versioned snapshots. Keep shared contract changes consistent in both services; generate types with `npm --prefix scripts run generate:types`. Local Compose/.env belong in docker/local/; the standalone migration runner has private ms/orbita-db/.env for the VPS tunnel and optional .env.local for the local test DB. Use scripts/local.sh for local containers; keep the repository root free of environment files, production configuration belongs in `prod/<service>`.

## Production Laravel (22.10.2026)

Миграции запускаются только PHP с локальной машины через SSH-туннель, не на VPS и не в Docker-образе. Секреты — в серверном .env, публичный CA cert/mysql-ca.pem включён в образ. Не генерировать APP_KEY/JWT при старте и не подставлять имена БД/пользователей другого проекта.
