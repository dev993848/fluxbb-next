# ADR-001: Выбор PHP 8.4+ как минимальной версии

## Статус
Accepted

## Контекст
Исходный проект требует PHP >=5.6.4. Современные версии PHP (8.x) предоставляют:
- JIT-компиляцию
- named arguments
- readonly properties
- enums
- union types
- FFI
- значительный прирост производительности

PHP 8.4+ — LTS с долгосрочной поддержкой.

## Решение
Минимальная версия: PHP 8.4 (с возможностью понижения до 8.2 при необходимости совместимости хостингов).

## Последствия
- Полный переход на строгую типизацию (`declare(strict_types=1)`)
- Использование enum для ролей (`PUN_ADMIN`, `PUN_MOD` и т.д.)
- Readonly DTO для Query/Command
- Невозможность установки на shared-хостингах с PHP 7.x

---

# ADR-002: Domain-Driven Design с Bounded Contexts

## Статус
Accepted

## Контекст
FluxBB — монолит с процедурной архитектурой. Все файлы — скрипты с прямым доступом
к глобальному `$db`. Доменные шрамы (модерация, подписки) размазаны по всему коду.

## Решение
Разделить на Bounded Contexts (BC):
- Forum (категории, форумы)
- Topic (темы)
- Post (посты)
- User (пользователи, группы, аутентификация)
- Moderation (баны, флуд-контроль, модерация)
- Subscription (подписки на темы)
- Shared (общие сущности, инструментарий)

Каждый BC имеет свою Domain-модель, Application-слой и Infrastructure.

## Последствия
- Изолированные изменения (не затрагивают другие BC)
- Четкие границы ответственности
- Возможность вынести BC в микросервис
- Больше boilerplate кода
- Сложнее для понимания новыми разработчиками

---

# ADR-003: Doctrine DBAL + Migration вместо собственного DBAL

## Статус
Accepted

## Контекст
Текущая кодовая база имеет свою DB abstraction layer (`include/dblayer/`),
поддерживающую MySQL, MySQLi, PgSQL, SQLite. Нет миграций — эволюция схемы
через `db_update.php`.

## Решение
- Использовать Doctrine DBAL для query building
- Doctrine Migrations для версионирования схемы
- Прямые SQL-запросы для сложных случаев (решения InnoDB fulltext)

## Последствия
- Стандартизованный доступ к БД
- Миграции с откатом
- Потеря "лёгкости" оригинального FluxBB
- Поддержка MySQL, PostgreSQL, SQLite через DBAL

---

# ADR-004: Twig как шаблонизатор

## Статус
Accepted

## Контекст
Текущий FluxBB использует PHP-шаблоны (include `main.tpl`) и смесь HTML/PHP
в runtime. Это небезопасно и трудно поддерживать.

## Решение
Заменить на Twig 3.x:
- `templates/forum/` — пользовательские страницы
- `templates/admin/` — админка
- `templates/mail/` — email-шаблоны
- `templates/components/` — переиспользуемые компоненты

## Последствия
- Auto-escaping XSS protection
- Наследование шаблонов (`{% extends %}`)
- Сложнее динамически модифицировать HTML (но это и не нужно)
- Потеря "blank slate" подхода FluxBB

---

# ADR-005: Сохранение обратной совместимости URL

## Статус
Accepted

## Контекст
FluxBB использует URL типа `viewtopic.php?id=123&pid=456`. Тысячи форумов
имеют внешние ссылки на такие URL.

## Решение
- Написать мидлварь/роутинг, перенаправляющий старые URL на новые
  - `viewtopic.php` → `/topic/{id}`
  - `viewforum.php` → `/forum/{id}`
  - `profile.php` → `/user/{id}`
  - `post.php` → `/post/new`
  - `edit.php` → `/post/{id}/edit`
- Первые 6 месяцев — 301 redirect со старых URL

## Последствия
- SEO-совместимость
- Старые ссылки не сломаются
- Дополнительная работа при разворачивании

---

# ADR-006: Event Sourcing для Moderation и Subscription

## Статус
Proposed

## Контекст
Доменные шрамы (moderation, subscription) имеют много cross-cutting concerns:
- Бан пользователя → завершить сессию, скрыть его посты, разорвать подписки
- Flood block → зафиксировать попытку, уведомить модератора
- Подписка на тему → уведомление при новом посте

## Решение
Использовать доменные события (Domain Events):
- `UserBanned`
- `UserReported`
- `PostReported`
- `TopicSubscribed`
- `FloodDetected`
- `PostEdited` (содержит кто, когда, таймаут)

Event Bus — Symfony Messenger (in-memory, с возможностью переключения на RabbitMQ).

## Последствия
- Слабая связанность BC
- Auditing из коробки
- Дополнительная сложность для простых операций
- Легко добавить email/SM/telegram уведомления позже