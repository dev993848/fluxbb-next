<?php

declare(strict_types=1);

use FluxBB\Shared\Domain;
use PHPUnit\Framework\TestCase;

class EntityInterfaceTest extends TestCase
{
    public function test_entity_interface_is_available(): void
    {
        $this->assertTrue(interface_exists(Domain\Entity::class));
    }

    public function test_aggregate_root_is_available(): void
    {
        $this->assertTrue(class_exists(Domain\AggregateRoot::class));
    }

    public function test_value_object_is_available(): void
    {
        $this->assertTrue(class_exists(Domain\ValueObject::class));
    }

    public function test_domain_event_is_available(): void
    {
        $this->assertTrue(interface_exists(Domain\DomainEvent::class));
    }
}