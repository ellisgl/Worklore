<?php

declare(strict_types=1);

namespace App\Company;

use App\Company\Event\CompanyArchived;
use App\Company\Event\CompanyCreated;
use App\Company\Event\CompanyOverviewChanged;
use App\Company\Event\CompanyRenamed;
use App\Company\Event\CompanyUnarchived;
use App\Shared\EventSourcing\AggregateRoot;
use App\Shared\EventSourcing\DomainEvent;
use App\Shared\InvalidValueException;
use App\Shared\Name;
use App\Shared\Text;
use App\Shared\UserId;
use DateTimeImmutable;
use LogicException;

/**
 * A company the owner works or worked for. An event-sourced aggregate (stream "company").
 */
final class Company extends AggregateRoot
{
    private CompanyId $id;
    private UserId $ownerId;
    private(set) Name $name;
    private(set) ?string $overview = null;
    private(set) ?DateTimeImmutable $archivedAt = null;

    private function __construct()
    {
    }

    /**
     * @throws InvalidValueException if the name is blank or too long
     */
    public static function create(
        CompanyId $id,
        UserId $ownerId,
        string $name,
        ?string $overview,
        DateTimeImmutable $at,
    ): self {
        $company = new self();
        $company->recordThat(new CompanyCreated($id, $ownerId, Name::of($name, 'Company name')->value, Text::optional($overview), $at));

        return $company;
    }

    /**
     * @param iterable<DomainEvent> $history
     */
    public static function fromHistory(iterable $history): self
    {
        $company = new self();
        $company->replay($history);

        return $company;
    }

    public function id(): CompanyId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    /**
     * @throws InvalidValueException if the name is blank or too long
     */
    public function rename(string $name): void
    {
        $name = Name::of($name, 'Company name');
        if (!$name->equals($this->name)) {
            $this->recordThat(new CompanyRenamed($this->id, $name->value));
        }
    }

    /**
     * Sets the overview; blank clears it.
     */
    public function describe(?string $overview): void
    {
        $overview = Text::optional($overview);
        if ($overview !== $this->overview) {
            $this->recordThat(new CompanyOverviewChanged($this->id, $overview));
        }
    }

    /**
     * Archiving an archived company keeps its original archive time.
     */
    public function archive(DateTimeImmutable $at): void
    {
        if (null === $this->archivedAt) {
            $this->recordThat(new CompanyArchived($this->id, $at));
        }
    }

    public function unarchive(): void
    {
        if (null !== $this->archivedAt) {
            $this->recordThat(new CompanyUnarchived($this->id));
        }
    }

    protected function apply(DomainEvent $event): void
    {
        match (true) {
            $event instanceof CompanyCreated => $this->created($event),
            $event instanceof CompanyRenamed => $this->name = Name::of($event->name, 'Company name'),
            $event instanceof CompanyOverviewChanged => $this->overview = $event->overview,
            $event instanceof CompanyArchived => $this->archivedAt = $event->archivedAt,
            $event instanceof CompanyUnarchived => $this->archivedAt = null,
            default => throw new LogicException('Not a company event: '.$event::class),
        };
    }

    private function created(CompanyCreated $event): void
    {
        $this->id = $event->companyId;
        $this->ownerId = $event->ownerId;
        $this->name = Name::of($event->name, 'Company name');
        $this->overview = $event->overview;
    }
}
