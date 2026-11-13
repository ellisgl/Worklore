<?php

declare(strict_types=1);

namespace App\Shared\EventSourcing;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A module's events and their stored type names. The names are what the
 * event store keeps, so they never change, even if a class is renamed.
 */
#[AutoconfigureTag]
interface EventCatalog
{
    /**
     * @return array<string, class-string<DomainEvent>> type name => class
     */
    public function events(): array;
}
