<?php

declare(strict_types=1);

namespace App\Shared\Http\OpenApi;

use Attribute;
use OpenApi\Attributes as OA;

/**
 * OpenAPI: 422. Either the request failed validation (a problem document
 * with "violations", one per field), or it broke a rule the data must keep
 * ({"error": "..."}), e.g. a position that ends before it starts.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Invalid extends OA\Response
{
    public function __construct(string $rules = '')
    {
        parent::__construct(
            response: 422,
            description: 'Invalid request: the violations list each field'.('' === $rules ? '.' : '; or '.$rules),
            content: new OA\JsonContent(oneOf: [
                new OA\Schema(ref: '#/components/schemas/ValidationProblem'),
                new OA\Schema(ref: '#/components/schemas/Error'),
            ]),
        );
    }
}
