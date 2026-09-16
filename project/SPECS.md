# FluxBB Modernization — Технические Спецификации

## 1. Целевой стек

| Компонент | Технология | Обоснование |
|-----------|-----------|-------------|
| Язык | PHP 8.4+ (минимум 8.2) | Типизация, атрибуты, JIT, readonly properties |
| Маршрутизация | Symfony Routing + FastRoute | Промышленный стандарт |
| DI-контейнер | PHP-DI или Symfony DI | Autowiring, конфигурация |
| ORM | Doctrine DBAL (или Cycle) | Migrations, Query Builder |
| Шаблонизация | Twig | Безопасный, расширяемый |
| Миграции | Doctrine Migrations | Версионирование схемы БД |
| Тестирование | PHPUnit 12 + Pest | Современный стек |
| Контейнеризация | Docker + docker-compose | Окружение разработки |
| Quality | PHPStan level 9, PHP CS Fixer, Rector | Статический анализ |
| Password hashing | `password_hash()`/`password_verify()` native | bcrypt/argon2 |
| Формат дат | Carbon | DateTimeZone-адекватный |
| Медиация | Symfony Messenger или собственный | Event sourcing, CQRS-ready |

## 2. Структура директорий

```
fluxbb/
├── src/
│   ├── Forum/              # Forum BC (Bounded Context)
│   │   ├── Domain/
│   │   │   ├── Entity/         # Forum, Category
│   │   │   ├── ValueObject/    # ForumName, Description
│   │   │   ├── Repository/     # ForumRepositoryInterface
│   │   │   ├── Event/          # ForumCreated, ForumUpdated
│   │   │   └── Exception/
│   │   ├── Application/
│   │   │   ├── Command/        # CreateForum, UpdateForum
│   │   │   ├── Query/          # GetForumQuery
│   │   │   └── Service/        # ForumService
│   │   └── Infrastructure/
│   │       ├── Persistence/    # DoctrineForumRepository
│   │       └── Controller/     # ForumController (Symfony)
│   │
│   ├── Topic/               # Topic BC
│   │   ├── Domain/
│   │   ├── Application/
│   │   └── Infrastructure/
│   │
│   ├── Post/                # Post BC
│   │   ├── Domain/
│   │   ├── Application/
│   │   └── Infrastructure/
│   │
│   ├── User/                # User & Auth BC
│   │   ├── Domain/           # User, Group, Role
│   │   ├── Application/
│   │   └── Infrastructure/   # PasswordHasher, AuthController
│   │
│   ├── Moderation/          # Moderation BC (Domain Scar)
│   │   ├── Domain/
│   │   │   ├── Entity/       # Ban, Report, FloodControl
│   │   │   ├── ValueObject/  # IPMask, BanExpiry
│   │   │   ├── Service/      # BanService, FloodDetector
│   │   │   └── Specification/ # CanEditPost, CanModerate
│   │   ├── Application/
│   │   └── Infrastructure/
│   │
│   ├── Subscription/        # Subscription BC (Domain Scar)
│   │   ├── Domain/
│   │   ├── Application/
│   │   └── Infrastructure/
│   │
│   └── Shared/              # Shared Kernel
│       ├── Domain/
│       │   ├── Entity.php        # Base entity trait
│       │   ├── AggregateRoot.php
│       │   ├── DomainEvent.php
│       │   └── ValueObject.php
│       └── Infrastructure/
│           ├── Controller/       # BaseController
│           └── Bus/              # CommandBus, EventBus
│
├── config/
│   ├── packages/
│   ├── routes/
│   └── services.php
│
├── migrations/
├── templates/
├── public/
│   └── index.php             # Entry point
│
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── Fixtures/
│
├── docker/
├── project/                  # Документация проекта
└── var/                      # Cache, logs
```

## 3. Domain Scars (из Issue Tracker)

### 3.1 Модерация
- **Flood control** (`g_post_flood`, `last_post`): интервал между постами
  - Проверка в `post.php` и `edit.php`
  - `flux_hook('post_before_validation')`
  - **Issue:** #80, #93
- **Права редактирования чужих постов**:
  - `$pun_user['g_edit_posts']` — может ли пользователь редактировать
  - `$cur_post['poster_id'] != $pun_user['id']` — ограничение на свои
  - `$is_admmod` — администраторы/модераторы могут любые
  - Таймаут: `$pun_config['o_edit_timeout']` — время после публикации
  - **Issues:** #44, #105
