<?php

declare(strict_types=1);

namespace App\Shared\Http\OpenApi;

use Attribute;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * OpenAPI: 200 with one $class read model.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Item extends OA\Response
{
    /**
     * @param class-string $class
     */
    public function __construct(string $class, string $description)
    {
        parent::__construct(response: 200, description: $description, content: new OA\JsonContent(ref: new Model(type: $class)));
    }
}
