<?php

declare(strict_types=1);

namespace App\Shared\Security;

use Attribute;

/**
 * On a controller action: refused (403) when an admin is impersonating the
 * user, e.g. changing their password. See ImpersonationGuard.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final readonly class NotWhileImpersonating
{
}
