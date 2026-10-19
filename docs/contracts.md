# Интерфейсы

GraphQL public reads: GET `/graphql` с `query` и JSON `variables`. Существующие `catalog_one`, `rubric_one`, `category_one`, `product_one` сохранены; списки и поиск пагинируются. Поля цены передаются Decimal-строками. Полный SDL: `ms/orbita-api/graphql/client.graphql`, типы генерируются `npm --prefix scripts run generate:types`.

Auth: GET `/auth/csrf`, GET `/auth/me`, POST `/auth/login`, `/auth/logout`, `/auth/password/forgot`, `/auth/password/reset`. Перед POST получить cookie и передать `X-XSRF-TOKEN`. Cookies HttpOnly для сессии, Secure в production, SameSite=Lax. Админка передаёт cookies в SSR в рамках одного запроса.

Admin GraphQL: `adminProducts`, `adminProduct`, `referenceData`; `createProduct(input: ProductInput!)`, `updateProduct(id: ID!, input: ProductInput!)`, `archiveProduct`, `restoreProduct`. Справочники read-only. Price, unit, texts и properties обновляются атомарно. Отключение сохраняет данные.

Media: POST `/api/media`, multipart `image`, JPEG/PNG/WebP <=10 МБ; ответ `hash`, `url`. Требуются сотрудник, CSRF и включённый upload. Existing `image.hash` и `/images/` сохраняются, общий bucket не чистится.

Quote: POST `/api/checkout/quote`, `{items:[{productId,quantity}]}`; ответ `{items,total,currency,quoteHash}`. Quantity и money — строки. Недоступные товары не оплачиваются, сервер использует настоящую цену.

Order: POST `/api/orders`, `{items,quoteHash,customer:{name,phone,address?,comments?},onlinePayment,idempotencyKey}`. Ответ `{id,status,paymentStatus,total,currency,confirmationUrl}`. При изменении цены 409 `QUOTE_CHANGED` и новый quote. Повтор того же ключа возвращает тот же заказ; иной payload с тем же ключом отвергается.

GET `/api/orders/{id}` доступен только сессии заказчика. GET `/api/admin/orders` доступен сотрудникам. Доставка/разгрузка отдельно согласуются; онлайн сумма включает товары.

POST `/api/payments/yookassa/webhook` — единственное commerce исключение CSRF: статус всегда перепроверяется авторизованным запросом к провайдеру и сравнением amount/currency/order. Возврат покупателя не подтверждает оплату. Pending платежи сверяет scheduler.

POST `/api/subscriptions` сохраняет email и durable уведомление. Это подписка на существующую форму, не новая система маркетинговых рассылок.
