<?php

declare(strict_types=1);

namespace App\Company\Event;

use App\Company\ProductId;
use App\Company\ProjectId;
use App\Shared\EventSourcing\DomainEvent;
use App\Shared\EventSourcing\ListOf;
use App\Shared\UserId;
use App\Taxonomy\TagId;
use DateTimeImmutable;

/**
 * A project was started for a product. Stored as "project.created".
 */
final readonly class ProjectCreated implements DomainEvent
{
    /**
     * @param list<TagId> $tagIds
     */
    public function __construct(
        public ProjectId $projectId,
        public ProductId $productId,
        public UserId $ownerId,
        public string $name,
        public ?string $overview,
        #[ListOf(TagId::class)]
        public array $tagIds,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
