# ORBITA

Новая витрина и админка на Svelte 5 / SvelteKit 3 / Tailwind 4, единый Laravel 13 / Lighthouse API. Основной домен — **орбита-строй.рф**.

## Структура

`ms/orbita-front` — публичный сайт; `ms/orbita-admin` — `/admin`; `ms/orbita-api` — модульный монолит. `ms/orbita-db` содержит только снимок каталога и служебные миграции. Каждый Node-сервис содержит собственные `package.json`, `package-lock.json`, `Dockerfile.production` и локальные пакеты `packages/ui`, `packages/contracts`. Сборка не требует корневого npm workspace или исходников соседнего сервиса. `scripts/package.json` содержит только инструменты генерации и сквозных тестов. Корневые `.env` и `compose.yaml` обслуживают совместное локальное окружение, как в OTZOVIK и PLITKA; production-настройки находятся в `prod/<сервис>/`. Инфраструктура повторяет поддерживаемые соглашения PLITKA.

## Локальный запуск

Требуются Docker Compose, Node 24 LTS, PHP 8.4 и Composer для проверок без Docker.

```sh
bash scripts/setup.sh
bash scripts/build.sh local
docker compose up -d --wait mysql redis mailpit
bash scripts/migrate-services.sh local
docker compose up -d --wait
```

После служебных миграций назначить сотрудника (пароль запрашивается скрыто):

```sh
docker compose exec api php artisan orbita:employee admin@example.test --name='Администратор' --role=admin
```

Сайт: `http://127.0.0.1:4151`, админка: `/admin`, локальные письма: `http://127.0.0.1:4154`.

Начальный каталог не создаётся автоматически. После получения SQL-экспорта:

```sh
bash scripts/import-local.sh /absolute/path/catalog-tables.sql
bash scripts/check-schema.sh
```

Импортёр принимает только определения таблиц. Полный экспорт с views/triggers хранится отдельно; эти объекты проверяются и восстанавливаются в клоне до подтверждения совместимости — см. [database-contract.md](docs/database-contract.md).

Синтетические данные существуют только в тестах. Для демонстрации витрины без API можно явно включить `ORBITA_DEMO=true` в локальной конфигурации; в production этот режим запрещён.

## Проверки

```sh
npm --prefix ms/orbita-front ci
npm --prefix ms/orbita-admin ci
npm --prefix scripts ci
bash scripts/check.sh
composer install --working-dir=ms/orbita-api
composer test --working-dir=ms/orbita-api
npm --prefix scripts run generate:types
npm --prefix scripts run test:e2e
```

Автоматические PHP-тесты используют отдельную синтетическую SQLite схему. Изолированный MySQL-профиль также использует синтетическую схему. Проверка совместимости с настоящей схемой выполняется после получения экспорта: см. [verification.md](docs/verification.md).

## До подключения production

Запись в каталог выключена. Пользователь должен предоставить экспорт схемы и согласовать переключение всех старых writers на общий allocator. До этого код не утверждает, что реальные связи и SQL-типы подтверждены.

Production использует существующую внешнюю БД; MySQL-контейнер отсутствует. Подробности: [operations.md](docs/operations.md), [database-contract.md](docs/database-contract.md), [contracts.md](docs/contracts.md).

Выполненные проверки и условия production-подключения: [implementation-status.md](docs/implementation-status.md).

Папки серверной поставки и сборка образов: [prod/README.md](prod/README.md).
