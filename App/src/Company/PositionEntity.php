<?php

declare(strict_types=1);

namespace App\Company;

use App\Entity\User;
use DateMalformedStringException;
use \DateTimeImmutable;
use \DateTimeZone;
use \InvalidArgumentException;
use \LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * A role a user held (or holds) at a company. A user can have several at the
 * same company, e.g. after a promotion, and they may overlap.
 *
 * Doctrine mapping: src/Company/Config/PositionEntity.orm.xml.
 */
final class PositionEntity
{
    public private(set) Uuid $id;
    public private(set) string $title;
    public private(set) ?string $overview;
    public private(set) DateTimeImmutable $startDate;
    /** Null while the position is current */
    public private(set) ?DateTimeImmutable $endDate = null;
    public private(set) ?string $reasonLeft = null;
    public private(set) ?DateTimeImmutable $archivedAt = null;

    /**
     * @throws DateMalformedStringException - I hate to have logic in my constructor, but it make it easier.
     */
    public function __construct(
        public private(set) readonly CompanyEntity $company,
        public private(set) readonly User $user,
        string $title,
        DateTimeImmutable $startDate,
        ?string $overview = null,
        public private(set) readonly DateTimeImmutable $createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC')),
    ) {
        $this->id = Uuid::v7();
        $this->title = self::normalizeTitle($title);
        $this->startDate = self::dateOnly($startDate);
        $this->overview = self::normalizeText($overview);

        $company->addPosition($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function isCurrent(): bool
    {
        return null === $this->endDate;
    }

    public function setTitle(string $title): static
    {
        $this->title = self::normalizeTitle($title);

        return $this;
    }

    public function setOverview(?string $overview): static
    {
        $this->overview = self::normalizeText($overview);

        return $this;
    }

    /**
     * @throws InvalidArgumentException if the position has ended before this date
     */
    public function setStartDate(DateTimeImmutable $startDate): static
    {
        $startDate = self::dateOnly($startDate);
        self::assertChronological($startDate, $this->endDate);
        $this->startDate = $startDate;

        return $this;
    }

    /**
     * Marks the position as ended. Calling it again corrects the date/reason.
     *
     * @throws InvalidArgumentException if $endDate is before the start date
     */
    public function end(DateTimeImmutable $endDate, ?string $reasonLeft = null): static
    {
        $endDate = self::dateOnly($endDate);
        self::assertChronological($this->startDate, $endDate);
        $this->endDate = $endDate;
        $this->reasonLeft = self::normalizeText($reasonLeft);

        return $this;
    }

    /**
     * @throws LogicException if the position hasn't ended
     */
    public function setReasonLeft(?string $reasonLeft): static
    {
        if ($this->isCurrent()) {
            throw new LogicException('A current position has no reason for leaving; use end()');
        }

        $this->reasonLeft = self::normalizeText($reasonLeft);

        return $this;
    }

    /**
     * Makes the position current again, e.g. to undo end(). Clears the reason.
     */
    public function reopen(): static
    {
        $this->endDate = null;
        $this->reasonLeft = null;

        return $this;
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    public function archive(DateTimeImmutable $at): static
    {
        $this->archivedAt ??= $at;  // already archived: keep the original time

        return $this;
    }

    public function unarchive(): static
    {
        $this->archivedAt = null;

        return $this;
    }

    private static function normalizeTitle(string $title): string
    {
        $title = trim($title);
        if ('' === $title) {
            throw new InvalidArgumentException('Position title cannot be blank');
        }

        return $title;
    }

    /**
     * Blank or whitespace-only text is stored as null.
     */
    private static function normalizeText(?string $text): ?string
    {
        return null === $text ? null : (trim($text) ?: null);
    }

    /**
     * The columns are DATE, so drop the time now rather than having it vanish
     * on the next load. Keeps the date as written in the given timezone.
     */
    private static function dateOnly(DateTimeImmutable $date): DateTimeImmutable
    {
        return $date->setTime(0, 0);
    }

    private static function assertChronological(DateTimeImmutable $start, ?DateTimeImmutable $end): void
    {
        if (null !== $end && $end < $start) {
            throw new InvalidArgumentException('A position cannot end before it starts');
        }
    }
}
