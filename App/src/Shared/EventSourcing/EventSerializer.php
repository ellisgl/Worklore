<?php

declare(strict_types=1);

namespace App\Shared\EventSourcing;

use App\Shared\EntityId;
use Closure;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use LogicException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use UnexpectedValueException;

use function array_flip;
use function array_key_exists;
use function count;
use function is_a;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function lcfirst;
use function preg_replace;
use function sprintf;
use function strtolower;

/**
 * Turns domain events into a type name plus a JSON-ready payload, and back.
 *
 * The payload has one snake_case key per constructor parameter. Ids are
 * stored as RFC 4122 strings, times as ISO 8601 in UTC, scalars as they are,
 * and arrays as lists of the #[ListOf] type. A key missing from an old
 * payload takes the parameter's default, so events can grow new parameters.
 */
final readonly class EventSerializer
{
    /** @var array<string, class-string<DomainEvent>> */
    private array $classes;
    /** @var array<class-string<DomainEvent>, string> */
    private array $types;

    /**
     * @param iterable<EventCatalog> $catalogs
     */
    public function __construct(#[AutowireIterator(EventCatalog::class)] iterable $catalogs)
    {
        $classes = [];
        foreach ($catalogs as $catalog) {
            foreach ($catalog->events() as $type => $class) {
                if (isset($classes[$type])) {
                    throw new LogicException(sprintf('Event type "%s" is registered twice.', $type));
                }
                $classes[$type] = $class;
            }
        }
        $types = array_flip($classes);
        if (count($types) !== count($classes)) {
            throw new LogicException('An event class is registered under two type names.');
        }

        $this->classes = $classes;
        $this->types = $types;
    }

    public function typeOf(DomainEvent $event): string
    {
        return $this->types[$event::class] ?? throw new LogicException(sprintf('%s is in no EventCatalog.', $event::class));
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(DomainEvent $event): array
    {
        $payload = [];
        foreach (self::parameters($event::class) as $parameter) {
            $name = $parameter->getName();
            $payload[self::key($name)] = self::encode($event->{$name}, $parameter);
        }

        return $payload;
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @throws UnexpectedValueException if the type is unknown or the payload doesn't fit the event
     */
    public function event(string $type, array $payload): DomainEvent
    {
        $class = $this->classes[$type] ?? throw new UnexpectedValueException(sprintf('Unknown event type "%s".', $type));

        $arguments = [];
        foreach (self::parameters($class) as $parameter) {
            $key = self::key($parameter->getName());
            if (array_key_exists($key, $payload)) {
                $arguments[] = self::decode($payload[$key], $parameter);
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
            } else {
                throw new UnexpectedValueException(sprintf('Event "%s" has no "%s".', $type, $key));
            }
        }

        return new $class(...$arguments);
    }

    /**
     * A copy of $event with every id in it (lists included) replaced by
     * $map's answer, e.g. to give imported records new ids.
     *
     * @template T of DomainEvent
     *
     * @param T                           $event
     * @param Closure(EntityId): EntityId $map
     *
     * @return T
     */
    public function withIds(DomainEvent $event, Closure $map): DomainEvent
    {
        $arguments = [];
        foreach (self::parameters($event::class) as $parameter) {
            $value = $event->{$parameter->getName()};
            if ($value instanceof EntityId) {
                $value = $map($value);
            } elseif (is_array($value)) {
                $list = [];
                foreach ($value as $item) {
                    $list[] = $item instanceof EntityId ? $map($item) : $item;
                }
                $value = $list;
            }
            $arguments[] = $value;
        }

        return new ($event::class)(...$arguments);
    }

    /**
     * @param class-string $class
     *
     * @return list<ReflectionParameter>
     */
    private static function parameters(string $class): array
    {
        return new ReflectionClass($class)->getConstructor()?->getParameters() ?? [];
    }

    private static function key(string $parameter): string
    {
        return strtolower((string) preg_replace('/[A-Z]/', '_$0', lcfirst($parameter)));
    }

    private static function encode(mixed $value, ReflectionParameter $parameter): mixed
    {
        if (null === $value || is_string($value) || is_int($value) || is_bool($value) || is_float($value)) {
            return $value;
        }
        if ($value instanceof EntityId) {
            return $value->toRfc4122();
        }
        if ($value instanceof DateTimeImmutable) {
            return $value->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM);
        }
        if (is_array($value)) {
            $list = [];
            foreach ($value as $item) {
                $list[] = self::encode($item, $parameter);
            }

            return $list;
        }

        throw new LogicException(sprintf('Parameter $%s of an event has a type EventSerializer can\'t store.', $parameter->getName()));
    }

    private static function decode(mixed $value, ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();
        if (null === $value) {
            return $type?->allowsNull() ? null : throw new UnexpectedValueException(sprintf('"%s" is null.', $parameter->getName()));
        }
        if (!$type instanceof ReflectionNamedType) {
            throw new LogicException(sprintf('Parameter $%s needs a single named type.', $parameter->getName()));
        }

        $name = $type->getName();
        if ('array' === $name) {
            $of = ($parameter->getAttributes(ListOf::class)[0] ?? null)?->newInstance()->type
                ?? throw new LogicException(sprintf('Array parameter $%s needs #[ListOf].', $parameter->getName()));
            if (!is_array($value)) {
                throw new UnexpectedValueException(sprintf('"%s" is not a list.', $parameter->getName()));
            }
            $list = [];
            foreach ($value as $item) {
                $list[] = self::decodeAs($of, $item, $parameter);
            }

            return $list;
        }

        return self::decodeAs($name, $value, $parameter);
    }

    private static function decodeAs(string $type, mixed $value, ReflectionParameter $parameter): mixed
    {
        return match (true) {
            is_a($type, EntityId::class, true) && is_string($value) => $type::fromString($value),
            DateTimeImmutable::class === $type && is_string($value) => self::time($value, $parameter),
            'string' === $type && is_string($value),
            'int' === $type && is_int($value),
            'bool' === $type && is_bool($value),
            'float' === $type && (is_float($value) || is_int($value)) => $value,
            default => throw new UnexpectedValueException(sprintf('"%s" doesn\'t hold a %s.', $parameter->getName(), $type)),
        };
    }

    private static function time(string $value, ReflectionParameter $parameter): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value)->setTimezone(new DateTimeZone('UTC'));
        } catch (DateMalformedStringException $e) {
            throw new UnexpectedValueException(sprintf('"%s" is not a time.', $parameter->getName()), previous: $e);
        }
    }
}