- **Баны по IP с маской** (`admin_bans.php`):
  - `$db->query('... ban_ip ...')` — IP с wildcard (`192.168.*`)
  - Проверка при каждом запросе через `check_bans()`
  - Кеш бан-листа: `cache_bans.php`
  - **Issues:** #34, #58, #219

### 3.2 Подписки
- **Топик-сабскрипшены**:
  - `topic_subscriptions` таблица
  - `email.php` — отправка уведомлений
  - `subscription` флаг при посте
  - **Issues:** #82, #96

### 3.3 Пароль
- HMAC-based cookie auth: `forum_hmac()`
- `flux_password_hash()` / `flux_password_verify()`
- **Issues:** #235 (фикс бага с новым паролем)

### 3.4 Безопасность
- XSS: CVE-2021-43677 (#237)
- Open redirect: #961 (#116)
- CSRF: `confirm_referrer()` — проверка referrer
- **Issues:** #237, #116, #241 (password size limit)

## 4. API и Форматы

### RESTful API (для SPA или интеграций)

```
GET    /api/forums              -> ForumController::index()
POST   /api/forums              -> ForumController::create()
GET    /api/forums/{id}         -> ForumController::show()
PATCH  /api/forums/{id}         -> ForumController::update()
DELETE /api/forums/{id}         -> ForumController::delete()

GET    /api/topics              -> TopicController::index()
POST   /api/topics              -> TopicController::create()
GET    /api/topics/{id}         -> TopicController::show()

GET    /api/posts               -> PostController::index()
POST   /api/posts               -> PostController::create()
PATCH  /api/posts/{id}          -> PostController::update()
DELETE /api/posts/{id}          -> PostController::delete()

GET    /api/users               -> UserController::index()
POST   /api/users/register      -> AuthController::register()
POST   /api/users/login         -> AuthController::login()
POST   /api/users/logout        -> AuthController::logout()

GET    /api/bans                -> BanController::index()
POST   /api/bans                -> BanController::create()
DELETE /api/bans/{id}           -> BanController::delete()

POST   /api/topics/{id}/subscribe    -> SubController::subscribe()
DELETE /api/topics/{id}/subscribe    -> SubController::unsubscribe()
```

### Web UI (Twig pages)
Контроллеры будут рендерить Twig-шаблоны для обычных пользователей, используя те же
сервисы/команды, что и API.

## 5. База данных

### Миграция схемы
- Исходная схема ~20 таблиц (см. `install.php`)
- Переход на миграции (Doctrine Migrations)
- Имена таблиц: `fluxbb_` prefix → `forum_` prefix
- Добавление UUID как альтернативы автоинкременту
- `created_at`/`updated_at` — carbon-aware timestamps (datetime(3))

### Основные таблицы

| Таблица | Entitas | Примечание |
|---------|---------|------------|
| `forum_categories` | Category | disp_position |
| `forum_forums` | Forum | cat_id, last_post_id |
| `forum_topics` | Topic | forum_id, poster_id, last_post_id |
| `forum_posts` | Post | topic_id, poster_id, message (LONGTEXT) |
| `forum_users` | User | username, password_hash, email |
| `forum_groups` | Group | permissions |
| `forum_forum_perms` | ForumPermission | group_id, forum_id |
| `forum_bans` | Ban | ip_mask, email, username |
| `forum_topic_subscriptions` | Subscription | user_id, topic_id |
| `forum_reports` | Report | post_id, reported_by |
| `forum_online` | OnlineSession | user_id, logged |
| `forum_censoring` | CensoredWord | search_for, replace_with |
| `forum_config` | Config | key-value store |

## 6. Безопасность

1. **Аутентификация:** session-based с опцией JWT для API
2. **Password Hashing:** Argon2id через `password_hash()`
3. **CSRF:** Symfony CSRF для форм, JWT для API
4. **XSS:** Twig auto-escaping, Content-Security-Policy header
5. **SQL Injection:** Doctrine ORM/DBAL parameter binding
6. **Flood Control:** Rate limiter (логика + Redis опционально)
7. **File Uploads:** Размер, тип, storage adapter
8. **Headers:** X-Frame-Options, X-Content-Type-Options, HSTS