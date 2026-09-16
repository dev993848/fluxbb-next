<?php

declare(strict_types=1);

namespace FluxBB\Tests\Unit;

use FluxBB\Search\Domain\SearchQuery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Search domain entities.
 */
class SearchDomainTest extends TestCase
{
    public function testSearchQueryCanBeCreated(): void
    {
        $query = new SearchQuery(keywords: 'fluxbb forum', page: 1, perPage: 10);
        $this->assertSame('fluxbb forum', $query->keywords);
        $this->assertSame(1, $query->page);
        $this->assertSame(10, $query->perPage);
    }

    public function testSearchQueryDefaultsForumIdZero(): void
    {
        $query = new SearchQuery(keywords: 'test');
        $this->assertSame(0, $query->forumId);
        $this->assertSame(0, $query->userId);
    }
}