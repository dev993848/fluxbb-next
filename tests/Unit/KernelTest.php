<?php

declare(strict_types=1);

use FluxBB\Shared\Domain;
use PHPUnit\Framework\TestCase;

/**
 * Basic sanity test: ensure the autoloader and shared kernel work.
 */
class KernelTest extends TestCase
{
    public function test_autoloader_works(): void
    {
        $this->assertTrue(interface_exists(Domain\Entity::class));
        $this->assertTrue(class_exists(Domain\AggregateRoot::class));
        $this->assertTrue(class_exists(Domain\ValueObject::class));
        $this->assertTrue(interface_exists(Domain\DomainEvent::class));
    }

    public function test_kernel_can_be_instantiated(): void
    {
        $kernel = new \FluxBB\Shared\Infrastructure\Kernel('test', true);
        $this->assertInstanceOf(\FluxBB\Shared\Infrastructure\Kernel::class, $kernel);
    }
}