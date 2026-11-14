<?php

declare(strict_types=1);

namespace App\Shared\EventSourcing\Listener;

use App\Shared\EventSourcing\DomainEvent;
use App\Shared\EventSourcing\EventSerializer;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use function str_ends_with;

/**
 * Logs every domain event to the "domain" channel as an audit trail: its
 * type and the ids it carries (company_id, owner_id, ...). Names, overviews
 * and journal text stay out of the logs.
 */
#[AsMessageHandler(bus: 'event.bus')]
#[WithMonologChannel('domain')]
final readonly class LogDomainEvents
{
    public function __construct(
        private EventSerializer $serializer,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(DomainEvent $event): void
    {
        $ids = [];
        foreach ($this->serializer->payload($event) as $key => $value) {
            if (str_ends_with($key, '_id')) {
                $ids[$key] = $value;
            }
        }

        $type = $this->serializer->typeOf($event);
        $this->logger->info($type, ['event' => $type] + $ids);
    }
}
