<?php

declare(strict_types=1);

namespace FluxBB\User\Domain;

use FluxBB\Shared\Domain\ValueObject;

/**
 * Value Object representing a user's unique identifier.
 *
 * Wraps the auto-increment integer ID from the database.
 * Used throughout the domain model for type safety.
 */
final class UserId extends ValueObject
{
    /**
     * @param int $value The raw user ID
     */
    public function __construct(
        private readonly int $value
    ) {}

    /**
     * Get the raw integer value.
     *
     * @return int The user ID
     */
    public function toInt(): int
    {
        return $this->value;
    }

    /**
     * Compare with another Value Object of the same type.
     *
     * Two UserId objects are equal if they wrap the same integer.
     *
     * @param ValueObject $other The other value object to compare
     * @return bool True if both have the same ID value
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $other->value === $this->value;
    }
}