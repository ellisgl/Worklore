<?php

declare(strict_types=1);

namespace App\Shared;

use InvalidArgumentException;

/**
 * A value broke a domain rule: a blank name, a period ending before it
 * starts, text over a length limit. The message is safe to show the client;
 * the API answers 422 (see Http\DomainExceptionListener).
 *
 * Still an InvalidArgumentException, so existing catch blocks keep working.
 */
class InvalidValueException extends InvalidArgumentException
{
}
