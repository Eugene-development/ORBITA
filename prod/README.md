# ORBITA — самостоятельные папки для VPS

Обновлено 18.10.2026. Общий порядок подготовки сервера, доставки, MySQL, DNS,
HTTPS и восстановления — [Development/VPS](../../VPS/README.md). Здесь только
особенности приложения. Требуется Docker Compose v2.24+ (`env_file.required`).

| Папка | Состав | Порт VPS |
| --- | --- | --- |
| `orbita-front` | Витрина SvelteKit + nginx | `127.0.0.1:4151` |
| `orbita-admin` | Админка `/admin/`, SvelteKit + nginx | `127.0.0.1:4152` |
| `orbita-api` | Laravel, авторизация, Redis, outbox-worker, scheduler, инструмент миграций | `127.0.0.1:7151` |
| `nginx` | Внешний nginx `orbita.conf` | HTTP/HTTPS сайта |

Каждая папка запускается после распаковки без исходников репозитория. Общего Compose,
gateway, MySQL и отдельного auth здесь нет. API использует существующую внешнюю БД.
Node/PHP работают с read-only filesystem и прежними ограничениями; только nginx
публикует loopback-порты. Локальный gateway сохранён отдельно в `docker/nginx/local.conf`.

## Сборка

Фиксированные имена: `larux/orbita-front:latest`, `larux/orbita-admin:latest`,
`larux/orbita-api:latest`, `larux/orbita-db:latest`. Имена и теги в `.env` не задаются.

`deploy/front.sh`, `deploy/admin.sh`, `deploy/api.sh`, `deploy/db.sh` собирают свой
сервис из собственного контекста `ms/orbita-<сервис>` и его `Dockerfile.production`. `scripts/build.sh` собирает весь комплект. После успешной сборки образы автоматически отправляются в Docker Hub.
Для сборки без отправки используйте `--no-push`; прежний `--push` также поддерживается.
Нужен `docker login` под аккаунтом с правом публикации в `larux`:

```sh
docker login
./scripts/build.sh
# Только витрина:
./deploy/front.sh
# Только локальная сборка витрины:
./deploy/front.sh --no-push
```

Сборка использует встроенный Docker builder текущего context (`--builder "$(docker context show)"`) и явно загружает образ (`--load`) перед `docker push`; выбранный пользовательский buildx-builder не влияет на этот сценарий.
Сборка рассчитана на `linux/amd64`; при необходимости задайте `PLATFORM`.
Образ db основан на `larux/orbita-api:latest`. При отдельном `deploy/db.sh`
API сначала собирается из текущих исходников локально, затем собирается db;
публикуется только выбранный db. Полная сборка использует уже собранный API.
Он содержит только canonical migrations из `ms/orbita-db/database/migrations` в
`/migrations`. Legacy recovery, seed и импорт в него не включены.
Для локальной разработки используйте `scripts/build.sh local`.

## Настройки

В каждой папке скопируйте `.env.example` в `.env` **рядом с `docker-compose.yml`,
не в `scripts/`** и задайте права 600. Во front/admin находятся только публичные
настройки; backend-секреты им не передаются.

В API заполните постоянный APP_KEY, параметры существующей внешней БД, SMTP и
нужных провайдеров. Для новой установки ключ: `base64:` + результат
`openssl rand -base64 32`. При переносе сохраняйте старый ключ.
`DB_NETWORK` — существующая Docker-сеть с внешним MySQL, по умолчанию `leget-db`.
DB_HOST=mysql соответствует [общему MySQL VPS](../../VPS/12-mysql-server.md)
и имени в его TLS-сертификате. Этот alias разрешён только при явно заполненном
DB_NETWORK; сама сеть остаётся external в Compose. Loopback и имя `db` не допускаются.
При другом существующем сервере задайте его фактическое сетевое имя и CA;
имя обязано совпадать с сертификатом. Не создавайте параллельную БД и не
переименовывайте действующую инфраструктуру.

Скопируйте публичный CA действующего MySQL в `orbita-api/cert/mysql-ca.pem`.
Compose монтирует его в `/run/certs/mysql-ca.pem` для API, worker, scheduler и db;
Laravel проверяет CA и имя сервера. MYSQL_ATTR_SSL_CA уже указан в `.env.example`.
Приватные ключи MySQL в поставку не входят. До запуска убедитесь, что CA читается UID 82.

Подсеть API `172.30.94.0/24` должна быть свободна. При изменении обновите и Compose,
и `TRUSTED_PROXIES`. Redis внутри API доступен как `redis`; его volume сохраняет
имя прежней поставки `orbita-production_redis_data`.

