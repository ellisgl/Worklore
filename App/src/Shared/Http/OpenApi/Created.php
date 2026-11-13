<?php

declare(strict_types=1);

namespace App\Shared\Http\OpenApi;

use Attribute;
use OpenApi\Attributes as OA;

/**
 * OpenAPI: 201 with the new record's id (the server picks it).
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Created extends OA\Response
{
    public function __construct(string $description)
    {
        parent::__construct(response: 201, description: $description, content: new OA\JsonContent(
            required: ['id'],
            properties: [new OA\Property(property: 'id', type: 'string', format: 'uuid')],
            type: 'object',
        ));
    }
}
