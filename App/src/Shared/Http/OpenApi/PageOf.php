<?php

declare(strict_types=1);

namespace App\Shared\Http\OpenApi;

use Attribute;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * OpenAPI: 200 with one page of $class read models, in the
 * {"items", "page", "per_page", "total"} envelope (App\Shared\Query\Paginated).
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class PageOf extends OA\Response
{
    /**
     * @param class-string $class
     */
    public function __construct(string $class, string $description)
    {
        parent::__construct(response: 200, description: $description, content: new OA\JsonContent(
            required: ['items', 'page', 'per_page', 'total'],
            properties: [
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: new Model(type: $class))),
                new OA\Property(property: 'page', description: 'This page, from 1', type: 'integer', example: 1),
                new OA\Property(property: 'per_page', description: 'Items per page', type: 'integer', example: 50),
                new OA\Property(property: 'total', description: 'Items across all pages', type: 'integer', example: 1),
            ],
            type: 'object',
        ));
    }
}