Основной домен — орбита-строй.рф (`xn----7sbc2ahzelejid.xn--p1ai`). SSR front/admin
обращается к API через публичный HTTPS этого сайта, поэтому DNS, сертификат и
доступность домена с VPS обязательны. При смене домена согласуйте `.env` и nginx.

## Подготовка БД и запуск

Проверки fingerprint, совместимости metadata, координации writers и запреты записи
в каталог сохранены. Перестройка поставки не согласует схему и не включает CRUD,
оплаты, загрузки или demo. Не запускайте legacy/recovery миграции на рабочей БД.

После подтверждённого экспорта, проверки metadata и резервной копии существующей
БД подготовьте отдельного SQL-пользователя миграций по [VPS/16](../../VPS/16-project-database.md).
В `orbita-api` скопируйте `.env.migrate.example` в `.env.migrate`, задайте только
DB_USERNAME и DB_PASSWORD этого аккаунта, права 600. Runtime `.env` остаётся
с отдельным пользователем без DDL. Мигратор наследует схему, TLS и APP_KEY из `.env`;
доступы из `.env.migrate` получает только CLI-сервис db. Start/update используют
этот файл, если он установлен, только для проверки состояния миграций.

Затем выполните отдельный шаг служебных миграций из `/root/Project/ORBITA`:

```sh
./orbita-api/scripts/migrate.sh
./orbita-api/scripts/start.sh
./orbita-front/scripts/start.sh
./orbita-admin/scripts/start.sh
```

`migrate.sh` запускает Redis, проверяет fingerprint до и после миграций; изменяются
только таблицы `orbita_*`. При несовпадении запуск останавливается. Для несовместимых
изменений заранее остановите приложения и всех писателей. В production должны
использоваться заранее проверенные образы. Start/update дополнительно проверяют
metadata и отсутствие pending service migrations; сами миграции они не выполняют.

Установите `nginx/orbita.conf` в `/etc/nginx/sites-available/orbita`, включите через
`sites-enabled`, выполните `nginx -t` и reload. Затем включите HTTPS:

```sh
certbot --nginx -d xn----7sbc2ahzelejid.xn--p1ai --redirect
```

Сохраняйте добавленные Certbot TLS-настройки при следующих обновлениях.
Маршруты `/auth/`, `/graphql`, `/api/`, `/media/`, `/admin/` сохранены, API readiness
доступен через `/health/api`. Проверьте также `/health` и `/admin/health`.

## Обновление, остановка и резервные копии

В каждой папке есть `scripts/start.sh`, `scripts/update.sh`, `scripts/stop.sh`.
Скрипты работают из любого каталога, явно выбирают свою `.env`, Compose и project name;
унаследованные COMPOSE_PROFILES не включают мигратор при обычном up.
Update скачивает и пересоздаёт только сервисы
своей папки; API также проверяет metadata и миграции. Stop не удаляет volumes.
При переходе сначала остановите старую поставку, чтобы освободить порты.

До релиза сохраняйте БД, APP_KEY, настройки провайдеров, Redis и прежние образы.
`latest` не хранит историю релизов: для отката сохраните прежний образ/digest заранее.
Служебные миграции не откатываются автоматически; старые несовместимые writers
возобновлять нельзя. Расширения схемы и allocator сохраняются при откате приложения.

В репозитории остаётся `scripts/backup.sh production`: он использует только DB-поля
из `prod/orbita-api/.env`, создаёт приватный SQL gzip и не выполняет миграций.
Клиент монтирует тот же публичный CA и использует `--ssl-mode=VERIFY_IDENTITY`.
`MYSQL_BACKUP_IMAGE` выбирает совместимый клиент существующего MySQL. Восстановление
проверяйте на отдельной БД. Для обычного запуска этот скрипт не вызывается.

## Архивирование на Mac

Из корня репозитория:

```sh
for service in front admin api; do
  COPYFILE_DISABLE=1 tar --exclude=.env --exclude=.env.migrate --exclude=.DS_Store --exclude='._*' \
    -czf "/tmp/orbita-${service}.tar.gz" -C prod "orbita-${service}"
done
COPYFILE_DISABLE=1 tar --exclude=.DS_Store --exclude='._*' \
  -czf /tmp/orbita-nginx.tar.gz -C prod nginx
```

Архивы примера содержат конфигурацию, CA и скрипты; образы, данные и `.env`/`.env.migrate`
в них не входят. Закрытые настройки доставляйте отдельно либо упакуйте целую
готовую поставку по [VPS/02](../../VPS/02-delivery.md). Сохраняйте действующие
серверные настройки и TLS-конфиги при обновлении. Статические проверки: `python3 scripts/test-infra.py`,
сценарии команд и разделение credentials: `python3 scripts/test-deployment.py`.
