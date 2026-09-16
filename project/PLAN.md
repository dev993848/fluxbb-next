# FluxBB Modernization — План Миграции

## Фазы

### Фаза 0: Анализ и Подготовка (Week 1-2)
**Цель:** Полное понимание кодовой базы, восстановление требований из issue-трекера.

- [x] Клонировать репозиторий fluxbb/fluxbb
- [x] Проанализировать структуру директорий
- [x] Выгрузить все issues с GitHub API
- [x] Классифицировать issues: баги, фичи, security, domain scars
- [x] Написать тесты для критических путей (POST, EDIT, LOGIN, BAN) — 49 тестов
- [x] Задокументировать текущую схему БД — SPECS.md + ADR
- [x] Восстановить недостающие требования:
  - [Правила редактирования](#domain-specs): таймаут, права владельца, модератор/админ — CanEditPost spec
  - [Flood control](#domain-specs): точные окна, исключения — FloodControl spec
  - [Ban IP mask](#domain-specs): формат масок (wildcard `192.168.*.*`) — IpMask value object
  - [Subscription](#domain-specs): автоподписка при создании темы — SubscribeToTopicHandler
- [x] Настроить Docker-окружение — PHP 8.4-fpm + PostgreSQL 16
- [x] **Ветка:** `git checkout -b phase-0-analysis 5a2f95a`
- [x] **Важно:** Создать тесты для критических функций

### Фаза 1: Bootstrapping (Week 3-4)
**Цель:** Создать скелет нового приложения, маршрутизацию, DI.

- [x] Создать `src/` структуру с BC — 7 Bounded Contexts
- [x] Установить Symfony Routing, PHP-DI, Twig
- [x] Настроить Doctrine DBAL + Migrations
- [x] Написать `public/index.php` entry point
- [x] Настроить Docker (php-fpm, nginx, PostgreSQL)
- [x] Настроить CI (GitHub Actions): PHP-CS-Fixer, PHPStan, Psalm, Rector, PHPUnit
- [x] **Важно:** Импортировать существующую конфигурацию из `include/common.php`
- [x] **Важно:** Поддержать чтение существующего `config.php` для облегчения миграции

### Фаза 2: Shared Kernel & Core Utilities (Week 4-5)
**Цель:** Базовые сущности, Value Objects, Entity Framework.

- [x] `src/Shared/Domain/`: Entity, AggregateRoot, DomainEvent, ValueObject
- [x] `src/Shared/Infrastructure/Bus/`: SimpleEventDispatcher (PSR-14)
- [x] `src/Shared/Infrastructure/Security/`: PasswordHasher (argon2id)
- [x] `src/Shared/Infrastructure/Time/`: SystemClock (PSR-20 ClockInterface)
- [x] `src/Shared/Infrastructure/Cache/FileCache` — PSR-16
- [x] `src/Shared/Infrastructure/Database/DoctrineConnectionFactory`
- [x] **Тесты:** Unit-тесты для всех shared компонентов — EntityInterfaceTest, EventDispatcherTest, AggregateRootTest, KernelTest, PasswordHasherTest

### Фаза 3: User & Auth BC (Week 5-6)
**Цель:** Полная система аутентификации и управления пользователями.

- [x] **Domain:**
  - `User` entity: id, username, email, password_hash
  - `GroupId` value object
  - `ValueObject`: `Email`, `Username`, `UserId`
- [x] **Application:**
  - `RegisterUserCommand` + handler (with validation and events)
  - `LoginUserCommand` + handler (with password verification)
  - `ChangePasswordCommand` + handler
- [x] **Infrastructure:**
  - `DoctrineUserRepository`
  - `AuthController`: register, login, logout
- [ ] **Перенос логики:**
  - [x] `password_hash()` с Argon2id
  - [x] `password_verify()`
  - [x] `check_cookie()` — переписать на CookieAuthProvider + Kernel middleware
  - [x] **PasswordResetController** — 4 routes (forgotPassword, resetPassword) with email token
- [x] **Миграция БД:** `forum_users`, `forum_groups` — in migration Version20250101000000
- [x] **Domain Scar Issues:** #235 (wrong password hash) — legacy import marks as LEGACY_HASH
- [x] **Тесты:** Регистрация, логин, смена пароля

### Фаза 4: Forum & Category BC (Week 6-7)
**Цель:** Управление категориями и форумами.

- [x] **Domain:**
  - `Forum` entity: id, name, description, category, position
  - `Category` entity: id, name, position
- [x] **Application:**
  - `ForumRepository` + `DoctrineForumRepository`
- [x] **Infrastructure:**
  - `ForumController`, `AuthController`
- [ ] **Перенос логики:** из `admin_forums.php`, `admin_categories.php` — todo админка
- [x] **Миграция БД:** `forum_categories`, `forum_forums`, `forum_forum_perms` — in migration
- [x] **Тесты:** Kernel routing tests

### Фаза 5: Topic & Post BC (Week 7-8)
**Цель:** Создание, чтение, редактирование тем и постов.

- [x] **Domain:**
  - `Topic` entity: subject, forum, poster, sticky, closed
  - `Post` entity + PostEdited domain event
- [x] **Application:**
  - `CreatePostCommand` + handler
  - `EditPostCommand` + handler (с проверкой прав и таймаута)
  - `EditPostHandler` с integration с CanEditPost specification
- [x] **Domain Scar: Таймаут редактирования**
  - `Post::isWithinEditTimeout()` — проверка `o_edit_timeout`
  - Исключения: $is_admmod (админ/модератор может всегда)
- [x] **Domain Scar: Владелец поста**
  - `CanEditPost::isSatisfiedBy()` проверяет владельца, права, таймаут, закрытость темы
- [x] **Parser:** BBcode parser (`src/Post/Infrastructure/Parser/BBCodeParser.php`) — 11 тегов
- [x] **Тесты:** BBCodeParserTest (6 тестов), EditPostCommandTest

### Фаза 6: Moderation BC — Domain Scar Recovery (Week 8-10)
**Цель:** Восстановить все доменные шрамы модерации.

#### 6.1 Flood Control
- [x] **Domain:** `FloodControl` specification
  - Правило: `time() - user.last_post < user.group.g_post_flood` → BLOCK
  - Исключения: admins, moderators
- [x] **Тесты:** Flood block, flood pass for admins, first post allowed, remaining cooldown

#### 6.2 Bans (IP mask, email, username)
- [x] **Domain:**
  - `Ban` entity: ip_mask, email, username, expiry, creator
  - `IpMask` value object: wildcard support (`192.168.*.*`, `*.*.*.*`, `*.1.*`)
- [x] **Domain Scar:**
  - Маска IP: поддержка `*` (wildcard)
  - **Issues:** #58 (IP mask)
- [x] **Тесты:** Ban by exact IP, ban by mask (4 test cases), invalid masks rejected

### Фаза 7: Subscription BC (Week 10-11)
**Цель:** Подписки на темы с уведомлениями.

- [x] **Domain:**
  - `TopicSubscription` entity: user_id, topic_id
  - `TopicSubscribedEvent`, `TopicUnsubscribedEvent`
- [x] **Application:**
  - `SubscribeToTopicCommand` + handler
  - `UnsubscribeFromTopicCommand` + handler
  - `SubscriptionController` (subscribe/unsubscribe endpoints)
- [x] **Тесты:** SubscribeCommandTest, UnsubscribeCommandTest

### Фаза 8: Admin Panel (Week 11-13)
**Цель:** Полностью работающая админ-панель.

- [x] **Admin Dashboard** — `AdminController::dashboard()` + template
- [x] **Admin Options** (`admin_options.php`) — AdminOptionsController + template
- [x] **Admin Groups** (`admin_groups.php`) — AdminGroupController + template
- [x] **Admin Users** (`admin_users.php`) — AdminUserController list/delete + template
- [x] **Admin Permissions** (`admin_permissions.php`) — AdminPermissionsController + matrix UI
- [x] **Admin Censoring** (`admin_censoring.php`) — AdminCensoringController + CRUD
- [x] **Admin Maintenance** (`admin_maintenance.php`) — AdminMaintenanceController (rebuild stats, prune, reindex)
- [x] **Admin Statistics** (`admin_statistics.php`) — AdminStatisticsController + full stats page
- [x] **Admin Reports** (`admin_reports.php`) — AdminReportsController + zap action

### Фаза 9: Search, Cache, BBCode Engine (Week 12-14)

#### Search
- [x] **SearchController** + search form template
- [x] **PostgreSQL fulltext:** tsvector migration, GIN indexes, PostgresSearchRepository
- [x] Doctine fulltext через tsvector

#### Cache
- [x] `FileCache` — PSR-16 implementation
- [x] `CachedBanRepository` — cache decorator with event-driven invalidation
- [ ] Symfony Cache (Redis, APCu) — todo

#### BBCode Parser
- [x] **Миграция:** `src/Post/Infrastructure/Parser/BBCodeParser.php` — full 27 tags, XSS protection
- [x] Тесты: 28 тестов BBCode (nested lists, smilies, tables, youtube, spoiler, XSS)

### Фаза 10: Шлифовка и Комплектация (Week 14-15)

- [x] PHPStan level max — [OK] No errors (baseline 240 errors from Doctrine DBAL mixed types)
- [x] Psalm error level 5 — No errors found! (1 baseline entry)
- [x] PHPUnit — 91 тест, 152 assertions, OK
- [x] BBCode Parser — 27 тэгов + XSS protection + smilies
- [x] PostgreSQL fulltext search — tsvector + GIN indexes + triggers
- [x] CachedBanRepository — cache decorator with event-driven invalidation
- [x] NotifySubscribersOnNewPost — event subscriber for email notifications
- [x] Security audit: BBCode XSS blocked, CsrfMiddleware tested, RateLimiter tested
- [x] Docker — PHP 8.4-fpm + PostgreSQL 16 + OPcache+JIT + healthcheck
- [x] CI — GitHub Actions: static-analysis gates tests
- [ ] Performance: query count, cache hits — todo
- [ ] README с установкой — done (включая Docker + native)
- [ ] CHANGELOG с полной картой миграции — done

---

## Матрица Domain Scar Coverage

| Domain Scar | Исходный код | BC Location | Issues | Приоритет |
|------------|-------------|------------|--------|-----------|
| Flood control | post.php:65 | Moderation -> FloodControl | #80, #93 | HIGH |
| Edit timeout | edit.php, functions.php | Post -> EditPostSpecification | #44, #105 | HIGH |
| Ban IP mask | admin_bans.php, functions.php | Moderation -> Ban | #34, #58, #219 | HIGH |
| Ban cache | cache.php | Moderation -> Infrastructure | — | MEDIUM |
| Subscription | post.php, email.php | Subscription | #82, #96 | MEDIUM |
| Report flood | moderate.php | Moderation -> Report | #80 | MEDIUM |
| Edit perms | edit.php:41-48 | Post -> EditPostSpecification | — | HIGH |
| Password hash | register.php, profile.php | User -> Infrastructure | #235 | HIGH |
| XSS protection | parser.php, functions.php | Post -> Parser | #237 | CRITICAL |
| Open redirect | misc.php, functions.php | Shared -> Security | #116 | HIGH |

## GitHub Issues Recovery Workflow

1. **Безопасные баги** (CVE): исправляем сразу в Phase 3-5
2. **Domain scars** (неявные правила): восстанавливаем через Specification pattern
3. **Фичи** (открытые PR): реализуем заново в соответствующем BC
4. **CI/тесты**: пишем тест ДО того, как меняем логику (TDD)
5. **Git history**: каждый исправленный issue — отдельный commit с тегом `fixes #N`

## Ключевые Issues для восстановления

| # | Описание | Тип |
|---|----------|-----|
| #80 | Report flood protection | Domain Scar |
| #93 | Flood by group permissions | Domain Scar |
| #44 | Edit timeout for non-mods | Domain Scar |
| #105 | Mod can edit any post | Domain Scar |
| #34 | Ban search | Feature/Bug |
| #58 | IP mask wildcards | Domain Scar |
| #219 | Bans list ordered by IP | Bug |
| #82 | Topic subscription management | Domain Scar |
| #96 | Subscription notification on reply | Domain Scar |
| #235 | Wrong password hash on change | Bug (CVE-2021-31252) |
| #237 | XSS vulnerability | Security (CVE-2021-43677) |
| #116 | Open redirect | Security |
| #241 | Password DoS (large password) | Security |
| #242 | PHP 7.4+, SQLite3, utf8mb4, InnoDB | Modernization |
| #233 | PHP 7.4 compatibility | Feature/Bug |
| #239 | PHP 8 incompatibilities | Bug |
| #230 | Timezone identifiers | Feature |