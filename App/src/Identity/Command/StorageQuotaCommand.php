<?php

declare(strict_types=1);

namespace App\Identity\Command;

use App\Identity\Application\SetStorageQuota;
use App\Identity\StorageQuotaPolicy;
use App\Identity\UserRepository;
use App\Shared\ByteSize;
use App\Shared\CommandBus;
use App\Shared\InvalidValueException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Throwable;

use function sprintf;

#[AsCommand(name: 'app:user:quota', description: 'Show or set how much media a user may store')]
final readonly class StorageQuotaCommand
{
    public function __construct(
        private UserRepository $users,
        private StorageQuotaPolicy $quotas,
        private CommandBus $commands,
    ) {
    }

    /**
     * @throws ExceptionInterface
     * @throws Throwable
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Email address of the user')] string $email,
        #[Argument(description: 'A size such as 500M or 2G, or "default" for MEDIA_USER_QUOTA; leave out to show the current quota')] ?string $quota = null,
    ): int {
        $user = $this->users->findOneByEmail($email);
        if (null === $user) {
            $io->error(sprintf('No user with email "%s".', $email));

            return Command::FAILURE;
        }

        if (null !== $quota) {
            try {
                $bytes = 'default' === $quota ? null : ByteSize::parse($quota);
            } catch (InvalidValueException $e) {
                $io->error($e->getMessage());

                return Command::INVALID;
            }
            $this->commands->dispatch(new SetStorageQuota($user->id, $bytes));
        }

        $limit = $this->quotas->limitFor($user->id);
        $io->success(null === $user->storageQuota
            ? sprintf('%s may store %s (the default, MEDIA_USER_QUOTA).', $user->email, ByteSize::format($limit))
            : sprintf('%s may store %s (their own quota).', $user->email, ByteSize::format($limit)));

        return Command::SUCCESS;
    }
}
