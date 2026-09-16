# AGENTS.md — Инструкции для LLM-координации

## Концепция

Этот проект переписывается LLM-агентами. Каждый агент автономен, работает
в своём bounded context, и общается с другими агентами через события (Event Bus).

**Архитектура агентов:**

```
                    ┌───────────────┐
                    │  Architect     │
                    │  (Supervisor)  │
                    └───────┬───────┘
                            │
          ┌─────────────────┼─────────────────┐
          │                 │                 │
   ┌──────▼─────┐    ┌─────▼──────┐   ┌──────▼─────┐
   │ User/Auth  │    │    Post    │   │ Moderation  │
   │   Agent    │    │   Agent    │   │   Agent     │
   └──────┬─────┘    └─────┬──────┘   └──────┬─────┘
          │                 │                 │
   ┌──────▼─────┐    ┌─────▼──────┐   ┌──────▼─────┐
   │  Forum/Cat │    │    Topic   │   │Subscription│
   │   Agent    │    │   Agent    │   │   Agent     │
   └────────────┘    └────────────┘   └────────────┘
```

## Роли Агентов

### Architect (Supervisor)
- **Отвечает за:** Структуру проекта, ADR, маршрутизацию, общую архитектуру
- **Инструменты:** PHP 8.4, Symfony Routing, PHP-DI, Twig, Doctrine DBAL
- **Читает:** `project/SPECS.md`, `project/PLAN.md`, `project/ADR/*`
- **Создаёт:** src/Shared, config/, public/index.php, docker-compose.yml

### User/Auth Agent
- **Отвечает за:** Регистрацию, логин, профиль, пароли, cookie
- **BC:** `src/Forum/User/` (в идеале `src/User/`)
- **Читает:** `include/common.php` (check_cookie), `login.php`, `register.php`, `profile.php`
- **Восстанавливает:** Issues #182, #47, #235 (password hash fix)
- **Порождает события:** `UserRegistered`, `UserLoggedIn`, `PasswordChanged`

### Forum & Category Agent
- **Отвечает за:** Категории, форумы, их отображение
- **BC:** `src/Forum/`
- **Читает:** `admin_forums.php`, `admin_categories.php`, `index.php` (forum list)
- **Порождает события:** `ForumCreated`, `ForumUpdated`, `ForumDeleted`

### Topic Agent
- **Отвечает за:** Темы, создание, отображение, навигация
- **BC:** `src/Topic/`
- **Читает:** `viewtopic.php`, `viewforum.php`, `post.php` (topic creation)
- **Порождает события:** `TopicCreated`, `TopicMoved`, `TopicClosed`

### Post Agent
- **Отвечает за:** Посты, BBcode parser, редактирование с таймаутом
- **BC:** `src/Post/`
- **Domain Scars:**
  - **Edit timeout:** `o_edit_timeout` — если время с `post.posted` превышает лимит, запрет
  - **Edit permission:** владелец поста, админ или модератор могут редактировать
  - **flood check:** взаимодействие с Moderation Agent
- **Читает:** `post.php`, `edit.php`, `include/parser.php`, `include/search_idx.php`
- **Восстанавливает:** Issues #44, #105, #103 (nested lists parsing)
- **Порождает события:** `PostCreated`, `PostEdited`, `PostDeleted`

### Moderation Agent
- **Отвечает за:** Баны, флуд-контроль, репорты, модерацию
- **BC:** `src/Moderation/`
- **Domain Scars (Critical):**
  - **Flood control:** `g_post_flood` из группы + `last_post` из user
  - **Ban IP mask:** `192.168.*.*` wildcard, проверка при каждом запросе
  - **Ban cache:** кеширование для производительности
  - **Report flood:** защита от спама репортов
- **Читает:** `admin_bans.php`, `moderate.php`, `admin_reports.php`, `include/cache.php`
- **Восстанавливает:** Issues #80, #93, #34, #58, #219
- **Порождает события:** `UserBanned`, `UserReported`, `PostReported`, `FloodDetected`

### Subscription Agent
- **Отвечает за:** Подписки на темы, email-уведомления
- **BC:** `src/Subscription/`
- **Читает:** `post.php` (subscribe), `email.php`, `include/email.php`
- **Восстанавливает:** Issues #82, #96, #31
- **Подписывается на:** `PostCreated` (из Post BC)
- **Порождает события:** `TopicSubscribed`, `TopicUnsubscribed`, `NotificationSent`

## Протокол взаимодействия

### 1. Каждый агент работает в своей директории
```
src/
├── Forum/         ← Forum Agent
├── Topic/         ← Topic Agent  
├── Post/          ← Post Agent
├── User/          ← User Agent
├── Moderation/    ← Moderation Agent
├── Subscription/  ← Subscription Agent
└── Shared/        ← Architect
```

### 2. Агенты общаются через события
```php
// После создания поста Post Agent публикует событие
$eventBus->publish(new PostCreated(
    postId: $postId,
    topicId: $topicId,
    userId: $userId,
    occurredAt: new \DateTimeImmutable()
));

// Subscription Agent подписан на это событие
class NotifySubscribersOnNewPost
{
    public function __invoke(PostCreated $event): void
    {
        // ... нотификация подписчиков темы
    }
}
```

