# Архитектура

Nginx маршрутизирует `/` в storefront, `/admin/` в admin, `/graphql`, `/auth/*`, `/api/*` в один Laravel FPM. Внешний reverse proxy завершает TLS. Браузер работает с одним origin; SSR использует внутренний адрес gateway и передаёт только контекст своего запроса.

Laravel разделён на Catalog, Auth, Media, Orders, Payments и Schema. Eloquent отражает старые singular таблицы и aliases `parentable_type`; actions управляют транзакциями и проверяют политики. GraphQL не принимает произвольные connections и morph types.

Auth использует новые `orbita_users` и cookie-session в Redis. Публичной регистрации нет; обе роли сотрудников могут управлять карточками. Каталоговые справочники выбираются без CRUD.

Orders сохраняет снимок позиций и outbox в одной транзакции. Worker выполняет сохранённые уведомления, восстанавливает зависшие leases и повторяет SMTP. Payments хранит ключ и исходный запрос провайдера; повтор не создаёт новый платёж. Scheduler сверяет незавершённые платежи. После неизвестного результата создания в течение 23 часов дальнейшая автоматическая попытка останавливается для ручной проверки.

`orbita-db` — отдельный Laravel migration runner со своими зависимостями. Canonical migrations и восстановление выполняются локальным PHP через SSH-туннель к VPS (VPS/18), без API/Docker runtime. API проверяет только read-only manifest применённых миграций. Миграции и metadata approval не меняют catalogue DDL. Счётчики ID находятся в той же SQL-транзакции, что карточка товара.
