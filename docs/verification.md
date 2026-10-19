# Проверки и границы достоверности

## Автоматически

- PHP tests: синтетическая SQLite схема, auth/CSRF/project isolation, relationship CRUD rollback, независимые ID, gate false by default, archive/restore, media validation.
- Commerce tests: точные decimal calculations, fractional units, quote changes, snapshot/outbox transaction, stable idempotency, session ownership, SMTP retry, payment/provider verification и unknown creation expiry.
- Node tests: integer decimal arithmetic. Svelte checks и production builds для обеих app. GraphQL codegen из standalone SDL.
- Browser tests: публичные маршруты/адаптивность/поиск/корзина в explicit local demo mode; admin login и backend flow проверяются отдельно без вызовов внешних providers.
- Migration allowlist и production config checker проверяют service-only таблицы, внешнюю БД, HTTPS/session flags и immutable tags.

## После получения экспорта

SQLite не доказывает MySQL locking semantics. На изолированном MySQL той же версии импортировать настоящий schema-only dump и synthetic fixtures. Проверить pivots/FK/triggers, morph aliases, Decimal SQL types, nullable/defaults, engines. Сравнить fingerprint до/после service migrations, CRUD, setup/update. Два параллельных процесса создают карточки с price/unit/image/text/property: ID не пересекаются, связанная запись указывает на свою карточку. Ошибка в последней записи откатывает всё, включая счётчики. В полном сценарии старые writers также используют allocator.

## Визуально

Сравнить source и implementation на 360/390/768/1024/1440 px: главная, каталог, товар, корзина, меню/поиск и админка. На 1024 исходник обрезает корзину в header; новая версия должна использовать компактную навигацию до1280. Проверить long titles, увеличенный текст, map, картинки, keyboard focus/Escape, reduced motion. Порядок блоков, палитра и контент сохраняются.

## Production gate

Не утверждать real DB compatibility до экспорта и metadata audit; не считать payment redirect подтверждением оплаты. Настройки SMTP/S3/YooKassa/чеков и согласование writers проверяются отдельно. Тесты не обращаются к production DB и не создают реальные платежи/письма.
