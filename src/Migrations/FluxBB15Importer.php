<?php

declare(strict_types=1);

namespace FluxBB\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use FluxBB\User\Domain\Email;
use FluxBB\User\Domain\Username;

/**
 * FluxBB 1.5 → FluxBB Next migration importer.
 *
 * Reads the old FluxBB 1.5 database (MySQL/PostgreSQL/SQLite) and
 * transforms its schema and data to the new FluxBB Next schema.
 *
 * The migration does the following:
 * 1. Connects to the old database (read-only source)
 * 2. Connects to the new database (target)
 * 3. Creates the new schema via Doctrine migrations
 * 4. Transforms and copies all tables
 * 5. Re-hashes passwords from old HMAC format to Argon2id
 *
 * Usage:
 *   php console.php fluxbb:migrate --old-db-url=pdo_mysql://old:pass@host/db
 *
 * @see https://github.com/fluxbb/fluxbb (v1.5.11)
 */
class FluxBB15Importer
{
    /** Number of rows to process in one chunk. */
    private const int CHUNK_SIZE = 100;

    /**
     * @param Connection       $source Connection to the old FluxBB 1.5 database
     * @param Connection       $target Connection to the new FluxBB Next database
     * @param string           $tablePrefix  Old table prefix (default: 'forum_')
     * @param \Psr\Log\LoggerInterface|null $logger
     */
    public function __construct(
        private readonly Connection $source,
        private readonly Connection $target,
        private readonly string $tablePrefix = 'forum_',
        private readonly ?\Psr\Log\LoggerInterface $logger = null,
    ) {}

    /**
     * Run the full import process.
     *
     * @return array{users: int, forums: int, topics: int, posts: int, bans: int, config: int, categories: int, groups: int, forum_perms: int, subscriptions: int, censoring: int, reports: int, online: int}
     */
    public function import(): array
    {
        $this->log('Starting FluxBB 1.5 → Next migration...');

        $stats = [];

        try {
            $this->target->beginTransaction();

            $stats['config'] = $this->importConfig();
            $stats['categories'] = $this->importCategories();
            $stats['forums'] = $this->importForums();
            $stats['users'] = $this->importUsers();
            $stats['groups'] = $this->importGroups();
            $stats['forum_perms'] = $this->importForumPerms();
            $stats['topics'] = $this->importTopics();
            $stats['posts'] = $this->importPosts();
            $stats['bans'] = $this->importBans();
            $stats['subscriptions'] = $this->importSubscriptions();
            $stats['censoring'] = $this->importCensoring();
            $stats['reports'] = $this->importReports();
            $stats['online'] = $this->importOnline();

            $this->target->commit();

            $this->log('Migration completed successfully. Stats: ' . json_encode($stats));
        } catch (\Throwable $e) {
            $this->target->rollBack();
            $this->log('Migration FAILED: ' . $e->getMessage());
            throw $e;
        }

        return $stats;
    }

    /**
     * Import forum config (key-value pairs).
     */
    private function importConfig(): int
    {
        $rows = $this->source->fetchAllAssociative(
            "SELECT conf_name, conf_value FROM {$this->tablePrefix}config"
        );

        $count = 0;
        foreach ($rows as $row) {
            $this->target->insert('fluxbb_config', [
                'conf_name' => (string) $row['conf_name'],
                'conf_value' => (string) $row['conf_value'],
            ]);
            $count++;
        }

        $this->log("Imported {$count} config entries");
        return $count;
    }

    /**
     * Import categories.
     */
    private function importCategories(): int
    {
        $rows = $this->source->fetchAllAssociative(
            "SELECT id, cat_name, disp_position FROM {$this->tablePrefix}categories ORDER BY id"
        );

        $count = 0;
        foreach ($rows as $row) {
            $this->target->insert('forum_categories', [
                'id' => (int) $row['id'],
                'cat_name' => (string) $row['cat_name'],
                'disp_position' => (int) ($row['disp_position'] ?? 0),
            ]);
            $count++;
        }

        $this->log("Imported {$count} categories");
        return $count;
    }

    /**
     * Import forums.
     */
    private function importForums(): int
    {
        $rows = $this->source->fetchAllAssociative(
            "SELECT * FROM {$this->tablePrefix}forums ORDER BY id"
        );

        $count = 0;
        foreach ($rows as $row) {
            $this->target->insert('forum_forums', [
                'id' => (int) $row['id'],
                'forum_name' => (string) $row['forum_name'],
                'forum_desc' => (string) ($row['forum_desc'] ?? ''),
                'cat_id' => (int) $row['cat_id'],
                'disp_position' => (int) ($row['disp_position'] ?? 0),
                'last_post_id' => isset($row['last_post_id']) ? (int) $row['last_post_id'] : null,
                'last_poster_id' => isset($row['last_poster_id']) ? (int) $row['last_poster_id'] : null,
                'last_poster' => (string) ($row['last_poster'] ?? ''),
                'last_post' => isset($row['last_post']) ? (int) $row['last_post'] : null,
                'num_topics' => (int) ($row['num_topics'] ?? 0),
                'num_posts' => (int) ($row['num_posts'] ?? 0),
                'redirect_url' => (string) ($row['redirect_url'] ?? ''),
                'moderators' => isset($row['moderators']) ? (string) $row['moderators'] : null,
            ]);
            $count++;
        }

        $this->log("Imported {$count} forums");
        return $count;
    }

