<?php

declare(strict_types=1);

namespace App\Tests\Unit\Company;

use App\Company\CompanyId;
use App\Company\Event\PositionCreated;
use App\Company\Event\PositionEnded;
use App\Company\Event\PositionReopened;
use App\Company\Event\PositionStartDateChanged;
use App\Company\Position;
use App\Company\PositionId;
use App\Shared\InvalidValueException;
use App\Shared\UserId;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

use function array_map;
use function str_repeat;

/**
 * Given its history, when the position is told to change, then it records
 * these events (or refuses): above all, it never ends before it starts.
 */
final class PositionTest extends TestCase
{
    public function testRecordingWithAnEndDateRecordsItAsEnded(): void
    {
        $position = self::position('2020-01-15', '2023-06-30', '  Moved on  ');

        $events = $position->releaseEvents();
        self::assertSame([PositionCreated::class, PositionEnded::class], self::classes($events));
        self::assertFalse($position->isCurrent());
        self::assertSame('Moved on', $position->reasonLeft);
    }

    public function testDatesAreDaysOnly(): void
    {
        $position = self::position('2020-01-15 23:30:00', null, at: new DateTimeImmutable('2020-01-15 23:30:00', new DateTimeZone('Pacific/Auckland')));

        self::assertSame('2020-01-15 00:00:00', $position->period->start->format('Y-m-d H:i:s'));
    }

    public function testItCannotEndBeforeItStarts(): void
    {
        foreach ([
            static fn () => self::position('2020-01-15', '2020-01-14'),
            static fn () => self::saved(self::position('2020-01-15'))->end(new DateTimeImmutable('2020-01-14')),
            static fn () => self::saved(self::position('2020-01-15', '2021-01-01'))->moveStartDate(new DateTimeImmutable('2021-06-01')),
        ] as $refused) {
            try {
                $refused();
                self::fail('Expected InvalidValueException');
            } catch (InvalidValueException $e) {
                self::assertSame('A position cannot end before it starts', $e->getMessage());
            }
        }
    }

    public function testItCanStartAndEndOnTheSameDay(): void
    {
        self::assertFalse(self::position('2020-01-15', '2020-01-15')->isCurrent());
    }

    public function testEndingAgainCorrectsTheDayAndReasonButNotWhenNothingChanged(): void
    {
        $position = self::saved(self::position('2020-01-15', '2023-06-30', 'Moved on'));

        $position->end(new DateTimeImmutable('2023-06-30'), 'Moved on');
        self::assertSame([], $position->releaseEvents());

        $position->end(new DateTimeImmutable('2023-07-31'));
        self::assertSame([PositionEnded::class], self::classes($position->releaseEvents()));
        self::assertSame('2023-07-31', $position->period->end?->format('Y-m-d'));
        self::assertNull($position->reasonLeft, 'No reason given this time');
    }

    public function testReopeningClearsTheEndAndReason(): void
    {
        $position = self::saved(self::position('2020-01-15', '2023-06-30', 'Moved on'));

        $position->reopen();
        $position->reopen();   // already current: nothing

        self::assertSame([PositionReopened::class], self::classes($position->releaseEvents()));
        self::assertTrue($position->isCurrent());
        self::assertNull($position->reasonLeft);
    }

    public function testMovingTheStartRecordsOnlyARealChange(): void
    {
        $position = self::saved(self::position('2020-01-15'));

        $position->moveStartDate(new DateTimeImmutable('2020-01-15 12:00'));
        $position->moveStartDate(new DateTimeImmutable('2020-02-01'));

        self::assertSame([PositionStartDateChanged::class], self::classes($position->releaseEvents()));
    }

    public function testATooLongReasonIsRefusedAndChangesNothing(): void
    {
        $position = self::saved(self::position('2020-01-15'));

        try {
            $position->end(new DateTimeImmutable('2021-01-01'), str_repeat('x', Position::MAX_REASON_LEFT_LENGTH + 1));
            self::fail('Expected InvalidValueException');
        } catch (InvalidValueException) {
        }

        self::assertTrue($position->isCurrent());
        self::assertSame([], $position->releaseEvents());
    }

    public function testBlankTitleIsRefused(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessageIs('Position title cannot be blank');

        Position::record(PositionId::generate(), CompanyId::generate(), UserId::generate(), '  ', new DateTimeImmutable('2020-01-01'), null, null, null, new DateTimeImmutable());
    }

    public function testReplayRebuildsTheSameState(): void
    {
        $position = self::position('2020-01-15', '2023-06-30', 'Moved on');
        $position->reopen();
        $position->retitle('Lead');

        $replayed = Position::fromHistory($position->releaseEvents());

        self::assertSame('Lead', $replayed->title->value);
        self::assertTrue($replayed->isCurrent());
        self::assertSame(4, $replayed->version());
    }

    private static function position(string $start, ?string $end = null, ?string $reason = null, ?DateTimeImmutable $at = null): Position
    {
        return Position::record(
            PositionId::generate(),
            CompanyId::generate(),
            UserId::generate(),
            'Developer',
            new DateTimeImmutable($start),
            null,
            null === $end ? null : new DateTimeImmutable($end),
            $reason,
            $at ?? new DateTimeImmutable(),
        );
    }

    /**
     * As if loaded from the store: its recording is history, not news.
     */
    private static function saved(Position $position): Position
    {
        return Position::fromHistory($position->releaseEvents());
    }

    /**
     * @param list<object> $events
     *
     * @return list<class-string>
     */
    private static function classes(array $events): array
    {
        return array_map(static fn (object $e) => $e::class, $events);
    }
}
