<?php

declare(strict_types=1);

namespace App\Company;

use App\Shared\InvalidValueException;
use DateTimeImmutable;

/**
 * The dates a position runs: a start day and, once it has ended, an end day.
 * Days only (the columns are DATE), and never ending before it starts.
 *
 * Immutable: the "change" methods return a new Period. Properties aren't
 * readonly only because Doctrine rewrites them when an entity is refreshed.
 *
 * Doctrine embeddable: src/Company/Config/Period.orm.xml (start_date, end_date).
 */
final class Period
{
    private(set) DateTimeImmutable $start;
    /** Null while ongoing */
    private(set) ?DateTimeImmutable $end;

    /**
     * @throws InvalidValueException if $end is before $start
     */
    private function __construct(DateTimeImmutable $start, ?DateTimeImmutable $end)
    {
        $start = self::dateOnly($start);
        $end = null === $end ? null : self::dateOnly($end);
        if (null !== $end && $end < $start) {
            throw new InvalidValueException('A position cannot end before it starts');
        }

        $this->start = $start;
        $this->end = $end;
    }

    public static function startingOn(DateTimeImmutable $start): self
    {
        return new self($start, null);
    }

    /**
     * @throws InvalidValueException if $end is before $start
     */
    public static function between(DateTimeImmutable $start, DateTimeImmutable $end): self
    {
        return new self($start, $end);
    }

    public function isOngoing(): bool
    {
        return null === $this->end;
    }

    /**
     * @throws InvalidValueException if this would end before it starts
     */
    public function withStart(DateTimeImmutable $start): self
    {
        return new self($start, $this->end);
    }

    /**
     * @throws InvalidValueException if $end is before the start
     */
    public function endingOn(DateTimeImmutable $end): self
    {
        return new self($this->start, $end);
    }

    public function reopened(): self
    {
        return new self($this->start, null);
    }

    public function equals(self $other): bool
    {
        return $this->start == $other->start && $this->end == $other->end;
    }

    /**
     * Keeps the date as written in the given timezone, so it doesn't change
     * on the next load from the DATE column.
     */
    private static function dateOnly(DateTimeImmutable $date): DateTimeImmutable
    {
        return $date->setTime(0, 0);
    }
}