### 3. Каждый агент пишет тесты ДО изменения логики (TDD)

### 4. Domain Scar Recovery Protocol
При восстановлении доменного шрама из issue-трекера:

```
ШАГ 1: Найти issue в `project/PLAN.md` → Матрица Domain Scar Coverage
ШАГ 2: Прочитать исходный код, где scar был реализован
ШАГ 3: Прочитать issue-тред (возможно, несколько страниц)
ШАГ 4: Написать тест, описывающий ожидаемое поведение
ШАГ 5: Реализовать Specification/Service в BC
ШАГ 6: Запустить тест — должен пройти
ШАГ 7: Задокументировать восстановление
ШАГ 8: Commit с сообщением: `fixes #N: восстановлен domain scar <name>`
```

### 5. Правила для агентов

| Правило | Описание |
|---------|----------|
| **Не ломай BC** | Не изменяй код в чужом BC без согласования |
| **События — граница** | Если нужно действие в другом BC — публикуй событие |
| **Тесты прежде всего** | Никакой код не принимается без тестов |
| **Read домена** | Каждый агент должен прочитать весь свой BC из исходников |
| **Один issue = один commit** | Чистая история для отслеживания |
| **CI не красный** | Зелёный CI — требование для merge |
| **Документируй шрамы** | Каждый восстановленный scar — запись в PLAN.md |

### 6. Приоритеты агентов по фазам

```
Фаза 0-1: Architect (скелет, DI, routing, ci/cd)
Фаза 2:   Architect (Shared Kernel)
Фаза 3:   User Agent (login, register, profile)
Фаза 4:   Forum Agent (forums, categories)
Фаза 5:   Topic Agent + Post Agent (topics, posts, parser)
Фаза 6:   Moderation Agent (domain scars)
Фаза 7:   Subscription Agent (subscriptions)
Фаза 8:   Admin — все агенты (admin panels)
Фаза 9:   Post Agent + Architect (search, cache)
Фаза 10:  ВСЕ (polish, docs, security)
```

### 7. Prompt Template для старта нового агента

```
Ты — {Agent Name} (src/{BC}/). 
Твоя ответственность: {описание BC}.
Ты читаешь исходный код из: {list of files}.
Ты восстанавливаешь domain scars: {issue numbers}.
Ты порождаешь события: {event list}.
Ты подписываешься на события: {event list}.

Фаза проекта: {phase}.
Твоя задача сейчас: {task}.

Технический стек: PHP 8.4, {frameworks}.
Помни: тесты до кода, один issue — один commit.
```

## Восстановление из issue-трекера

### Как читать issue для восстановления domain scars

1. **Старые issues** (до 2014 на fluxbb.org) — часто содержат обсуждение edge cases.
   Читать полностью, включая closed PRs.

2. **Новые issues** (GitHub, 2015+) — более формальные, часто с кодом.
   Обращать внимание на комментарии от контрибьюторов (franzliedke, Quy, adaur, Visman).

3. **Pull Requests** — лучший источник правды о том, как scar должен работать.
   Смотреть diff, читать обсуждение.

4. **CVE Issues** (#237, #116, #241) — имеют приоритет, исправлять немедленно в Phase 3.

### Список для первичного восстановления (по приоритету)

```json
[
  {"id": 237, "type": "security", "file": "parser.php", "scar": "XSS via BBCode"},
  {"id": 116, "type": "security", "file": "misc.php", "scar": "Open redirect"},
  {"id": 235, "type": "bug", "file": "profile.php", "scar": "Wrong password hash"},
  {"id": 241, "type": "security", "file": "login.php", "scar": "Password DoS"},
  {"id": 80,  "type": "domain", "file": "moderate.php", "scar": "Report flood"},
  {"id": 93,  "type": "domain", "file": "post.php", "scar": "Flood by group"},
  {"id": 44,  "type": "domain", "file": "edit.php", "scar": "Edit timeout"},
  {"id": 105, "type": "domain", "file": "edit.php", "scar": "Mod can edit"},
  {"id": 34,  "type": "domain", "file": "admin_bans.php", "scar": "Ban search"},
  {"id": 58,  "type": "domain", "file": "admin_bans.php", "scar": "Ban IP mask"},
  {"id": 219, "type": "bug", "file": "admin_bans.php", "scar": "Ban list order"},
  {"id": 82,  "type": "domain", "file": "post.php", "scar": "Subscription"},
  {"id": 96,  "type": "domain", "file": "email.php", "scar": "Notif on reply"},
  {"id": 230, "type": "feature", "file": "profile.php", "scar": "Timezone"}
]
```

## Структура промпта для quick start

```
/fluxbb-next — корень проекта
/project/SPECS.md — спецификации
/project/PLAN.md — план с таблицей шрамов
/project/ADR/ — архитектурные решения
/project/AGENTS.md — эта инструкция

Исходная кодовая база для анализа:
- {file_list}

Начни с {first_task}.
```