<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\InvalidValueException;
use App\Shared\Query\Page;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The ?page=&per_page= query parameters of a list endpoint, both optional.
 */
final readonly class PageRequest
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Range(min: 1, max: Page::MAX_SIZE)]
        public int $perPage = Page::DEFAULT_SIZE,
    ) {
    }

    /**
     * @throws InvalidValueException never once validated
     */
    public function toPage(): Page
    {
        return new Page($this->page, $this->perPage);
    }
}
