<?php

declare(strict_types=1);

namespace App\Identity\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Exception\ValidationFailedException;

use function is_string;

/**
 * Passwords are only ever prompted for (hidden), never taken as arguments,
 * so they don't end up in shell history or the process list.
 */
final class PasswordPrompt
{
    public static function askNew(InputInterface $input, SymfonyStyle $io): ?string
    {
        if (!$input->isInteractive()) {
            $io->error('A password must be entered interactively; run without -n (with docker compose exec, keep the TTY).');

            return null;
        }

        $password = self::askHidden($io, 'New password');
        if ($password !== self::askHidden($io, 'Repeat password')) {
            $io->error('The passwords do not match.');

            return null;
        }

        return $password;
    }

    /**
     * The answer, or '' if nothing was entered (the password rules reject it).
     */
    private static function askHidden(SymfonyStyle $io, string $question): string
    {
        $answer = $io->askHidden($question);

        return is_string($answer) ? $answer : '';
    }

    public static function printViolations(SymfonyStyle $io, ValidationFailedException $e): void
    {
        foreach ($e->getViolations() as $violation) {
            $io->error((string) $violation->getMessage());
        }
    }
}
