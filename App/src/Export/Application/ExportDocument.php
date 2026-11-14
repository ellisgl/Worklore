<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Export\DataExport;
use App\Shared\InvalidValueException;
use DateMalformedStringException;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

use function array_is_list;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;

/**
 * Reads the "events" list out of a decoded DataExport. Only the events
 * matter for an import; the record lists are a snapshot of the same data.
 */
final class ExportDocument
{
    /**
     * @param array<array-key, mixed> $document
     *
     * @return list<ExportedEvent>
     *
     * @throws InvalidValueException if it isn't an export this version can import
     */
    public static function events(array $document): array
    {
        if (DataExport::FORMAT !== ($document['format'] ?? null)) {
            throw new InvalidValueException('This is not a Worklore export.');
        }
        $version = $document['version'] ?? null;
        if (1 === $version) {
            throw new InvalidValueException('This export has no event history (format version 1). Export your data again, then import the new file.');
        }
        if (DataExport::VERSION !== $version) {
            throw new InvalidValueException(sprintf('Unsupported export version; expected %d.', DataExport::VERSION));
        }
        $list = $document['events'] ?? null;
        if (!is_array($list) || !array_is_list($list)) {
            throw new InvalidValueException('The export has no "events" list.');
        }

        $events = [];
        foreach ($list as $i => $event) {
            $events[] = self::event($i, $event);
        }

        return $events;
    }

    private static function event(int $i, mixed $event): ExportedEvent
    {
        if (!is_array($event)) {
            throw self::invalid($i, 'is not an object');
        }
        $streamType = $event['stream_type'] ?? null;
        $streamId = $event['stream_id'] ?? null;
        $version = $event['version'] ?? null;
        $type = $event['type'] ?? null;
        $recordedAt = $event['recorded_at'] ?? null;
        $data = $event['data'] ?? null;

        if (!is_string($streamType) || !is_string($type) || !is_string($recordedAt) || !is_string($streamId)) {
            throw self::invalid($i, 'needs stream_type, stream_id, type and recorded_at strings');
        }
        if (!is_int($version) || $version < 1) {
            throw self::invalid($i, 'needs a version of 1 or more');
        }
        if (!is_array($data)) {
            throw self::invalid($i, 'needs a data object');
        }

        try {
            return new ExportedEvent($streamType, Uuid::fromString($streamId), $version, $type, new DateTimeImmutable($recordedAt), $data);
        } catch (InvalidArgumentException) {
            throw self::invalid($i, 'has a stream_id that is not a UUID');
        } catch (DateMalformedStringException) {
            throw self::invalid($i, 'has a recorded_at that is not a time');
        }
    }

    public static function invalid(int $i, string $problem): InvalidValueException
    {
        return new InvalidValueException(sprintf('Event %d in the export %s.', $i + 1, $problem));
    }
}
