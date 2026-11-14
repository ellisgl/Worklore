<?php

declare(strict_types=1);

namespace App\Journal;

use App\Shared\NotFoundException;

final class MediaNotFound extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Media not found.');
    }
}
