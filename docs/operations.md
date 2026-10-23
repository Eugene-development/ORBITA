# Запуск и выпуск

Локальная Compose среда имеет свой MySQL, Redis, Mailpit и loopback порты 4151/4153/4154. `MYSQL_IMAGE` должен совпадать с подтверждённой версией реальной БД для финальных проверок. Setup не импортирует каталог и не выполняет миграции.

Production состоит из самостоятельных папок `prod/orbita-front`, `prod/orbita-admin`,
`prod/orbita-api` и конфига внешнего nginx. Сборка и запуск используют фиксированные
`larux/orbita-*:latest`. Порядок настройки, миграций, запуска и архивирования:
[prod/README.md](../prod/README.md). Runtime backend-секреты находятся только в
`prod/orbita-api/.env`; front/admin получают собственные публичные настройки.

До production: подтверждённый экспорт, metadata, восстановленная копия и backup. `backup.sh production` сохраняет private gzip dump существующей внешней БД; `MYSQL_BACKUP_IMAGE` задаёт совместимую версию клиента. Скрипт не запускается сборкой или обычным обновлением.

Служебные миграции выполняются с Mac из отдельного Laravel `ms/orbita-db` через SSH-туннель по [VPS/18](../../VPS/18-local-database.md). `bash scripts/migrate-services.sh production` — локальный PHP runner с проверкой catalogue fingerprint до/после; Redis, API и Docker ему не нужны. Update не выполняет миграции, только проверку metadata и смену образов.

Переключение writers согласовать до approval счётчиков. Сначала все старые мутации и импортеры прекращают независимую выдачу ID. После approval и конфигурации gate можно включить запись. Отсутствие экспорта не компенсируется env-флагом.

Заказы требуют `ORDER_MANAGER_EMAIL` и SMTP. Для оплаты: `PAYMENTS_ENABLED=true`, shop ID/secret и HTTPS `YOOKASSA_RETURN_URL`. Сохранить существующую интеграцию чеков: `YOOKASSA_RECEIPT_MODE=external`, если чеки формирует текущая внешняя касса; `yookassa` использует подтверждённые VAT/payment mode/subject параметры. Не придумывать ставки и настройки магазина. Провести provider sandbox tests до включения real payments.

`MEDIA_UPLOADS_ENABLED=true` включать после проверки S3 credentials/disk. Production uploads используют existing `/images/`; старые hashes не перемещаются.

Внешний nginx отправляет Host и X-Forwarded-Proto в loopback-прокси front/admin/API. PHP-FPM, Redis и MySQL не публикуют порты. Доверенные proxy IP/CIDR задаются явно. Health: `/health`, `/admin/health`, `/health/api`; логи не содержат query strings, пароли и payment payloads. Наблюдать outbox pending/failed, платёжные creating/pending/needs_review и ошибки API.

Откат: восстановить заранее сохранённые совместимые образы/digest, сохранив APP_KEY, БД, Redis и allocator. Служебные миграции не откатывать автоматически. Несовместимую старую запись не возобновлять.

Обновление поставки 18.10.2026: общий серверный алгоритм — [Development/VPS](../../VPS/README.md).
MySQL CA передаётся в `prod/orbita-api/cert/mysql-ca.pem`, миграционные доступы и локальный CA —
в частных `ms/orbita-db/.env` и `ms/orbita-db/cert/mysql-ca.pem` на Mac. В production-поставку они не входят; runtime сохраняет своего SQL-пользователя без DDL.
Подробный порядок и требования к сохранению настроек — в [prod/README.md](../prod/README.md).
