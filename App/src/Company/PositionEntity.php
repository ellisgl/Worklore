<?php

declare(strict_types=1);

namespace App\Company;

use App\Shared\OwnershipMismatchException;
use App\Identity\User;
use App\Shared\Name;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;
use function Symfony\Component\Clock\now;

/**
 * A role a user held (or holds) at a company. A user can have several at the
 * same company, e.g. after a promotion, and they may overlap.
 *
 * Doctrine mapping: src/Company/Config/PositionEntity.orm.xml.
 */
final class PositionEntity
{
    public const int MAX_REASON_LEFT_LENGTH = 255;

    private(set) PositionId $id;
    private(set) Name $title;
    private(set) ?string $overview;
    private(set) DateTimeImmutable $startDate;
    /** Null while the position is current */
    private(set) ?DateTimeImmutable $endDate = null;
    private(set) ?string $reasonLeft = null;
    private(set) ?DateTimeImmutable $archivedAt = null;
    private(set) DateTimeImmutable $createdAt;

    /**
     * @throws OwnershipMismatchException if the company belongs to another user
     */
    public function __construct(
        private(set) readonly CompanyEntity $company,
        private(set) readonly User $user,
        string $title,
        DateTimeImmutable $startDate,
        ?string $overview = null,
        ?DateTimeImmutable $createdAt = null,
    ) {
        OwnershipMismatchException::assertSameUser($company->user, $user, 'company');

        $this->id = PositionId::generate();
        $this->createdAt = $createdAt ?? now()->setTimezone(new DateTimeZone('UTC'));
        $this->title = Name::of($title, 'Position title');
        $this->startDate = self::dateOnly($startDate);
        $this->overview = self::normalizeText($overview);

        $company->addPosition($this);
    }

    public function isCurrent(): bool
    {
        return null === $this->endDate;
    }

    public function setTitle(string $title): self
    {
        $this->title = Name::of($title, 'Position title');

        return $this;
    }

    public function setOverview(?string $overview): self
    {
        $this->overview = self::normalizeText($overview);

        return $this;
    }

    /**
     * @throws InvalidArgumentException if the position has ended before this date
     */
    public function setStartDate(DateTimeImmutable $startDate): self
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
    public function end(DateTimeImmutable $endDate, ?string $reasonLeft = null): self
    {
        $endDate = self::dateOnly($endDate);
        self::assertChronological($this->startDate, $endDate);
        $reasonLeft = self::normalizeReasonLeft($reasonLeft);
        $this->endDate = $endDate;
        $this->reasonLeft = $reasonLeft;

        return $this;
    }

    /**
     * @throws LogicException if the position hasn't ended
     */
    public function setReasonLeft(?string $reasonLeft): self
    {
        if ($this->isCurrent()) {
            throw new LogicException('A current position has no reason for leaving; use end()');
        }

        $this->reasonLeft = self::normalizeReasonLeft($reasonLeft);

        return $this;
    }

    /**
     * Makes the position current again, e.g. to undo end(). Clears the reason.
     */
    public function reopen(): self
    {
        $this->endDate = null;
        $this->reasonLeft = null;

        return $this;
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    public function archive(DateTimeImmutable $at): self
    {
        $this->archivedAt ??= $at;  // already archived: keep the original time

        return $this;
    }

    public function unarchive(): self
    {
        $this->archivedAt = null;

        return $this;
    }

    /**
     * Blank or whitespace-only text is stored as null.
     */
    private static function normalizeText(?string $text): ?string
    {
        return null === $text ? null : (trim($text) ?: null);
    }

    private static function normalizeReasonLeft(?string $reasonLeft): ?string
    {
        $reasonLeft = self::normalizeText($reasonLeft);
        if (null !== $reasonLeft && mb_strlen($reasonLeft) > self::MAX_REASON_LEFT_LENGTH) {
            throw new InvalidArgumentException(\sprintf('Reason for leaving cannot be longer than %d characters', self::MAX_REASON_LEFT_LENGTH));
        }

        return $reasonLeft;
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
