<?php

declare(strict_types=1);

namespace FluxBB\Forum\Domain;

use FluxBB\Shared\Domain\Entity;

/**
 * Category entity.
 *
 * Represents a category that groups forums together on the index page.
 * Categories have a display position for ordering and a name.
 */
class Category implements Entity
{
    /**
     * @param int    $id       Unique category identifier
     * @param string $name     Display name of the category
     * @param int    $position Sort order on the index page
     */
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly int $position = 0,
    ) {}

    /**
     * Get the category's unique identifier.
     *
     * @return int The category ID
     */
    public function identity(): int
    {
        return $this->id;
    }

    /** @return int The category ID */
    public function getId(): int { return $this->id; }

    /** @return string The category display name */
    public function getName(): string { return $this->name; }

    /** @return int The sort position */
    public function getPosition(): int { return $this->position; }
}