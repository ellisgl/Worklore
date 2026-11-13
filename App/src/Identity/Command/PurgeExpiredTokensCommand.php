<?php

declare(strict_types=1);

namespace App\Identity\Command;

use App\Identity\Security\TokenManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:token:purge-expired', description: 'Delete sessions whose refresh token has expired')]
final readonly class PurgeExpiredTokensCommand
{
    public function __construct(private TokenManager $tokenManager)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->success(\sprintf('Purged %d expired session(s).', $this->tokenManager->purgeExpired()));

        return Command::SUCCESS;
    }
}
