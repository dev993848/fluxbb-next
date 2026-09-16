<?php

declare(strict_types=1);

namespace FluxBB\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add PostgreSQL full-text search indexes and search_entries table.
 */
final class Version20250201000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add PostgreSQL fulltext search (tsvector) support';
    }

    public function up(Schema $schema): void
    {
        // 1. Add tsvector column to forum_posts
        $this->addSql('ALTER TABLE forum_posts ADD COLUMN search_vector tsvector');

        // 2. Add tsvector column to forum_topics
        $this->addSql('ALTER TABLE forum_topics ADD COLUMN search_vector tsvector');

        // 3. Create GIN indexes for full-text search
        $this->addSql('CREATE INDEX idx_posts_search ON forum_posts USING GIN(search_vector)');
        $this->addSql('CREATE INDEX idx_topics_search ON forum_topics USING GIN(search_vector)');

        // 4. Create trigger function to auto-update search_vector on posts
        $this->addSql('
            CREATE OR REPLACE FUNCTION forum_posts_search_update() RETURNS trigger AS $$
            BEGIN
                NEW.search_vector := to_tsvector(\'english\', COALESCE(NEW.message, \'\'));
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        ');

        // 5. Apply trigger to forum_posts
        $this->addSql('
            CREATE TRIGGER forum_posts_search_trigger
            BEFORE INSERT OR UPDATE OF message ON forum_posts
            FOR EACH ROW EXECUTE FUNCTION forum_posts_search_update()
        ');

        // 6. Create trigger function for topics
        $this->addSql('
            CREATE OR REPLACE FUNCTION forum_topics_search_update() RETURNS trigger AS $$
            BEGIN
                NEW.search_vector := to_tsvector(\'english\', COALESCE(NEW.subject, \'\'));
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        ');

        // 7. Apply trigger to forum_topics
        $this->addSql('
            CREATE TRIGGER forum_topics_search_trigger
            BEFORE INSERT OR UPDATE OF subject ON forum_topics
            FOR EACH ROW EXECUTE FUNCTION forum_topics_search_update()
        ');

        // 8. Add performance indexes for common queries
        $this->addSql('CREATE INDEX idx_posts_topic_posted ON forum_posts (topic_id, posted)');
        $this->addSql('CREATE INDEX idx_topics_forum_posted ON forum_topics (forum_id, sticky DESC, last_post DESC)');
        $this->addSql('CREATE INDEX idx_users_last_visit ON forum_users (last_visit) WHERE last_visit IS NOT NULL');
        $this->addSql('CREATE INDEX idx_bans_expire ON forum_bans (expire) WHERE expire IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS forum_posts_search_trigger ON forum_posts');
        $this->addSql('DROP TRIGGER IF EXISTS forum_topics_search_trigger ON forum_topics');
        $this->addSql('DROP FUNCTION IF EXISTS forum_posts_search_update');
        $this->addSql('DROP FUNCTION IF EXISTS forum_topics_search_update');
        $this->addSql('DROP INDEX IF EXISTS idx_posts_search');
        $this->addSql('DROP INDEX IF EXISTS idx_topics_search');
        $this->addSql('DROP INDEX IF EXISTS idx_posts_topic_posted');
        $this->addSql('DROP INDEX IF EXISTS idx_topics_forum_posted');
        $this->addSql('DROP INDEX IF EXISTS idx_users_last_visit');
        $this->addSql('DROP INDEX IF EXISTS idx_bans_expire');
        $this->addSql('ALTER TABLE forum_posts DROP COLUMN IF EXISTS search_vector');
        $this->addSql('ALTER TABLE forum_topics DROP COLUMN IF EXISTS search_vector');
    }
}