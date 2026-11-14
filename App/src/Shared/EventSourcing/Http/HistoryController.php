<?php

declare(strict_types=1);

namespace App\Shared\EventSourcing\Http;

use App\Company\Application\CompanyNotFound;
use App\Company\Application\PositionNotFound;
use App\Company\Application\ProductNotFound;
use App\Company\Application\ProjectNotFound;
use App\Company\CompanyEvents;
use App\Journal\Application\JournalEntryNotFound;
use App\Journal\JournalEvents;
use App\Shared\EventSourcing\HistoryEntry;
use App\Shared\EventSourcing\HistoryQueries;
use App\Shared\Http\OpenApi\Invalid;
use App\Shared\Http\OpenApi\NotFound;
use App\Shared\Http\OpenApi\PageOf;
use App\Shared\Http\PageRequest;
use App\Shared\InvalidValueException;
use App\Shared\NotFoundException;
use App\Shared\Owner;
use App\Taxonomy\Application\CategoryNotFound;
use App\Taxonomy\Application\TagNotFound;
use App\Taxonomy\TaxonomyEvents;
use Doctrine\DBAL\Exception;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Every event-sourced record's history: what happened to it, in order.
 */
#[OA\Tag(name: 'History')]
final class HistoryController extends AbstractController
{
    /** URL segment => [stream type, not-found exception] */
    private const array RESOURCES = [
        'companies' => [CompanyEvents::COMPANY, CompanyNotFound::class],
        'products' => [CompanyEvents::PRODUCT, ProductNotFound::class],
        'projects' => [CompanyEvents::PROJECT, ProjectNotFound::class],
        'positions' => [CompanyEvents::POSITION, PositionNotFound::class],
        'categories' => [TaxonomyEvents::CATEGORY, CategoryNotFound::class],
        'tags' => [TaxonomyEvents::TAG, TagNotFound::class],
        'journal-entries' => [JournalEvents::STREAM, JournalEntryNotFound::class],
    ];

    public function __construct(private readonly HistoryQueries $history)
    {
    }

    /**
     * Get a record's history.
     *
     * GET /api/{resource}/{id}/history[?page=1&per_page=50]: every change to
     * one of the caller's records, oldest first, as the events that recorded
     * it (archived records too).
     *
     * @throws Exception
     * @throws InvalidValueException
     * @throws NotFoundException
     */
    #[OA\Parameter(name: 'resource', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['companies', 'products', 'projects', 'positions', 'categories', 'tags', 'journal-entries']))]
    #[PageOf(HistoryEntry::class, 'The events, oldest first')]
    #[NotFound('Company not found.', 'Product not found.', 'Project not found.', 'Position not found.', 'Category not found.', 'Tag not found.', 'Journal entry not found.')]
    #[Invalid]
    #[Route(
        '/api/{resource}/{id}/history',
        name: 'api_history',
        requirements: ['resource' => 'companies|products|projects|positions|categories|tags|journal-entries', 'id' => Requirement::UUID],
        methods: ['GET'],
    )]
    public function show(
        #[CurrentUser] Owner $owner,
        string $resource,
        string $id,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] PageRequest $paging = new PageRequest(),
    ): JsonResponse {
        [$streamType, $notFound] = self::RESOURCES[$resource];

        $history = $this->history->forOwner($owner->ownerId(), $streamType, StreamId::fromString($id), $paging->toPage());

        return $this->json($history ?? throw new $notFound());
    }
}
