<?php

declare(strict_types=1);

namespace App\Shared\Security;

use Attribute;

/**
 * On a controller action: only an admin impersonating the user may call it
 * (403 otherwise), e.g. purging records. See ImpersonationGuard.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final readonly class OnlyWhileImpersonating
{
}
