<?php

declare(strict_types=1);

namespace App\Shared\Security;

use App\Shared\UserId;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Whether the current request is an admin acting as another user, and who
 * that admin is. Identity's access token handler marks such requests (the
 * request attribute below); everything else only reads this.
 */
final readonly class Impersonation
{
    public const string ATTRIBUTE = '_impersonator_id';

    public function __construct(private RequestStack $requests)
    {
    }

    /**
     * The admin behind the current request, if it is an impersonation.
     */
    public function impersonatorId(): ?UserId
    {
        $id = $this->requests->getMainRequest()?->attributes->get(self::ATTRIBUTE);

        return $id instanceof UserId ? $id : null;
    }

    public function isActive(): bool
    {
        return null !== $this->impersonatorId();
    }
}
