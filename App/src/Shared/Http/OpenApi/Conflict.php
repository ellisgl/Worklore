<?php

declare(strict_types=1);

namespace App\Shared\Http\OpenApi;

use Attribute;
use OpenApi\Attributes as OA;

/**
 * OpenAPI: 409 {"error": "..."}, e.g. a name that is already taken.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Conflict extends OA\Response
{
    public function __construct(string $description, string $example)
    {
        parent::__construct(response: 409, description: $description, content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['error' => $example]));
    }
}
