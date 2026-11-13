<?php

declare(strict_types=1);

namespace App\Company;

use App\Shared\InvalidValueException;
use App\Shared\Name;
use App\Shared\Owner;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Symfony\Component\Clock\Clock;

use function sprintf;

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
    /** The aggregate owner, copied from the company; stored for owner-scoped queries */
    private(set) readonly Owner $owner;
    private(set) Name $title;
    private(set) ?string $overview;
    /** Start and (once ended) end day */
    private(set) Period $period;
    private(set) ?string $reasonLeft = null;
    private(set) ?DateTimeImmutable $archivedAt = null;
    private(set) DateTimeImmutable $createdAt;

    /**
     * @internal create through CompanyEntity::addPosition(), which adds it to the
     *           company's list; the owner is taken from the company
     */
    public function __construct(
        private(set) readonly CompanyEntity $company,
        string $title,
        DateTimeImmutable $startDate,
        ?string $overview = null,
        ?DateTimeImmutable $createdAt = null,
        ?PositionId $id = null,
    ) {
        $this->owner = $company->owner;
        $this->id = $id ?? PositionId::generate();
        $this->createdAt = $createdAt ?? Clock::get()->now()->setTimezone(new DateTimeZone('UTC'));
        $this->title = Name::of($title, 'Position title');
        $this->period = Period::startingOn($startDate);
        $this->overview = self::normalizeText($overview);
    }

    public function isCurrent(): bool
    {
        return $this->period->isOngoing();
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
     * @throws InvalidValueException if the position has ended before this date
     */
    public function setStartDate(DateTimeImmutable $startDate): self
    {
        $this->period = $this->period->withStart($startDate);

        return $this;
    }

    /**
     * Marks the position as ended. Calling it again corrects the date/reason.
     *
     * @throws InvalidValueException if $endDate is before the start date
     */
    public function end(DateTimeImmutable $endDate, ?string $reasonLeft = null): self
    {
        $period = $this->period->endingOn($endDate);
        $reasonLeft = self::normalizeReasonLeft($reasonLeft);
        $this->period = $period;
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
        $this->period = $this->period->reopened();
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
            throw new InvalidValueException(sprintf('Reason for leaving cannot be longer than %d characters', self::MAX_REASON_LEFT_LENGTH));
        }

        return $reasonLeft;
    }
}
