<?php

declare(strict_types=1);

namespace App\Company;

use App\Company\Event\PositionArchived;
use App\Company\Event\PositionCreated;
use App\Company\Event\PositionEnded;
use App\Company\Event\PositionOverviewChanged;
use App\Company\Event\PositionReopened;
use App\Company\Event\PositionRetitled;
use App\Company\Event\PositionStartDateChanged;
use App\Company\Event\PositionUnarchived;
use App\Shared\EventSourcing\AggregateRoot;
use App\Shared\EventSourcing\DomainEvent;
use App\Shared\InvalidValueException;
use App\Shared\Name;
use App\Shared\Text;
use App\Shared\UserId;
use DateTimeImmutable;
use LogicException;

use function mb_strlen;
use function sprintf;

/**
 * A job the owner held, or holds, at one of their companies: a title and a
 * Period that can't end before it starts. An event-sourced aggregate (stream
 * "position"). Its company must be the owner's (the handlers check).
 */
final class Position extends AggregateRoot
{
    public const int MAX_REASON_LEFT_LENGTH = 255;

    private PositionId $id;
    private UserId $ownerId;
    private(set) CompanyId $companyId;
    private(set) Name $title;
    private(set) ?string $overview = null;
    private(set) Period $period;
    /** Why the owner left; only once ended */
    private(set) ?string $reasonLeft = null;
    private(set) ?DateTimeImmutable $archivedAt = null;

    private function __construct()
    {
    }

    /**
     * Records the position; with an end day it is recorded as already ended.
     *
     * @throws InvalidValueException if the title is blank or too long, it would end before it starts, or the reason is too long
     */
    public static function record(
        PositionId $id,
        CompanyId $companyId,
        UserId $ownerId,
        string $title,
        DateTimeImmutable $startDate,
        ?string $overview,
        ?DateTimeImmutable $endDate,
        ?string $reasonLeft,
        DateTimeImmutable $at,
    ): self {
        $period = null === $endDate ? Period::startingOn($startDate) : Period::between($startDate, $endDate);

        $position = new self();
        $position->recordThat(new PositionCreated($id, $companyId, $ownerId, Name::of($title, 'Position title')->value, Text::optional($overview), $period->start, $at));
        if (null !== $period->end) {
            $position->recordThat(new PositionEnded($id, $period->end, self::reason($reasonLeft)));
        }

        return $position;
    }

    /**
     * @param iterable<DomainEvent> $history
     */
    public static function fromHistory(iterable $history): self
    {
        $position = new self();
        $position->replay($history);

        return $position;
    }

    public function id(): PositionId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function isCurrent(): bool
    {
        return $this->period->isOngoing();
    }

    /**
     * @throws InvalidValueException if the title is blank or too long
     */
    public function retitle(string $title): void
    {
        $title = Name::of($title, 'Position title');
        if (!$title->equals($this->title)) {
            $this->recordThat(new PositionRetitled($this->id, $title->value));
        }
    }

    /**
     * Sets the overview; blank clears it.
     */
    public function describe(?string $overview): void
    {
        $overview = Text::optional($overview);
        if ($overview !== $this->overview) {
            $this->recordThat(new PositionOverviewChanged($this->id, $overview));
        }
    }

    /**
     * @throws InvalidValueException if the position has ended before this day
     */
    public function moveStartDate(DateTimeImmutable $startDate): void
    {
        $period = $this->period->withStart($startDate);
        if ($period->start != $this->period->start) {
            $this->recordThat(new PositionStartDateChanged($this->id, $period->start));
        }
    }

    /**
     * Ends the position. Ending an ended one corrects its end day and reason.
     *
     * @throws InvalidValueException if it would end before it starts, or the reason is too long
     */
    public function end(DateTimeImmutable $endDate, ?string $reasonLeft = null): void
    {
        $period = $this->period->endingOn($endDate);
        $reasonLeft = self::reason($reasonLeft);
        if ($period->end != $this->period->end || $reasonLeft !== $this->reasonLeft) {
            $this->recordThat(new PositionEnded($this->id, $period->end ?? $endDate, $reasonLeft));
        }
    }

    /**
     * Makes an ended position current again, e.g. to undo end().
     */
    public function reopen(): void
    {
        if (!$this->period->isOngoing()) {
            $this->recordThat(new PositionReopened($this->id));
        }
    }

    /**
     * Archiving an archived position keeps its original archive time.
     */
    public function archive(DateTimeImmutable $at): void
    {
        if (null === $this->archivedAt) {
            $this->recordThat(new PositionArchived($this->id, $at));
        }
    }

    public function unarchive(): void
    {
        if (null !== $this->archivedAt) {
            $this->recordThat(new PositionUnarchived($this->id));
        }
    }

    protected function apply(DomainEvent $event): void
    {
        match (true) {
            $event instanceof PositionCreated => $this->created($event),
            $event instanceof PositionRetitled => $this->title = Name::of($event->title, 'Position title'),
            $event instanceof PositionOverviewChanged => $this->overview = $event->overview,
            $event instanceof PositionStartDateChanged => $this->period = $this->period->withStart($event->startDate),
            $event instanceof PositionEnded => $this->ended($event),
            $event instanceof PositionReopened => $this->reopened(),
            $event instanceof PositionArchived => $this->archivedAt = $event->archivedAt,
            $event instanceof PositionUnarchived => $this->archivedAt = null,
            default => throw new LogicException('Not a position event: '.$event::class),
        };
    }

    private function created(PositionCreated $event): void
    {
        $this->id = $event->positionId;
        $this->companyId = $event->companyId;
        $this->ownerId = $event->ownerId;
        $this->title = Name::of($event->title, 'Position title');
        $this->overview = $event->overview;
        $this->period = Period::startingOn($event->startDate);
    }

    private function ended(PositionEnded $event): void
    {
        $this->period = $this->period->endingOn($event->endDate);
        $this->reasonLeft = $event->reasonLeft;
    }

    private function reopened(): void
    {
        $this->period = $this->period->reopened();
        $this->reasonLeft = null;
    }

    /**
     * @throws InvalidValueException if longer than MAX_REASON_LEFT_LENGTH
     */
    private static function reason(?string $reasonLeft): ?string
    {
        $reasonLeft = Text::optional($reasonLeft);
        if (null !== $reasonLeft && mb_strlen($reasonLeft) > self::MAX_REASON_LEFT_LENGTH) {
            throw new InvalidValueException(sprintf('Reason for leaving cannot be longer than %d characters', self::MAX_REASON_LEFT_LENGTH));
        }

        return $reasonLeft;
    }
}