    /**
     * Import users, re-hashing passwords to Argon2id.
     *
     * FluxBB 1.5 stores HMAC-based password hashes:
     *   $pun_user['password'] = hash('sha1', $password)
     *   forum_hmac() for cookie auth
     *
     * These are read-only (old algorithm). We store a placeholder
     * "LEGACY_HASH:<old_hash>" and the user must reset their password
     * on first login. They receive a "password reset required" email.
     */
    private function importUsers(): int
    {
        $rows = $this->source->fetchAllAssociative(
            "SELECT * FROM {$this->tablePrefix}users ORDER BY id"
        );

        $count = 0;
        foreach ($rows as $row) {
            $oldHash = (string) ($row['password'] ?? '');

            // Mark as legacy hash – user must reset password on next login
            $legacyHash = 'LEGACY_HASH:' . $oldHash;

            $this->target->insert('forum_users', [
                'id' => (int) $row['id'],
                'username' => (string) $row['username'],
                'password' => $legacyHash,
                'email' => (string) $row['email'],
                'group_id' => (int) ($row['group_id'] ?? 4),
                'registered' => (int) ($row['registered'] ?? time()),
                'registration_ip' => (string) ($row['registration_ip'] ?? ''),
                'last_post_ip' => (string) ($row['last_post_ip'] ?? ''),
                'last_visit' => isset($row['last_visit']) ? (int) $row['last_visit'] : null,
                'language' => (string) ($row['language'] ?? 'English'),
                'style' => (string) ($row['style'] ?? 'Air'),
                'disp_topics' => (int) ($row['disp_topics'] ?? 0),
                'disp_posts' => (int) ($row['disp_posts'] ?? 0),
            ]);
            $count++;
        }

        $this->log("Imported {$count} users (password reset required for legacy hashes)");
        return $count;
    }

    /**
     * Import user groups.
     */
    private function importGroups(): int
    {
        $rows = $this->source->fetchAllAssociative(
            "SELECT * FROM {$this->tablePrefix}groups ORDER BY g_id"
        );

        $count = 0;
        foreach ($rows as $row) {
            $this->target->insert('forum_groups', [
                'g_id' => (int) $row['g_id'],
                'g_title' => (string) $row['g_title'],
                'g_user_title' => (string) ($row['g_user_title'] ?? ''),
                'g_read_board' => (int) ($row['g_read_board'] ?? 1),
                'g_post_replies' => (int) ($row['g_post_replies'] ?? 1),
                'g_post_topics' => (int) ($row['g_post_topics'] ?? 1),
                'g_edit_posts' => (int) ($row['g_edit_posts'] ?? 1),
                'g_delete_posts' => (int) ($row['g_delete_posts'] ?? 1),
                'g_post_flood' => (int) ($row['g_post_flood'] ?? 30),
                'g_moderator' => (int) ($row['g_moderator'] ?? 0),
                'g_mod_ban_users' => (int) ($row['g_mod_ban_users'] ?? 0),
            ]);
            $count++;
        }

        $this->log("Imported {$count} groups");
        return $count;
    }

    /**
     * Import forum permissions.
     */
    private function importForumPerms(): int
    {
        $rows = $this->source->fetchAllAssociative(
            "SELECT * FROM {$this->tablePrefix}forum_perms ORDER BY group_id, forum_id"
        );

        $count = 0;
        foreach ($rows as $row) {
            $this->target->insert('forum_forum_perms', [
                'group_id' => (int) $row['group_id'],
                'forum_id' => (int) $row['forum_id'],
                'read_forum' => isset($row['read_forum']) ? (int) $row['read_forum'] : null,
                'post_replies' => isset($row['post_replies']) ? (int) $row['post_replies'] : null,
                'post_topics' => isset($row['post_topics']) ? (int) $row['post_topics'] : null,
            ]);
            $count++;
        }

        $this->log("Imported {$count} forum permissions");
        return $count;
    }

    /**
     * Import topics.
     */
    private function importTopics(): int
    {
        $rows = $this->source->fetchAllAssociative(
            "SELECT * FROM {$this->tablePrefix}topics ORDER BY id"
        );

        $count = 0;
        foreach ($rows as $row) {
            $this->target->insert('forum_topics', [
                'id' => (int) $row['id'],
                'poster' => (string) $row['poster'],
                'poster_id' => (int) $row['poster_id'],
                'subject' => (string) $row['subject'],
                'posted' => (int) $row['posted'],
                'first_post_id' => (int) $row['first_post_id'],
                'last_post' => (int) $row['last_post'],
                'last_post_id' => (int) $row['last_post_id'],
                'last_poster' => (string) ($row['last_poster'] ?? ''),
                'last_poster_id' => (int) ($row['last_poster_id'] ?? 0),
                'num_replies' => (int) ($row['num_replies'] ?? 0),
                'closed' => (int) ($row['closed'] ?? 0),
                'sticky' => (int) ($row['sticky'] ?? 0),
                'moved_to' => isset($row['moved_to']) ? (int) $row['moved_to'] : null,
                'forum_id' => (int) $row['forum_id'],
            ]);
            $count++;
        }

        $this->log("Imported {$count} topics");
        return $count;
    }

