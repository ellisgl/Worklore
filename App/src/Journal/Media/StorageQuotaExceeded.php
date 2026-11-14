<?php

declare(strict_types=1);

namespace App\Journal\Media;

use App\Shared\ByteSize;
use App\Shared\ConflictException;

use function max;
use function sprintf;

final class StorageQuotaExceeded extends ConflictException
{
    /**
     * @param string $what what needs the space, e.g. "the file is"
     */
    public function __construct(int $size, int $used, int $limit, string $what = 'the file is')
    {
        parent::__construct(sprintf(
            'Not enough storage left: %s %s, and you have %s of your %s left.',
            $what,
            ByteSize::format($size),
            ByteSize::format(max(0, $limit - $used)),
            ByteSize::format($limit),
        ));
    }
}
