# FluxBB Next

**Modern PHP forum engine** — a complete rewrite of FluxBB v1.5.11 (PHP 5.6) on a modern **PHP 8.4+** stack with Domain-Driven Design.

[![CI](https://github.com/dev993848/fluxbb-next/actions/workflows/ci.yml/badge.svg)](https://github.com/dev993848/fluxbb-next/actions/workflows/ci.yml)

---

## Table of Contents

- [Requirements](#requirements)
- [Quick Start (Docker)](#quick-start-docker)
- [Production Deployment (Docker)](#production-deployment-docker)
  - [Environment Variables](#environment-variables)
  - [Redis + MailHog](#redis--mailhog)
  - [Healthcheck](#healthcheck)
- [Installation Without Docker](#installation-without-docker)
- [Migrating from FluxBB 1.5](#migrating-from-fluxbb-15)
- [Architecture](#architecture)
- [Configuration](#configuration)
- [Performance Monitoring](#performance-monitoring)
- [Security Checklist](#security-checklist)
- [Backup and Restore](#backup-and-restore)
- [Troubleshooting](#troubleshooting)
- [Static Analysis](#static-analysis)
- [Testing](#testing)
- [License](#license)

---

## Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| PHP | 8.2 | **8.4+** (JIT) |
| PostgreSQL | 15 | **16** |
| Redis | 6 | **7** (cache + sessions) |
| Composer | 2.x | 2.7+ |
| Node (build only) | — | 20+ (for dev assets) |

**PHP extensions:**
- `pdo_pgsql`, `pdo_mysql` — database
- `mbstring`, `intl`, `zip` — required
- `opcache` — **required** (JIT)
- `redis` — Redis cache
- `apcu` — APCu cache (optional)

---

## Quick Start (Docker)

```bash
# 1. Clone
git clone https://github.com/dev993848/fluxbb-next.git
cd fluxbb-next

# 2. Start the environment (development)
docker compose up -d

# 3. Run migrations
docker compose exec app php console.php migrations:migrate

# 4. Open in browser
open http://localhost:8080
# Email UI (MailHog): http://localhost:8025
```

---

## Production Deployment (Docker)

### Quick Start

```bash
# 1. Clone
git clone https://github.com/dev993848/fluxbb-next.git
cd fluxbb-next

# 2. Replace COOKIE_SEED in .env
# Generate: openssl rand -hex 64
echo 'COOKIE_SEED='$(openssl rand -hex 64) >> .env

# 3. Build images
docker compose build --no-cache

# 4. Start
docker compose up -d

# 5. Database migrations
docker compose exec app php console.php migrations:migrate

# 6. Warm up caches (ban list, config)
docker compose exec app php console.php cache:warmup

# 7. Check status
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
      # SMTP (replace with real one)
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

### Environment Variables

All variables are set via `.env` or `environment:` in docker-compose.yml.

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_ENV` | `prod` | `dev`, `prod`, `test` |
| `APP_DEBUG` | `0` | Enable debug mode |
| **Database** | | |
| `DB_DRIVER` | `pdo_pgsql` | `pdo_pgsql` or `pdo_mysql` |
| `DB_HOST` | `127.0.0.1` | Database host |
| `DB_PORT` | `5432` | Port |
| `DB_NAME` | `fluxbb` | Database name |
| `DB_USER` | `fluxbb` | User |
| `DB_PASSWORD` | `fluxbb_secret` | Password |
| **Cache** | | |
| `CACHE_DSN` | `file://var/cache` | `redis://redis:6379/1`, `apcu://`, `file://...` |
| `CACHE_NAMESPACE` | `fluxbb_` | Cache key prefix |
| **Mailer** | | |
| `MAILER_DSN` | `native://default` | `smtp://user:pass@host:25`, `sendmail://default` |
| `MAILER_FROM` | `noreply@fluxbb.local` | Sender email |
| `MAILER_FROM_NAME` | `FluxBB Forum` | Sender name |
| **Auth** | | |
| `COOKIE_NAME` | `fluxbb_session` | Cookie name |
| `COOKIE_SEED` | — | **64-character string** (must change!) |
| `SESSION_DRIVER` | `database` | `database` (PDO) or `redis` |
| **Redis (sessions)** | | |
| `REDIS_HOST` | `redis` | Redis host (if `SESSION_DRIVER=redis`) |
| `REDIS_PORT` | `6379` | Redis port |

> **Important:** `COOKIE_SEED` must be unique per installation.
> ```bash
> openssl rand -hex 64  # generate
> ```

### Redis + Sessions

Redis is used for:
- **Cache** — `CACHE_DSN=redis://redis:6379/1` (PSR-16)
- **Sessions** — via `session.save_handler=redis` (configured in Dockerfile)
- **Message queue** — Messenger transport (optional)

Verify Redis:
```bash
docker compose exec redis redis-cli ping
# PONG

docker compose exec app php -r "echo extension_loaded('redis') ? 'OK' : 'MISS';"
# OK
```

### Mailer

**Development:**
```bash
# docker-compose.yml already includes MailHog
# All emails are available in the UI: http://localhost:8025
MAILER_DSN=smtp://mailhog:1025
```

**Production (Symfony Mailer):**
```bash
# SMTP
MAILER_DSN=smtp://user:pass@smtp.example.com:587

# Sendmail
MAILER_DSN=sendmail://default
```

**Without SMTP (fallback):**
```bash
MAILER_DSN=native://default  # uses PHP mail()
```
**Note:** `symfony/mailer` is installed optionally via Composer.
If the package is not installed — `mail()` is used.

### Healthcheck

The `/healthcheck` endpoint checks:
- PHP-FPM status (via Nginx)
- PostgreSQL connection (via `pg_isready`)
- Redis (via `redis-cli ping`)

```bash
curl -f http://localhost:8080/healthcheck
# HTTP 200 OK
```

---

## Installation Without Docker

### Ubuntu/Debian 24.04+

```bash
# 1. PHP 8.4 + extensions
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

# 4. Project
git clone https://github.com/dev993848/fluxbb-next.git /var/www/fluxbb
cd /var/www/fluxbb
cp .env.example .env
# Edit .env to match your database

# 5. Dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# 6. Migrations
php console.php migrations:migrate

# 7. Warm up cache
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

# 9. Configure OPcache + JIT
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

# Same as Ubuntu from here on
```

---

## Migrating from FluxBB 1.5

```bash
php console.php fluxbb:import \
    --old-db-url=pdo_mysql://user:pass@host:port/old_fluxbb \
    --prefix=forum_
```

The migrator automatically:
- Reads all 13 tables from the old FluxBB
- Transforms the schema into the new PostgreSQL
- Marks old passwords as `LEGACY_HASH:` — users reset on login
- Works transactionally (all-or-nothing)

**After migration:**
```bash
# Rebuild search index
php console.php migrations:migrate  # tsvector + triggers

# Warm up cache
php console.php cache:warmup

# Reset admin password
php console.php fluxbb:reset-admin-password new_password
```

---

## Architecture

```
src/
├── Shared/          # Shared Kernel (cache, DB, security, mailer)
├── Forum/           # Forum & Category BC
├── Topic/           # Topic BC
├── Post/            # Post BC + BBCode Parser
├── User/            # User & Auth BC
├── Moderation/      # Moderation BC (Bans, Flood, Reports)
├── Subscription/    # Subscription BC
├── Admin/           # Admin Panel (9 sections)
└── Search/          # Search BC (PostgreSQL fulltext)
```

### Technical Stack

| Component | Technology |
|-----------|-----------|
| Language | PHP 8.4+ |
| Routing | Symfony Routing 7 |
| DI Container | PHP-DI 7 |
| ORM | Doctrine DBAL 4 |
| Migrations | Doctrine Migrations 3 |
| Templates | Twig 3 |
| Database | PostgreSQL 16 (default) |
| Cache | Redis 7 / APCu / Filesystem (PSR-16) |
| Sessions | Redis / PDO-backed |
| Mail | Symfony Mailer / native mail() |
| Static Analysis | PHPStan level max + Psalm level 5 |
| CI | GitHub Actions |

### Bounded Contexts

Each BC contains:
- **Domain** — entities, value objects, specifications, events
- **Application** — commands/handlers
- **Infrastructure** — controllers, persistence

Communication between BCs is via **domain events** (PSR-14 Event Dispatcher).

---

## Configuration

### PHP Settings

Main settings are in `.env` (see the [table above](#environment-variables)).

Additional parameters in `config/config.php`:

```php
'db.charset' => 'utf8',
'forum.default_lang' => 'English',
'forum.default_style' => 'Air',
'forum.cookie_name' => $_SERVER['COOKIE_NAME'] ?? 'fluxbb_cookie',
```

### OPcache + JIT (Production)

Settings in `docker/php/Dockerfile`:

```ini
opcache.memory_consumption=256   # 128MB → 256MB for large forums
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000  # ~20000 PHP files
opcache.revalidate_freq=60       # check changes every 60s
opcache.jit=1255                 # JIT on-demand, tracing
opcache.jit_buffer_size=128M     # 128MB for JIT
```

### PHP-FPM Tuning

```ini
pm.max_children=50        # 50 workers
pm.start_servers=5
pm.min_spare_servers=5
pm.max_spare_servers=15
pm.max_requests=500       # recycle after 500 requests
```

For high-traffic forums, increase `pm.max_children` to 100-200 (depending on RAM).

### Redis Tuning

```bash
# Redis config in docker-compose.yml
redis:
  image: redis:7-alpine
  command: redis-server \
    --maxmemory 256mb \
    --maxmemory-policy allkeys-lru \
    --save 300 100 \
    --appendonly yes
```

---

## Performance Monitoring

### QueryMonitor

Built-in database query monitoring:

```php
<?php
$monitor = $container->get(\FluxBB\Shared\Infrastructure\Database\QueryMonitor::class);

// After query execution:
$report = $monitor->getBenchmarkReport();
// [
//   'queries' => 12,           // number of queries
//   'cache_hits' => 8,         // cache hits
//   'cache_misses' => 2,       // cache misses
//   'hit_rate' => 80.0,        // hit percentage
//   'slowest_query' => 'SELECT ...', // slowest query
//   'max_duration' => 0.0152,  // max duration in seconds
// ]
```

### Performance Benchmarks

```bash
php vendor/bin/phpunit --group=performance
```

Expected results:

| Operation | Throughput |
|-----------|-----------|
| BBCode parsing (1000x) | ~32,000 ops/sec |
| BBCode strip (2000x) | ~208,000 ops/sec |
| CSRF token (10,000x) | ~1,350,000 ops/sec |
| IP mask matching (200,000x) | ~3,260,000 checks/sec |
| Rate limiter (1000x) | ~2,570 checks/sec |

### Blackfire.io / Xdebug Profiling

```bash
# Profiling with Xdebug
docker compose exec app php -d xdebug.mode=profile script.php
# Open cachegrind in KCachegrind / WebGrind
```

---

## Security Checklist

Before going to production:

- [ ] **`COOKIE_SEED`** — replaced with a 64-character random string
  ```bash
  openssl rand -hex 64
  ```
- [ ] **`APP_DEBUG=0`** — debug is off
- [ ] **Nginx** — configured to deny `.git`, `.env`, `composer.*`
- [ ] **SSL/TLS** — HTTPS enabled (Let's Encrypt)
- [ ] **Database** — password is not the default `fluxbb_secret`
- [ ] **Redis** — password set (via `requirepass` in config)
- [ ] **Mailer DSN** — replaced with a real SMTP (not MailHog)
- [ ] **Rate limiter** — active (60 requests/min/IP by default)
- [ ] **CSRF** — verified on all forms
- [ ] **BBCode XSS** — verified (`<script>`, `javascript:` blocked)
- [ ] **Sessions** — configured with Redis or PDO (not file)
- [ ] **OPcache + JIT** — enabled
- [ ] **Healthcheck** — available and returns 200

### Security Headers (automatic)

```
Content-Security-Policy: default-src 'self'; img-src 'self' https:; ...
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
```

---

## Backup and Restore

### PostgreSQL

```bash
# Backup
docker compose exec database pg_dump -U fluxbb fluxbb > backup_$(date +%Y%m%d).sql

# Restore
docker compose exec -T database psql -U fluxbb fluxbb < backup_20250101.sql

# Automated backup (cron)
0 3 * * * cd /opt/fluxbb && docker compose exec -T database pg_dump -U fluxbb fluxbb | gzip > backups/db_$(date +\%Y\%m\%d).sql.gz
```

### Redis

```bash
# RDB backup (AOF)
docker compose exec redis redis-cli save
cp /var/lib/docker/volumes/fluxbb_redis_data/_data/dump.rdb backups/

# Restore
docker compose exec redis redis-cli FLUSHALL
cat backup.rdb | docker compose exec -T redis redis-cli --pipe
```

### Full Backup

```bash
#!/bin/bash
# backup.sh
BACKUP_DIR="/backups/fluxbb"
DATE=$(date +%Y%m%d_%H%M)

mkdir -p $BACKUP_DIR

# DB
docker compose exec -T database pg_dump -U fluxbb fluxbb | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Redis
docker compose exec redis redis-cli save
docker compose cp redis:/data/dump.rdb $BACKUP_DIR/redis_$DATE.rdb

# Files (avatars, attachments)
tar czf $BACKUP_DIR/files_$DATE.tar.gz -C /opt/fluxbb public/uploads/

echo "Backup complete: $BACKUP_DIR"
```

---

## Troubleshooting

### Docker

```bash
# Logs
docker compose logs -f app
docker compose logs -f database
docker compose logs -f redis

# Enter a container
docker compose exec app sh
docker compose exec app php -v
docker compose exec app php -m | grep -E 'redis|apcu|pdo'

# Check database connection
docker compose exec app php -r "
    \$pdo = new PDO('pgsql:host=database;port=5432;dbname=fluxbb', 'fluxbb', 'fluxbb_secret');
    echo 'DB OK: ' . \$pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
"

# Check Redis
docker compose exec redis redis-cli ping

# Check cache
docker compose exec app php -r "
    require 'vendor/autoload.php';
    \$c = require 'config/config.php';
    echo 'Config loaded: ' . count(\$c) . ' keys';
"
```

### Database

```bash
# Migration status
docker compose exec app php console.php migrations:status

# Rollback a migration
docker compose exec app php console.php migrations:migrate prev

# Create a new migration
docker compose exec app php console.php migrations:diff
```

### Mail

```bash
# Check mailer DSN
docker compose exec app php -r "
    require 'vendor/autoload.php';
    echo getenv('MAILER_DSN') . PHP_EOL;
"

# Send a test email
docker compose exec app php -r "
    require 'vendor/autoload.php';
    \$m = new FluxBB\Shared\Infrastructure\Mail\FluxBBMailer(
        'smtp://mailhog:1025', 'test@fluxbb.local', 'Test'
    );
    echo \$m->send('user@example.com', 'Test', '<h1>Hello</h1>') ? 'Sent' : 'Failed';
"

# MailHog UI (dev): http://localhost:8025
```

### Performance

```bash
# Slow queries — enable logging
# In .env: QUERY_LOG=1

# Cache warmed up?
docker compose exec app php console.php cache:warmup

# Database indexes
docker compose exec database psql -U fluxbb -c "
    SELECT relname, seq_scan, seq_tup_read, idx_scan
    FROM pg_stat_user_tables
    ORDER BY seq_scan DESC;
"

# Database size
docker compose exec database psql -U fluxbb -c "
    SELECT pg_size_pretty(pg_database_size('fluxbb'));
"
```

### Common Issues

| Problem | Solution |
|---------|----------|
| `Class "Redis" not found` | Install `ext-redis` or `docker-php-ext-install redis` |
| `Connection refused` | Check `DB_HOST`, Redis host — in Docker it's the service name |
| `403 Forbidden` | CSRF token expired — refresh the page |
| `429 Too Many Requests` | Rate limiter — wait 60 seconds |
| Blank page | Check `APP_DEBUG=1` for errors |
| `No migrations to execute` | Already applied — `php console.php migrations:status` |

---

## Static Analysis

```bash
# PHPStan (level max)
composer phpstan

# Psalm (level 5)
composer psalm

# PHP-CS-Fixer
composer cs

# Rector
composer rector

# All at once
composer check
```

**Current status:**
- PHPStan — [OK] No errors
- Psalm — No errors found!
- 102 PHPUnit tests, 158 assertions

---

## Testing

```bash
# All tests (unit + integration)
composer test

# Unit tests
composer test:unit

# Integration tests (requires PostgreSQL)
composer test:integration

# Performance benchmarks
php vendor/bin/phpunit --group=performance

# With coverage
php vendor/bin/phpunit --coverage-html=var/coverage
```

---

## Domain Scars (Recovered)

| Domain Scar | BC | Status | Issues |
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

## License

**GNU General Public License v2.0 or later** — consistent with the original FluxBB/PunBB.  
See [COPYING](COPYING).