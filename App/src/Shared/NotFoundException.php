<?php

declare(strict_types=1);

namespace App\Shared;

use DomainException;

/**
 * The requested record doesn't exist, or belongs to another owner (the two
 * look the same, so other users' records aren't revealed). The API answers
 * 404 with the message (see Http\DomainExceptionListener).
 */
abstract class NotFoundException extends DomainException
{
}
