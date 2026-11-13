<?php

declare(strict_types=1);

namespace App\Shared\Http\OpenApi;

use Attribute;
use OpenApi\Attributes as OA;

/**
 * OpenAPI: 204, the change was saved.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class NoContent extends OA\Response
{
    public function __construct(string $description)
    {
        parent::__construct(response: 204, description: $description);
    }
}
