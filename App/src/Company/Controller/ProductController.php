<?php

declare(strict_types=1);

namespace App\Company\Controller;

use App\Company\Application\ArchiveProduct;
use App\Company\Application\CompanyNotFound;
use App\Company\Application\CreateProduct;
use App\Company\Application\EditProduct;
use App\Company\Application\ProductNotFound;
use App\Company\Application\UnarchiveProduct;
use App\Company\CompanyId;
use App\Company\ProductId;
use App\Company\Query\CompanyQueries;
use App\Company\Query\ProductQueries;
use App\Company\Query\ProductSummary;
use App\Shared\Http\OpenApi\Created;
use App\Shared\Http\OpenApi\Invalid;
use App\Shared\Http\OpenApi\Item;
use App\Shared\Http\OpenApi\NoContent;
use App\Shared\Http\OpenApi\NotFound;
use App\Shared\Http\OpenApi\PageOf;
use App\Shared\Http\PageRequest;
use App\Shared\InvalidValueException;
use App\Shared\Owner;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Products: listed and created under their company, read and changed by id.
 * Reads call the query classes; writes dispatch a command.
 */
#[OA\Tag(name: 'Products')]
#[Route('/api', name: 'api_products_')]
final class ProductController extends AbstractController
{
    public function __construct(
        // Named autowiring: $commandBus is the command.bus service.
        private readonly MessageBusInterface $commandBus,
        private readonly ProductQueries $products,
        private readonly CompanyQueries $companies,
    ) {
    }

    /**
     * List a company's products.
     *
     * GET /api/companies/{companyId}/products[?include_archived=1][&page=1&per_page=50]
     *
     * @throws CompanyNotFound
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws InvalidValueException
     */
    #[PageOf(ProductSummary::class, 'The products, sorted by name')]
    #[NotFound('Company not found.')]
    #[Invalid]
    #[Route('/companies/{companyId}/products', name: 'list', requirements: ['companyId' => Requirement::UUID], methods: ['GET'])]
    public function list(
        #[CurrentUser] Owner $owner,
        string $companyId,
        #[MapQueryParameter(name: 'include_archived')] bool $includeArchived = false,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] PageRequest $paging = new PageRequest(),
    ): JsonResponse {
        $company = CompanyId::fromString($companyId);
        if (!$this->companies->existsForOwner($owner->ownerId(), $company)) {
            throw new CompanyNotFound();
        }

        return $this->json($this->products->listForCompany($owner->ownerId(), $company, $includeArchived, $paging->toPage()));
    }

    /**
     * Add a product to a company.
     *
     * POST /api/companies/{companyId}/products {"name": "...", "overview": "..."} → 201 {"id": "..."}
     *
     * @throws ExceptionInterface
     */
    #[Created('Created')]
    #[Invalid]
    #[NotFound('Company not found.')]
    #[Route('/companies/{companyId}/products', name: 'create', requirements: ['companyId' => Requirement::UUID], methods: ['POST'])]
    public function create(
        #[CurrentUser] Owner $owner,
        string $companyId,
        #[MapRequestPayload] CreateProductRequest $payload,
    ): JsonResponse {
        $id = ProductId::generate();
        $this->commandBus->dispatch(new CreateProduct($id, CompanyId::fromString($companyId), $owner->ownerId(), $payload->name, $payload->overview));

        return $this->json(['id' => $id->toRfc4122()], Response::HTTP_CREATED);
    }

    /**
     * Get a product.
     *
     * GET /api/products/{id}: one of the caller's products, archived or not.
     *
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws ProductNotFound
     */
    #[Item(ProductSummary::class, 'The product')]
    #[NotFound('Product not found.')]
    #[Route('/products/{id}', name: 'show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(#[CurrentUser] Owner $owner, string $id): JsonResponse
    {
        return $this->json($this->products->findForOwner($owner->ownerId(), ProductId::fromString($id)) ?? throw new ProductNotFound());
    }

    /**
     * Change a product's name or overview.
     *
     * PATCH /api/products/{id} {"name": "...", "overview": "..."} → 204.
     * Only the fields sent are changed; an empty overview clears it.
     *
     * @throws ExceptionInterface
     */
    #[NoContent('Saved')]
    #[Invalid]
    #[NotFound('Product not found.')]
    #[Route('/products/{id}', name: 'edit', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    public function edit(
        #[CurrentUser] Owner $owner,
        string $id,
        #[MapRequestPayload] EditProductRequest $payload,
    ): Response {
        $this->commandBus->dispatch(new EditProduct(ProductId::fromString($id), $owner->ownerId(), $payload->name, $payload->overview));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Archive a product.
     *
     * POST /api/products/{id}/archive → 204. Hides the product (and its
     * projects) from lists; it can still be fetched by id and unarchived.
     *
     * @throws ExceptionInterface
     */
    #[NoContent('Archived')]
    #[NotFound('Product not found.')]
    #[Route('/products/{id}/archive', name: 'archive', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function archive(#[CurrentUser] Owner $owner, string $id): Response
    {
        $this->commandBus->dispatch(new ArchiveProduct(ProductId::fromString($id), $owner->ownerId()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Unarchive a product.
     *
     * POST /api/products/{id}/unarchive → 204.
     *
     * @throws ExceptionInterface
     */
    #[NoContent('Unarchived')]
    #[NotFound('Product not found.')]
    #[Route('/products/{id}/unarchive', name: 'unarchive', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function unarchive(#[CurrentUser] Owner $owner, string $id): Response
    {
        $this->commandBus->dispatch(new UnarchiveProduct(ProductId::fromString($id), $owner->ownerId()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
