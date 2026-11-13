<?php

declare(strict_types=1);

namespace App\Shared\Http\OpenApi;

use Attribute;
use OpenApi\Attributes as OA;

use function implode;

/**
 * OpenAPI: 404 {"error": "..."}. Records that belong to another user
 * answer the same way as ones that don't exist.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class NotFound extends OA\Response
{
    public function __construct(string ...$messages)
    {
        parent::__construct(
            response: 404,
            description: 'Not found, or it belongs to another user: '.implode(' / ', $messages),
            content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['error' => $messages[0] ?? 'Not found.']),
        );
    }
}
