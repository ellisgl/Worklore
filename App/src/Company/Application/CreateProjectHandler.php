<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\ProductRepository;
use App\Company\Project;
use App\Company\ProjectRepository;
use App\Company\Query\CompanyNames;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\InvalidValueException;
use App\Taxonomy\Application\TagLookup;
use App\Taxonomy\Application\TagNotFound;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * The product and tags must be the owner's; project names are unique within the product.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateProjectHandler
{
    public function __construct(
        private ProjectRepository $projects,
        private ProductRepository $products,
        private CompanyNames $names,
        private TagLookup $tags,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws InvalidValueException  if the name is blank or too long
     * @throws ProductNotFound
     * @throws ProjectNameTaken
     * @throws TagNotFound
     */
    public function __invoke(CreateProject $command): void
    {
        $product = $this->products->findForOwner($command->productId, $command->ownerId)
            ?? throw new ProductNotFound();

        if ($this->names->projectNameTaken($product->id(), $command->name)) {
            throw new ProjectNameTaken($command->name);
        }

        $this->projects->save(Project::create(
            $command->projectId,
            $product->id(),
            $command->ownerId,
            $command->name,
            $command->overview,
            $this->tags->idsOwnedBy($command->ownerId, $command->tagIds),
            $this->clock->now()->setTimezone(new DateTimeZone('UTC')),
        ));
    }
}
