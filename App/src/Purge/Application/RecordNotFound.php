<?php

declare(strict_types=1);

namespace App\Purge\Application;

use App\Shared\NotFoundException;

final class RecordNotFound extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Not found.');
    }
}
