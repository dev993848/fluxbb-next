<?php

declare(strict_types=1);

namespace FluxBB\Shared\Domain;

/**
 * Base entity marker interface.
 *
 * All domain entities must implement this interface to provide
 * a consistent way to retrieve their identity value, which is
 * used for equality comparison and persistence lookups.
 */
interface Entity
{
    /**
     * Get the unique identity value of this entity.
     *
     * @return mixed The identity value (typically int or Uuid)
     */
    public function identity(): mixed;
}