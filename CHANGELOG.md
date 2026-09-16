# Change Log

All notable changes to this project will be documented in this file.

## [1.0.0-alpha] — 2025-01-01

### Added
- **Full rewrite** from FluxBB v1.5.11 procedural PHP 5.6 → PHP 8.4+ DDD architecture
- 7 Bounded Contexts: Shared, Forum, Topic, Post, User, Moderation, Subscription
- **Shared Kernel:** Entity, AggregateRoot, DomainEvent, ValueObject, PSR-14 SimpleEventDispatcher
- **Security:** CSRF middleware, rate limiter, cookie auth (HMAC-signed), session auth, Argon2id password hashing, security headers (CSP, HSTS, XFO)
- **Password reset** flow with email-token verification
- **Admin panel:** Dashboard, Users (list/delete), Bans (CRUD with IP wildcard), Options (config editor), Groups (permissions)
- **Search controller** with Twig form (PostgreSQL fulltext search ready)
- **BBCode Parser:** 11 tags (b, i, u, s, url, img, quote, code, list, color, size)
- **FluxBB 1.5 legacy importer:** Full migration for all 13 tables, legacy password marker
- **Initial Doctrine Migration:** PostgreSQL schema with all tables and sequences
- **Console application:** Symfony Console for migrations (`console.php`)
- **Docker:** PHP 8.4-fpm-alpine + Nginx 1.27 + PostgreSQL 16, healthchecks, OPcache, PHP-FPM tuning
- **CI:** GitHub Actions with static analysis (PHP-CS-Fixer → PHPStan → Psalm → Rector) gating tests (PHPUnit on PostgreSQL)

### Domain Scars Recovered
- #44/#105: Edit timeout with CanEditPost specification
- #80/#93: Flood control with group-based intervals, admin/mod exemption
- #58: IP mask bans with wildcard support (`192.168.*.*`)
- #82/#96: Topic subscription with subscribe/unsubscribe handlers
- #235: Legacy password hash migration

### Infrastructure
- PHP 8.4+ type-safe code (strict types, readonly properties, enums, named arguments)
- PHPStan level max — [OK] No errors (baseline for Doctrine mixed types)
- Psalm error level 5 — [OK] No errors (1 baseline entry for DBAL)
- PHPUnit 60 tests / 99 assertions — [OK]
- PostgreSQL 16 as default database
- PSR-16 FileCache with runtime memory cache layer
- PSR-20 ClockInterface (SystemClock)
- .env configuration via vlucas/phpdotenv

### Changed from FluxBB 1.5
- Database: PostgreSQL 16 (MySQL optional via Doctrine DBAL)
- Password hashing: SHA1 → Argon2id via `password_hash()`
- Auth: HMAC cookie + session-based (Symfony HttpFoundation)
- Template engine: PHP templates → Twig 3
- Architecture: Procedural → DDD with Bounded Contexts
- Routing: Manual path parsing → Symfony Routing 7
- DI: Global variables → PHP-DI 7 autowiring

### Removed
- Legacy PunBB template engine (PHP-in-HTML)
- Global `$db`, `$pun_user`, `$pun_config` variables
- `include/common.php` bootstrap
- MySQL as default (configurable via env)