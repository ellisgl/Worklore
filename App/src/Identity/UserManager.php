<?php

declare(strict_types=1);

namespace App\Identity;

use App\Identity\Security\TokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Account lifecycle: shared by console commands and (later) API endpoints.
 */
final readonly class UserManager
{
    public const int MIN_PASSWORD_LENGTH = 12;

    public function __construct(
        private UserRepository $users,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private ValidatorInterface $validator,
        private TokenManager $tokenManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<string> $roles
     *
     * @throws ValidationFailedException
     * @throws UserAlreadyExistsException
     */
    public function create(string $email, #[SensitiveParameter] string $plainPassword, array $roles = []): User
    {
        $this->assertValid(User::normalizeEmail($email), [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)]);
        $this->assertValidPassword($plainPassword);

        if (null !== $this->users->findOneByEmail($email)) {
            throw new UserAlreadyExistsException($email);
        }

        $user = new User($email);
        $user->setRoles($roles);
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * Sets a new password and signs the user out everywhere.
     *
     * @return int number of sessions revoked
     *
     * @throws ValidationFailedException
     */
    public function changePassword(User $user, #[SensitiveParameter] string $plainPassword): int
    {
        $this->assertValidPassword($plainPassword);

        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
        $this->em->flush();

        return $this->tokenManager->revokeAllForUser($user);
    }

    /**
     * Blocks the user from logging in and signs them out everywhere. Their data
     * stays; unarchive() restores access. Archiving twice keeps the first time.
     *
     * @return int number of sessions revoked
     */
    public function archive(User $user): int
    {
        $user->archive($this->clock->now());
        $this->em->flush();

        return $this->tokenManager->revokeAllForUser($user);
    }

    public function unarchive(User $user): void
    {
        $user->unarchive();
        $this->em->flush();
    }

    /**
     * Deletes the user; their sessions go with them (ON DELETE CASCADE).
     */
    public function delete(User $user): void
    {
        $this->em->remove($user);
        $this->em->flush();
    }

    private function assertValidPassword(#[SensitiveParameter] string $plainPassword): void
    {
        $this->assertValid($plainPassword, [
            new Assert\NotBlank(),
            // 4096 cap: hashing absurdly long inputs is a DoS vector.
            new Assert\Length(min: self::MIN_PASSWORD_LENGTH, max: 4096),
        ]);
    }

    /**
     * @param list<Constraint> $constraints
     */
    private function assertValid(mixed $value, array $constraints): void
    {
        $violations = $this->validator->validate($value, $constraints);
        if (\count($violations) > 0) {
            throw new ValidationFailedException($value, $violations);
        }
    }
}
