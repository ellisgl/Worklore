<?php

declare(strict_types=1);

namespace App\Identity\Security;

use App\Identity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Blocks archived users on every authenticator of the api firewall: the
 * password login and Bearer access tokens. (Refresh isn't an authenticator;
 * TokenManager::refresh() checks it itself.)
 */
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
    }

    /**
     * Runs after the password was verified, so a wrong password still gets the
     * generic "Invalid credentials." and doesn't reveal the account's status.
     */
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if ($user instanceof User && $user->isArchived()) {
            throw new CustomUserMessageAccountStatusException('Account is archived.');
        }
    }
}
