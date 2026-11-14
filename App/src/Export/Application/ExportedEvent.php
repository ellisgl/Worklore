<?php

declare(strict_types=1);

namespace App\Export\Application;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * One entry of an export's "events" list, read but not yet trusted: the
 * import still checks it decodes and fits its stream.
 */
final readonly class ExportedEvent
{
    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(
        public string $streamType,
        public Uuid $streamId,
        public int $version,
        public string $type,
        public DateTimeImmutable $recordedAt,
        public array $data,
    ) {
    }
}
