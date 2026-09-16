# FluxBB Next

**Modern PHP forum engine** — полностью переписан с FluxBB v1.5.11 (PHP 5.6) на современный стек **PHP 8.4+** с Domain-Driven Design.

[![CI](https://github.com/dev993848/fluxbb-next/actions/workflows/ci.yml/badge.svg)](https://github.com/dev993848/fluxbb-next/actions/workflows/ci.yml)

---

## Содержание

- [Требования](#требования)
- [Быстрый старт (Docker)](#быстрый-старт-docker)
- [Production-деплой (Docker)](#production-деплой-docker)
  - [Переменные окружения](#переменные-окружения)
  - [Redis + MailHog](#redis--mailhog)
  - [Healthcheck](#healthcheck)
- [Установка без Docker](#установка-без-docker)
- [Миграция с FluxBB 1.5](#миграция-с-fluxbb-15)
- [Архитектура](#архитектура)
- [Конфигурация](#конфигурация)
- [Performance мониторинг](#performance-мониторинг)
- [Security checklist](#security-checklist)
- [Бэкап и восстановление](#бэкап-и-восстановление)
- [Траблшутинг](#траблшутинг)
- [Статический анализ](#статический-анализ)
- [Тестирование](#тестирование)
- [Лицензия](#лицензия)

---

## Требования

| Компонент | Минимум | Рекомендуется |
|-----------|---------|---------------|
| PHP | 8.2 | **8.4+** (JIT) |
| PostgreSQL | 15 | **16** |
| Redis | 6 | **7** (кеш + сессии) |
| Composer | 2.x | 2.7+ |
| Node (build only) | - | 20+ (для dev assets) |

**PHP расширения:**
- `pdo_pgsql`, `pdo_mysql` — база данных
- `mbstring`, `intl`, `zip` — обязательные
- `opcache` — **обязательно** (JIT)
- `redis` — для Redis кеша
- `apcu` — для APCu кеша (опционально)

---

## Быстрый старт (Docker)

```bash
# 1. Клонировать
git clone https://github.com/dev993848/fluxbb-next.git
cd fluxbb-next

# 2. Запустить окружение (разработка)
docker compose up -d

# 3. Выполнить миграции
docker compose exec app php console.php migrations:migrate

# 4. Открыть в браузере
open http://localhost:8080
# Email UI (MailHog): http://localhost:8025
```

---

## Production-деплой (Docker)

### Быстрый запуск

```bash
# 1. Клонировать
git clone https://github.com/dev993848/fluxbb-next.git
cd fluxbb-next

# 2. Сменить COOKIE_SEED в .env
# Генерация: openssl rand -hex 64
echo 'COOKIE_SEED='$(openssl rand -hex 64) >> .env

# 3. Собрать образы
docker compose build --no-cache

# 4. Запустить
docker compose up -d

# 5. Миграции БД
docker compose exec app php console.php migrations:migrate

# 6. Прогреть кеш (бан-лист, конфиг)
docker compose exec app php console.php cache:warmup

# 7. Проверить статус
curl -f http://localhost:8080/healthcheck
```

### docker-compose.yml (Production)

```yaml
services:
  app:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    environment:
      APP_ENV: prod
      APP_DEBUG: 0
      DB_HOST: database
      DB_NAME: fluxbb
      DB_USER: fluxbb
      DB_PASSWORD: fluxbb_secret
      # Redis cache
      CACHE_DSN: redis://redis:6379/1
      CACHE_NAMESPACE: fluxbb_
      # SMTP (заменить на реальный)
      MAILER_DSN: smtp://user:pass@smtp.example.com:587
      MAILER_FROM: noreply@fluxbb.local
      MAILER_FROM_NAME: "FluxBB Forum"

  redis:
    image: redis:7-alpine
    volumes:
      - fluxbb_redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]

  database:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: fluxbb
      POSTGRES_USER: fluxbb
      POSTGRES_PASSWORD: fluxbb_secret
    volumes:
      - fluxbb_db_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U fluxbb"]
```

### Переменные окружения

Все переменные задаются через `.env` или `environment:` в docker-compose.yml.

| Переменная | По умолчанию | Описание |
|-----------|-------------|----------|
| `APP_ENV` | `prod` | `dev`, `prod`, `test` |
| `APP_DEBUG` | `0` | Включать debug-режим |
| **Database** | | |
| `DB_DRIVER` | `pdo_pgsql` | `pdo_pgsql` или `pdo_mysql` |
| `DB_HOST` | `127.0.0.1` | Хост БД |
| `DB_PORT` | `5432` | Порт |
| `DB_NAME` | `fluxbb` | Имя БД |
| `DB_USER` | `fluxbb` | Пользователь |
| `DB_PASSWORD` | `fluxbb_secret` | Пароль |
| **Cache** | | |
| `CACHE_DSN` | `file://var/cache` | `redis://redis:6379/1`, `apcu://`, `file://...` |
| `CACHE_NAMESPACE` | `fluxbb_` | Префикс ключей кеша |
| **Mailer** | | |
| `MAILER_DSN` | `native://default` | `smtp://user:pass@host:25`, `sendmail://default` |
| `MAILER_FROM` | `noreply@fluxbb.local` | Email отправителя |
| `MAILER_FROM_NAME` | `FluxBB Forum` | Имя отправителя |
| **Auth** | | |
| `COOKIE_NAME` | `fluxbb_session` | Имя куки |
| `COOKIE_SEED` | — | **64-символьная строка** (обязательно сменить!) |
| `SESSION_DRIVER` | `database` | `database` (через PDO) или `redis` |
| **Redis (сессии)** | | |
| `REDIS_HOST` | `redis` | Хост Redis (если `SESSION_DRIVER=redis`) |
| `REDIS_PORT` | `6379` | Порт Redis |

> **Внимание:** `COOKIE_SEED` должен быть уникальным для каждой установки.
> ```bash
> openssl rand -hex 64  # генерация
> ```

### Redis + сессии

Redis используется для:
- **Кеш** — `CACHE_DSN=redis://redis:6379/1` (PSR-16)
- **Сессии** — через `session.save_handler=redis` (настроено в Dockerfile)
- **Message queue** — Messenger transport (опционально)

Проверка Redis:
```bash
docker compose exec redis redis-cli ping
# PONG

docker compose exec app php -r "echo extension_loaded('redis') ? 'OK' : 'MISS';"
# OK
```

### Mailer

**Development:**
```bash
# docker-compose.yml уже включает MailHog
# Все письма доступны в UI: http://localhost:8025
MAILER_DSN=smtp://mailhog:1025
```

**Production (Symfony Mailer):**
```bash
# SMTP
MAILER_DSN=smtp://user:pass@smtp.example.com:587

# Sendmail
MAILER_DSN=sendmail://default
```

**Без SMTP (fallback):**
```bash
MAILER_DSN=native://default  # использует PHP mail()
```
**Примечание:** `symfony/mailer` устанавливается опционально через Composer.
Если пакет не установлен — используется `mail()`.

### Healthcheck

Эндпоинт `/healthcheck` проверяет:
- PHP-FPM статус (через Nginx)
- PostgreSQL соединение (через `pg_isready`)
- Redis (через `redis-cli ping`)

```bash
curl -f http://localhost:8080/healthcheck
# HTTP 200 OK
```

---

## Установка без Docker

### Ubuntu/Debian 24.04+

```bash
# 1. PHP 8.4 + расширения
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install -y php8.4-fpm php8.4-cli php8.4-pgsql \
    php8.4-mbstring php8.4-intl php8.4-zip php8.4-xml \
    php8.4-redis php8.4-apcu php8.4-opcache

# 2. PostgreSQL
sudo apt install -y postgresql-16
sudo -u postgres createuser fluxbb -P
sudo -u postgres createdb fluxbb -O fluxbb

# 3. Redis
sudo apt install -y redis-server

# 4. Проект
git clone https://github.com/dev993848/fluxbb-next.git /var/www/fluxbb
cd /var/www/fluxbb
cp .env.example .env
# Отредактировать .env под свою БД

# 5. Зависимости
composer install --no-dev --optimize-autoloader --no-interaction

# 6. Миграции
php console.php migrations:migrate

# 7. Прогреть кеш
php console.php cache:warmup

# 8. Nginx
cat > /etc/nginx/sites-available/fluxbb << 'EOF'
server {
    listen 80;
    server_name forum.example.com;
    root /var/www/fluxbb/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Security
    location ~* (\.git|\.env|composer\.json|composer\.lock) {
        deny all;
    }

    # Static assets
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff2?)$ {
        expires max;
        add_header Cache-Control "public, immutable";
    }
}
EOF

ln -s /etc/nginx/sites-available/fluxbb /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

# 9. Настроить OPcache + JIT
cat > /etc/php/8.4/cli/conf.d/99-opcache.ini << 'EOF'
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.revalidate_freq=60
opcache.jit=1255
opcache.jit_buffer_size=128M
EOF
```

### CentOS / RHEL 9

```bash
# REMI PHP 8.4
sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm
sudo dnf module enable -y php:remi-8.4
sudo dnf install -y php-cli php-fpm php-pgsql php-mbstring \
    php-intl php-zip php-xml php-redis php-apcu php-opcache

# Далее аналогично Ubuntu
```

---

## Миграция с FluxBB 1.5

```bash
php console.php fluxbb:import \
    --old-db-url=pdo_mysql://user:pass@host:port/old_fluxbb \
    --prefix=forum_
```

Мигратор автоматически:
- Читает все 13 таблиц старого FluxBB
- Трансформирует схему в новую PostgreSQL
- Помечает старые пароли как `LEGACY_HASH:` — пользователи сбрасывают при входе
- Работает транзакционно (all-or-nothing)

**После миграции:**
```bash
# Перестроить поисковый индекс
php console.php migrations:migrate  # tsvector + триггеры

# Прогреть кеш
php console.php cache:warmup

# Сбросить пароль администратора
php console.php fluxbb:reset-admin-password new_password
```

---

## Архитектура

```
src/
├── Shared/          # Shared Kernel (кеш, БД, безопасность, mailer)
├── Forum/           # Forum & Category BC
├── Topic/           # Topic BC
├── Post/            # Post BC + BBCode Parser
├── User/            # User & Auth BC
├── Moderation/      # Moderation BC (Bans, Flood, Reports)
├── Subscription/    # Subscription BC
├── Admin/           # Admin Panel (9 секций)
└── Search/          # Search BC (PostgreSQL fulltext)
```

### Технический стек

| Компонент | Технология |
|-----------|-----------|
| Язык | PHP 8.4+ |
| Маршрутизация | Symfony Routing 7 |
| DI-контейнер | PHP-DI 7 |
| ORM | Doctrine DBAL 4 |
| Миграции | Doctrine Migrations 3 |
| Шаблоны | Twig 3 |
| База данных | PostgreSQL 16 (default) |
| Кеш | Redis 7 / APCu / Filesystem (PSR-16) |
| Сессии | Redis / PDO-backed |
| Почта | Symfony Mailer / native mail() |
| Статический анализ | PHPStan level max + Psalm level 5 |
| CI | GitHub Actions |

### Bounded Contexts

Каждый BC содержит:
- **Domain** — сущности, value objects, спецификации, события
- **Application** — команды/хендлеры
- **Infrastructure** — контроллеры, persistence

Коммуникация между BC — через **domain events** (PSR-14 Event Dispatcher).

---

## Конфигурация

### Параметры PHP

Основные настройки — через `.env` (см. [таблицу выше](#переменные-окружения)).

Дополнительные параметры в `config/config.php`:

```php
'db.charset' => 'utf8',
'forum.default_lang' => 'English',
'forum.default_style' => 'Air',
'forum.cookie_name' => $_SERVER['COOKIE_NAME'] ?? 'fluxbb_cookie',
```

### OPcache + JIT (Production)

Настройки в `docker/php/Dockerfile`:

```ini
opcache.memory_consumption=256   # 128MB → 256MB для больших форумов
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000  # ~20000 PHP файлов
opcache.revalidate_freq=60       # проверять изменения каждые 60с
opcache.jit=1255                 # JIT on-demand, tracing
opcache.jit_buffer_size=128M     # 128MB для JIT
```

### PHP-FPM тюнинг

```ini
pm.max_children=50        # 50 воркеров
pm.start_servers=5
pm.min_spare_servers=5
pm.max_spare_servers=15
pm.max_requests=500       # ресайкл после 500 запросов
```

Для высоконагруженных форумов увеличьте `pm.max_children` до 100-200 (смотря по RAM).

### Redis тюнинг

```bash
# Настройка Redis в docker-compose.yml
redis:
  image: redis:7-alpine
  command: redis-server \
    --maxmemory 256mb \
    --maxmemory-policy allkeys-lru \
    --save 300 100 \
    --appendonly yes
```

---

## Performance мониторинг

### QueryMonitor

Встроенный мониторинг запросов к БД:

```php
<?php
$monitor = $container->get(\FluxBB\Shared\Infrastructure\Database\QueryMonitor::class);

// После выполнения запросов:
$report = $monitor->getBenchmarkReport();
// [
//   'queries' => 12,           // количество запросов
//   'cache_hits' => 8,         // попадания в кеш
//   'cache_misses' => 2,       // промахи кеша
//   'hit_rate' => 80.0,        // процент попаданий
//   'slowest_query' => 'SELECT ...', // медленный запрос
//   'max_duration' => 0.0152,  // макс. время в секундах
// ]
```

### Performance Benchmarks

```bash
php vendor/bin/phpunit --group=performance
```

Ожидаемые результаты:

| Операция | Пропускная способность |
|----------|----------------------|
| BBCode парсинг (1000x) | ~32 000 ops/sec |
| BBCode strip (2000x) | ~208 000 ops/sec |
| CSRF token (10 000x) | ~1 350 000 ops/sec |
| IP mask matching (200 000x) | ~3 260 000 checks/sec |
| Rate limiter (1000x) | ~2 570 checks/sec |

### Blackfire.io / Xdebug Profiling

```bash
# Profiling с Xdebug
docker compose exec app php -d xdebug.mode=profile script.php
# Открыть кэшгрейс в KCachegrind / WebGrind
```

---

## Security checklist

Перед запуском в production:

- [ ] **`COOKIE_SEED`** — заменён на 64-символьную случайную строку
  ```bash
  openssl rand -hex 64
  ```
- [ ] **`APP_DEBUG=0`** — debug выключен
- [ ] **Nginx** — настроен deny на `.git`, `.env`, `composer.*`
- [ ] **SSL/TLS** — включён HTTPS (Let's Encrypt)
- [ ] **База данных** — пароль не равен дефолтному `fluxbb_secret`
- [ ] **Redis** — пароль (через `requirepass` в конфиге)
- [ ] **Mailer DSN** — заменён на реальный SMTP (не MailHog)
- [ ] **Rate limiter** — активен (60 запросов/мин/IP по умолчанию)
- [ ] **CSRF** — проверен на всех формах
- [ ] **BBCode XSS** — проверен (`<script>`, `javascript:` блокируются)
- [ ] **Сессии** — настроены на Redis или PDO (не file)
- [ ] **OPcache + JIT** — включён
- [ ] **Healthcheck** — доступен и отвечает 200

### Security Headers (автоматически)

```
Content-Security-Policy: default-src 'self'; img-src 'self' https:; ...
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
```

---

## Бэкап и восстановление

### PostgreSQL

```bash
# Бэкап
docker compose exec database pg_dump -U fluxbb fluxbb > backup_$(date +%Y%m%d).sql

# Восстановление
docker compose exec -T database psql -U fluxbb fluxbb < backup_20250101.sql

# Автоматический бэкап (cron)
0 3 * * * cd /opt/fluxbb && docker compose exec -T database pg_dump -U fluxbb fluxbb | gzip > backups/db_$(date +\%Y\%m\%d).sql.gz
```

### Redis

```bash
# Бэкап RDB (AOF)
docker compose exec redis redis-cli save
cp /var/lib/docker/volumes/fluxbb_redis_data/_data/dump.rdb backups/

# Восстановление
docker compose exec redis redis-cli FLUSHALL
cat backup.rdb | docker compose exec -T redis redis-cli --pipe
```

### Полный бэкап

```bash
#!/bin/bash
# backup.sh
BACKUP_DIR="/backups/fluxbb"
DATE=$(date +%Y%m%d_%H%M)

mkdir -p $BACKUP_DIR

# БД
docker compose exec -T database pg_dump -U fluxbb fluxbb | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Redis
docker compose exec redis redis-cli save
docker compose cp redis:/data/dump.rdb $BACKUP_DIR/redis_$DATE.rdb

# Файлы (аватарки, вложения)
tar czf $BACKUP_DIR/files_$DATE.tar.gz -C /opt/fluxbb public/uploads/

echo "Backup complete: $BACKUP_DIR"
```

---

## Траблшутинг

### Docker

```bash
# Логи
docker compose logs -f app
docker compose logs -f database
docker compose logs -f redis

# Войти в контейнер
docker compose exec app sh
docker compose exec app php -v
docker compose exec app php -m | grep -E 'redis|apcu|pdo'

# Проверить соединение с БД
docker compose exec app php -r "
    \$pdo = new PDO('pgsql:host=database;port=5432;dbname=fluxbb', 'fluxbb', 'fluxbb_secret');
    echo 'DB OK: ' . \$pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
"

# Проверить Redis
docker compose exec redis redis-cli ping

# Проверить кеш
docker compose exec app php -r "
    require 'vendor/autoload.php';
    \$c = require 'config/config.php';
    echo 'Config loaded: ' . count(\$c) . ' keys';
"
```

### База данных

```bash
# Статус миграций
docker compose exec app php console.php migrations:status

# Откатить миграцию
docker compose exec app php console.php migrations:migrate prev

# Создать новую миграцию
docker compose exec app php console.php migrations:diff
```

### Почта

```bash
# Проверить mailer DSN
docker compose exec app php -r "
    require 'vendor/autoload.php';
    echo getenv('MAILER_DSN') . PHP_EOL;
"

# Отправить тестовое письмо
docker compose exec app php -r "
    require 'vendor/autoload.php';
    \$m = new FluxBB\Shared\Infrastructure\Mail\FluxBBMailer(
        'smtp://mailhog:1025', 'test@fluxbb.local', 'Test'
    );
    echo \$m->send('user@example.com', 'Test', '<h1>Hello</h1>') ? 'Sent' : 'Failed';
"

# MailHog UI (dev): http://localhost:8025
```

### Производительность

```bash
# Медленные запросы — включить логирование
# В .env: QUERY_LOG=1

# Кеш прогреты?
docker compose exec app php console.php cache:warmup

# Индексы БД
docker compose exec database psql -U fluxbb -c "
    SELECT relname, seq_scan, seq_tup_read, idx_scan
    FROM pg_stat_user_tables
    ORDER BY seq_scan DESC;
"

# Размер базы
docker compose exec database psql -U fluxbb -c "
    SELECT pg_size_pretty(pg_database_size('fluxbb'));
"
```

### Частые проблемы

| Проблема | Решение |
|----------|---------|
| `Class "Redis" not found` | Установить `ext-redis` или `docker-php-ext-install redis` |
| `Connection refused` | Проверить `DB_HOST`, Redis host — в Docker это имя сервиса |
| `403 Forbidden` | CSRF токен истёк — обновить страницу |
| `429 Too Many Requests` | Rate limiter — подождать 60 секунд |
| Пустая страница | Проверить `APP_DEBUG=1` для ошибок |
| `No migrations to execute` | Уже выполнены — `php console.php migrations:status` |

---

## Статический анализ

```bash
# PHPStan (level max)
composer phpstan

# Psalm (level 5)
composer psalm

# PHP-CS-Fixer
composer cs

# Rector
composer rector

# Всё сразу
composer check
```

**Текущий статус:**
- PHPStan — [OK] No errors
- Psalm — No errors found!
- 102 PHPUnit теста, 158 assertions

---

## Тестирование

```bash
# Все тесты (unit + integration)
composer test

# Unit-тесты
composer test:unit

# Интеграционные тесты (требуется PostgreSQL)
composer test:integration

# Performance benchmarks
php vendor/bin/phpunit --group=performance

# С покрытием
php vendor/bin/phpunit --coverage-html=var/coverage
```

---

## Domain Scars (восстановлено)

| Domain Scar | BC | Статус | Issues |
|------------|-----|--------|--------|
| Edit timeout + permission | Post/Moderation | ✅ | #44, #105 |
| Flood control | Moderation | ✅ | #80, #93 |
| Ban IP mask (wildcard) | Moderation | ✅ | #34, #58, #219 |
| Topic subscription | Subscription | ✅ | #82, #96 |
| Password hash (Argon2id) | User | ✅ | #235 |
| Legacy password migration | Migrations | ✅ | — |
| BBCode XSS protection | Post | ✅ | #237 |
| Open redirect fix | Shared | ✅ | #116 |
| Nested lists parsing | Post | ✅ | #103 |
| PostgreSQL fulltext search | Search | ✅ | — |
| Ban cache (event-driven) | Moderation | ✅ | — |
| Email notifications | Subscription | ✅ | — |

---

## Лицензия

**GNU General Public License v2.0 or later** — соответствует оригинальному FluxBB/PunBB.  
См. [LICENSE](LICENSE).