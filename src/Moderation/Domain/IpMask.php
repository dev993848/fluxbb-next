<?php

declare(strict_types=1);

namespace FluxBB\Moderation\Domain;

use FluxBB\Shared\Domain\ValueObject;

/**
 * Value Object representing an IP address mask for ban matching.
 *
 * Supports wildcard patterns similar to the original FluxBB admin_bans.php:
 * - Exact IP:      "192.168.1.1"
 * - Partial mask:  "192.168.*.*"  (matches any IP in 192.168.x.x)
 * - Single octet:  "192.168.1.*"  (matches any IP in 192.168.1.x)
 * - Full mask:     "*.*.*.*"      (matches all IPs)
 *
 * The wildcard character "*" matches any value in that octet position.
 * IPv6 is not yet supported.
 */
final class IpMask extends ValueObject
{
    /** @var string The raw IP mask string */
    private readonly string $value;

    /** @var list<string> Parsed octets (each is a literal or "*") */
    private readonly array $octets;

    /**
     * @param string $ipMask The IP mask (e.g., "192.168.1.*")
     * @throws \DomainException If the IP mask format is invalid
     */
    public function __construct(string $ipMask)
    {
        $trimmed = trim($ipMask);

        if (!self::isValid($trimmed)) {
            throw new \DomainException(
                sprintf('Invalid IP mask format: "%s". Expected format: "192.168.1.1" or "192.168.*.*"', $ipMask)
            );
        }

        $this->value = $trimmed;
        $this->octets = explode('.', $trimmed);
    }

    /**
     * Get the raw IP mask string.
     *
     * @return string The IP mask
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Check if a given IP address matches this mask.
     *
     * Each octet is compared: literal octets must match exactly,
     * wildcard octets ("*") match any value.
     *
     * @param string $ip The IP address to check (e.g., "192.168.1.5")
     * @return bool True if the IP matches this mask
     */
    public function matches(string $ip): bool
    {
        $ipOctets = explode('.', $ip);

        // Must have exactly 4 octets
        if (count($ipOctets) !== 4 || count($this->octets) !== 4) {
            return false;
        }

        for ($i = 0; $i < 4; $i++) {
            if ($this->octets[$i] !== '*' && $this->octets[$i] !== $ipOctets[$i]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compare this IpMask with another Value Object.
     *
     * @param ValueObject $other The other IP mask to compare
     * @return bool True if both have the same mask value
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $other->value === $this->value;
    }

    /**
     * Validate an IP mask format.
     *
     * Accepts standard IPv4 addresses with optional "*" wildcards.
     *
     * @param string $ipMask The IP mask to validate
     * @return bool True if the format is valid
     */
    public static function isValid(string $ipMask): bool
    {
        $pattern = '/^(\d{1,3}|\*)(\.(\d{1,3}|\*)){3}$/';
        if (preg_match($pattern, $ipMask) !== 1) {
            return false;
        }

        // Validate numeric octets (0-255)
        $octets = explode('.', $ipMask);
        foreach ($octets as $octet) {
            if ($octet !== '*' && ((int) $octet < 0 || (int) $octet > 255)) {
                return false;
            }
        }

        return true;
    }
}