<?php

declare(strict_types=1);

namespace FluxBB\User\Domain;

use FluxBB\Shared\Domain\ValueObject;

/**
 * Value Object representing a validated username.
 *
 * Enforces length constraints (2-25 characters) and can be extended
 * with additional validation rules (allowed characters, banned names).
 */
final class Username extends ValueObject
{
    /** Minimum allowed username length. */
    private const int MIN_LENGTH = 2;

    /** Maximum allowed username length. */
    private const int MAX_LENGTH = 25;

    /**
     * @param string $value The raw username string
     * @throws \DomainException If the username length is out of bounds
     */
    public function __construct(
        private readonly string $value
    ) {
        $this->validate();
    }

    /**
     * Get the username string.
     *
     * @return string The username
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Compare with another Value Object of the same type.
     *
     * @param ValueObject $other The other username to compare
     * @return bool True if both have the same username value
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $other->value === $this->value;
    }

    /**
     * Validate the username length.
     *
     * @throws \DomainException If the username is too short or too long
     */
    private function validate(): void
    {
        $len = mb_strlen($this->value);

        if ($len < self::MIN_LENGTH || $len > self::MAX_LENGTH) {
            throw new \DomainException(
                sprintf('Username must be between %d and %d characters.', self::MIN_LENGTH, self::MAX_LENGTH)
            );
        }
    }
}