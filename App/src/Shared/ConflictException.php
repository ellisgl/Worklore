<?php

declare(strict_types=1);

namespace App\Shared;

use DomainException;

/**
 * The request clashes with the current state, e.g. a name that's already
 * taken. The message is safe to show the client; the API answers 409 (see
 * Http\DomainExceptionListener).
 */
abstract class ConflictException extends DomainException
{
}
