<?php

declare(strict_types=1);

namespace FluxBB\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema for FluxBB Next.
 *
 * Creates all core tables matching the original FluxBB 1.5 schema,
 * adapted for PostgreSQL with proper types.
 */
final class Version20250101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create initial FluxBB Next schema (PostgreSQL)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE forum_categories_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE forum_forums_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE forum_topics_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE forum_posts_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE forum_users_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE forum_bans_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE forum_reports_id_seq INCREMENT BY 1 MINVALUE 1 START 1');

        $this->addSql('CREATE TABLE forum_categories (
            id INT NOT NULL DEFAULT nextval(\'forum_categories_id_seq\'),
            cat_name VARCHAR(80) NOT NULL,
            disp_position INT NOT NULL DEFAULT 0,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE TABLE forum_groups (
            g_id INT NOT NULL,
            g_title VARCHAR(50) NOT NULL,
            g_user_title VARCHAR(50) DEFAULT NULL,
            g_read_board INT NOT NULL DEFAULT 1,
            g_post_replies INT NOT NULL DEFAULT 1,
            g_post_topics INT NOT NULL DEFAULT 1,
            g_edit_posts INT NOT NULL DEFAULT 1,
            g_delete_posts INT NOT NULL DEFAULT 1,
            g_post_flood INT NOT NULL DEFAULT 30,
            g_moderator INT NOT NULL DEFAULT 0,
            g_mod_ban_users INT NOT NULL DEFAULT 0,
            g_mod_promote_users INT NOT NULL DEFAULT 0,
            PRIMARY KEY(g_id)
        )');

        $this->addSql('CREATE TABLE forum_users (
            id INT NOT NULL DEFAULT nextval(\'forum_users_id_seq\'),
            group_id INT NOT NULL DEFAULT 4,
            username VARCHAR(200) NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(80) NOT NULL,
            registered INT NOT NULL,
            registration_ip VARCHAR(45) NOT NULL DEFAULT \'\',
            last_post_ip VARCHAR(45) NOT NULL DEFAULT \'\',
            last_visit INT DEFAULT NULL,
            language VARCHAR(50) NOT NULL DEFAULT \'English\',
            style VARCHAR(50) NOT NULL DEFAULT \'Air\',
            disp_topics INT DEFAULT NULL,
            disp_posts INT DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX idx_users_username ON forum_users (username)');
        $this->addSql('CREATE UNIQUE INDEX idx_users_email ON forum_users (email)');

        $this->addSql('CREATE TABLE forum_forums (
            id INT NOT NULL DEFAULT nextval(\'forum_forums_id_seq\'),
            forum_name VARCHAR(80) NOT NULL,
            forum_desc TEXT DEFAULT NULL,
            cat_id INT NOT NULL,
            disp_position INT NOT NULL DEFAULT 0,
            last_post_id INT DEFAULT NULL,
            last_poster_id INT DEFAULT NULL,
            last_poster VARCHAR(200) DEFAULT NULL,
            last_post INT DEFAULT NULL,
            num_topics INT NOT NULL DEFAULT 0,
            num_posts INT NOT NULL DEFAULT 0,
            redirect_url VARCHAR(100) NOT NULL DEFAULT \'\',
            moderators TEXT DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_forums_cat ON forum_forums (cat_id)');

        $this->addSql('CREATE TABLE forum_topics (
            id INT NOT NULL DEFAULT nextval(\'forum_topics_id_seq\'),
            poster VARCHAR(200) NOT NULL,
            poster_id INT NOT NULL,
            subject VARCHAR(255) NOT NULL,
            posted INT NOT NULL,
            first_post_id INT NOT NULL DEFAULT 0,
            last_post INT NOT NULL,
            last_post_id INT NOT NULL DEFAULT 0,
            last_poster VARCHAR(200) DEFAULT NULL,
            last_poster_id INT NOT NULL DEFAULT 0,
            num_replies INT NOT NULL DEFAULT 0,
            closed INT NOT NULL DEFAULT 0,
            sticky INT NOT NULL DEFAULT 0,
            moved_to INT DEFAULT NULL,
            forum_id INT NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_topics_forum ON forum_topics (forum_id)');
        $this->addSql('CREATE INDEX idx_topics_last_post ON forum_topics (last_post)');

        $this->addSql('CREATE TABLE forum_posts (
            id INT NOT NULL DEFAULT nextval(\'forum_posts_id_seq\'),
            poster VARCHAR(200) NOT NULL,
            poster_id INT NOT NULL,
            poster_ip VARCHAR(45) NOT NULL DEFAULT \'\',
            message TEXT NOT NULL,
            hide_smilies INT NOT NULL DEFAULT 0,
            posted INT NOT NULL,
            edited INT DEFAULT NULL,
            edited_by VARCHAR(200) DEFAULT NULL,
            topic_id INT NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_posts_topic ON forum_posts (topic_id)');
        $this->addSql('CREATE INDEX idx_posts_posted ON forum_posts (posted)');

        $this->addSql('CREATE TABLE forum_bans (
            id INT NOT NULL DEFAULT nextval(\'forum_bans_id_seq\'),
            username VARCHAR(200) DEFAULT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            email VARCHAR(80) DEFAULT NULL,
            message VARCHAR(255) DEFAULT NULL,
            expire INT DEFAULT NULL,
            creator_id INT DEFAULT NULL,
            created_at INT NOT NULL DEFAULT EXTRACT(EPOCH FROM NOW()),
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_bans_ip ON forum_bans (ip)');

        $this->addSql('CREATE TABLE forum_forum_perms (
            group_id INT NOT NULL,
            forum_id INT NOT NULL,
            read_forum INT DEFAULT NULL,
            post_replies INT DEFAULT NULL,
            post_topics INT DEFAULT NULL,
            PRIMARY KEY(group_id, forum_id)
        )');

        $this->addSql('CREATE TABLE forum_topic_subscriptions (
            id INT NOT NULL,
            user_id INT NOT NULL,
            topic_id INT NOT NULL,
            subscribed_at INT NOT NULL DEFAULT EXTRACT(EPOCH FROM NOW()),
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_subscriptions_user ON forum_topic_subscriptions (user_id)');
        $this->addSql('CREATE INDEX idx_subscriptions_topic ON forum_topic_subscriptions (topic_id)');

        $this->addSql('CREATE TABLE forum_censoring (
            id INT NOT NULL,
            search_for VARCHAR(60) NOT NULL,
            replace_with VARCHAR(60) NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE TABLE forum_reports (
            id INT NOT NULL DEFAULT nextval(\'forum_reports_id_seq\'),
            post_id INT NOT NULL,
            topic_id INT NOT NULL,
            forum_id INT NOT NULL,
            reported_by INT NOT NULL,
            created INT NOT NULL,
            message TEXT DEFAULT NULL,
            zapped INT DEFAULT NULL,
            zapped_by INT DEFAULT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE TABLE forum_online (
            user_id INT NOT NULL,
            ident VARCHAR(200) NOT NULL,
            logged INT NOT NULL,
            idle INT NOT NULL DEFAULT 0,
            PRIMARY KEY(user_id)
        )');

        $this->addSql('CREATE TABLE forum_config (
            conf_name VARCHAR(255) NOT NULL,
            conf_value TEXT NOT NULL,
            PRIMARY KEY(conf_name)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS forum_categories CASCADE');
        $this->addSql('DROP TABLE IF EXISTS forum_groups CASCADE');
        $this->addSql('DROP TABLE IF EXISTS forum_users CASCADE');
        $this->addSql('DROP TABLE IF EXISTS forum_forums CASCADE');
        $this->addSql('DROP TABLE IF EXISTS forum_topics CASCADE');
        $this->addSql('DROP TABLE IF EXISTS forum_posts CASCADE');
        $this->addSql('DROP TABLE IF EXISTS forum_bans CASCADE');
        $this->addSql('DROP TABLE IF EXISTS forum_forum_perms CASCADE');
        $this->addSql('DROP TABLE IF EXISTS forum_topic_subscriptions CASCADE');
        $this->addSql('DROP TABLE IF EXISTS forum_censoring CASCADE');
        $this->addSql('DROP TABLE IF EXISTS forum_reports CASCADE');
        $this->addSql('DROP TABLE IF EXISTS forum_online CASCADE');
        $this->addSql('DROP TABLE IF EXISTS forum_config CASCADE');

        $this->addSql('DROP SEQUENCE IF EXISTS forum_categories_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE IF EXISTS forum_forums_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE IF EXISTS forum_topics_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE IF EXISTS forum_posts_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE IF EXISTS forum_users_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE IF EXISTS forum_bans_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE IF EXISTS forum_reports_id_seq CASCADE');
    }
}