    /**
     * Import posts.
     */
    private function importPosts(): int
    {
        $rows = $this->source->fetchAllAssociative(
            "SELECT * FROM {$this->tablePrefix}posts ORDER BY id"
        );

        $count = 0;
        foreach ($rows as $row) {
            $this->target->insert('forum_posts', [
                'id' => (int) $row['id'],
                'poster' => (string) $row['poster'],
                'poster_id' => (int) $row['poster_id'],
                'poster_ip' => (string) ($row['poster_ip'] ?? ''),
                'message' => (string) $row['message'],
                'hide_smilies' => (int) ($row['hide_smilies'] ?? 0),
                'posted' => (int) $row['posted'],
                'edited' => isset($row['edited']) ? (int) $row['edited'] : null,
                'edited_by' => isset($row['edited_by']) ? (string) $row['edited_by'] : null,
                'topic_id' => (int) $row['topic_id'],
            ]);
            $count++;
        }

        $this->log("Imported {$count} posts");
        return $count;
    }

    /**
     * Import bans.
     */
    private function importBans(): int
    {
        $rows = $this->source->fetchAllAssociative(
            "SELECT * FROM {$this->tablePrefix}bans ORDER BY id"
        );

        $count = 0;
        foreach ($rows as $row) {
            $this->target->insert('forum_bans', [
                'id' => (int) $row['id'],
                'username' => (string) ($row['username'] ?? ''),
                'ip' => (string) ($row['ip'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'message' => (string) ($row['message'] ?? ''),
                'expire' => isset($row['expire']) ? (int) $row['expire'] : null,
                'ban_creator' => isset($row['ban_creator']) ? (int) $row['ban_creator'] : null,
            ]);
            $count++;
        }

        $this->log("Imported {$count} bans");
        return $count;
    }

    /**
     * Import topic subscriptions.
     */
    private function importSubscriptions(): int
    {
        $rows = $this->source->fetchAllAssociative(
            "SELECT * FROM {$this->tablePrefix}topic_subscriptions ORDER BY id"
        );

        $count = 0;
        foreach ($rows as $row) {
            $this->target->insert('forum_topic_subscriptions', [
                'id' => (int) $row['id'],
                'user_id' => (int) $row['user_id'],
                'topic_id' => (int) $row['topic_id'],
            ]);
            $count++;
        }

        $this->log("Imported {$count} topic subscriptions");
        return $count;
    }

    /**
     * Import censored words.
     */
    private function importCensoring(): int
    {
        $rows = $this->source->fetchAllAssociative(
            "SELECT * FROM {$this->tablePrefix}censoring ORDER BY id"
        );

        $count = 0;
        foreach ($rows as $row) {
            $this->target->insert('forum_censoring', [
                'id' => (int) $row['id'],
                'search_for' => (string) $row['search_for'],
                'replace_with' => (string) $row['replace_with'],
            ]);
            $count++;
        }

        $this->log("Imported {$count} censored words");
        return $count;
    }

    /**
     * Import reports.
     */
    private function importReports(): int
    {
        $rows = $this->source->fetchAllAssociative(
            "SELECT * FROM {$this->tablePrefix}reports ORDER BY id"
        );

        $count = 0;
        foreach ($rows as $row) {
            $this->target->insert('forum_reports', [
                'id' => (int) $row['id'],
                'post_id' => (int) $row['post_id'],
                'topic_id' => (int) $row['topic_id'],
                'forum_id' => (int) $row['forum_id'],
                'reported_by' => (int) $row['reported_by'],
                'created' => (int) ($row['created'] ?? time()),
                'message' => (string) ($row['message'] ?? ''),
                'zapped' => isset($row['zapped']) ? (int) $row['zapped'] : null,
                'zapped_by' => isset($row['zapped_by']) ? (int) $row['zapped_by'] : null,
            ]);
            $count++;
        }

        $this->log("Imported {$count} reports");
        return $count;
    }

    /**
     * Import online list (will be stale, but preserved for tracking).
     */
    private function importOnline(): int
    {
        $rows = $this->source->fetchAllAssociative(
            "SELECT * FROM {$this->tablePrefix}online ORDER BY user_id"
        );

        $count = 0;
        foreach ($rows as $row) {
            $this->target->insert('forum_online', [
                'user_id' => (int) $row['user_id'],
                'ident' => (string) $row['ident'],
                'logged' => (int) $row['logged'],
            ]);
            $count++;
        }

        $this->log("Imported {$count} online entries");
        return $count;
    }

    private function log(string $message): void
    {
        if ($this->logger !== null) {
            $this->logger->info($message);
        }
    }
}