<?php

declare(strict_types=1);

namespace FluxBB\User\Domain;

use FluxBB\Shared\Domain\ValueObject;

/**
 * Value Object representing a validated email address.
 *
 * Uses PHP's built-in FILTER_VALIDATE_EMAIL for validation.
 * All email addresses in the domain must pass this validation.
 */
final class Email extends ValueObject
{
    /**
     * @param string $value The raw email address
     * @throws \DomainException If the email is not valid
     */
    public function __construct(
        private readonly string $value
    ) {
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new \DomainException('Invalid email address.');
        }
    }

    /**
     * Get the email address string.
     *
     * @return string The email
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Compare with another Value Object of the same type.
     *
     * @param ValueObject $other The other email to compare
     * @return bool True if both have the same email value
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $other->value === $this->value;
    }
}