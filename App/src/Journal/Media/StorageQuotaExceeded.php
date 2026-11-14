<?php

declare(strict_types=1);

namespace App\Journal\Media;

use App\Shared\ByteSize;
use App\Shared\ConflictException;

use function max;
use function sprintf;

final class StorageQuotaExceeded extends ConflictException
{
    public function __construct(int $size, int $used, int $limit)
    {
        parent::__construct(sprintf(
            'Not enough storage left: the file is %s, and you have %s of your %s left.',
            ByteSize::format($size),
            ByteSize::format(max(0, $limit - $used)),
            ByteSize::format($limit),
        ));
    }
}
