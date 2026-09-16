<?php

declare(strict_types=1);

namespace FluxBB\Shared\Domain;

/**
 * Base Value Object class.
 *
 * Value Objects are immutable objects that are compared by their attributes
 * rather than by identity. They have no lifecycle and can be freely shared.
 *
 * All Value Objects must implement the equals() method for proper
 * domain-level comparison.
 */
abstract class ValueObject
{
    /**
     * Compare this Value Object with another of the same type.
     *
     * Two Value Objects are considered equal if all their attributes
     * are identical. This is the domain-level equality check.
     *
     * @param ValueObject $other The other value object to compare with
     * @return bool True if the two objects have the same value
     */
    abstract public function equals(ValueObject $other): bool;
}