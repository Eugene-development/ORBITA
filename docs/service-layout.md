# Самостоятельные сборки ORBITA

Соглашения сверены с LEGET, OTZOVIK и PLITKA: исходники и Dockerfile находятся в `ms/<service>`, оболочки сборки — в `deploy/`, готовые конфигурации VPS — в `prod/<service>`.

- `ms/orbita-front` и `ms/orbita-admin`: собственные package.json, package-lock.json, node_modules, Dockerfile.production и .dockerignore. Для сборки достаточно содержимого папки выбранного сервиса: `npm ci && npm run build` или `docker build -f Dockerfile.production .`.
- `ms/orbita-api`: собственные Composer-зависимости и Dockerfile.production.
- `ms/orbita-db`: Dockerfile.production и служебные миграции. Образ наследует API; deploy/db.sh сначала собирает его, как и прежде.
- `scripts/package.json`: отдельный пакет инструментов генерации контрактов и сквозных тестов. Он не нужен для сборки приложений. Установка: `npm --prefix scripts ci`.
- `docker/local/`: локальные `compose.yaml`, `.env` и `.env.example`. Команды выполняются через `bash scripts/local.sh …` из корня (или по абсолютному пути из любого каталога). Production использует отдельные настройки в prod.

UI и контракты включены в каждый Node-сервис как локальные пакеты `file:packages/...`. Это исходные снимки, а не ссылки на соседний сервис. При изменении общего API обновляйте оба снимка контрактов; команда `npm --prefix scripts run generate:types` генерирует типы сразу для обоих потребителей. Изменения общих UI-токенов переносите в оба сервиса явно.

Проверки: `bash scripts/check.sh`, `npm --prefix ms/orbita-front test`, `python3 scripts/test-deployment.py`, `npm --prefix scripts run test:e2e`. Браузерные тесты используют синтетическую SQLite; production-БД не участвует.

Старые корневые npm-файлы, пакеты и результаты тестов сохранены локально в игнорируемой папке `runtime/pre-service-layout/` для восстановления. Текущие сборки их не используют.
