<?php

declare(strict_types=1);

namespace App\Identity;

use App\Shared\Owner;
use App\Shared\UserId;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use function Symfony\Component\Clock\now;

/**
 * Doctrine mapping: src/Identity/Config/User.orm.xml.
 */
final class User implements UserInterface, PasswordAuthenticatedUserInterface, Owner
{
    private(set) UserId $id;
    /** Stored trimmed and lowercased; see normalizeEmail() */
    private(set) string $email;
    /** @var list<string> Extra roles only; getRoles() adds the implicit ROLE_USER */
    private array $roles = [];
    /** Password hash; read through getPassword() (required by the interface) */
    private ?string $password = null;
    private(set) ?DateTimeImmutable $archivedAt = null;
    private(set) DateTimeImmutable $createdAt;

    public function __construct(
        string $email,
        ?DateTimeImmutable $createdAt = null,
    ) {
        $this->id = UserId::generate();
        $this->createdAt = $createdAt ?? now()->setTimezone(new DateTimeZone('UTC'));
        $this->setEmail($email);
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function ownerId(): UserId
    {
        return $this->id;
    }

    public function setEmail(string $email): self
    {
        $this->email = self::normalizeEmail($email);

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * Every user implicitly has ROLE_USER.
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = array_values(array_unique(array_diff($roles, ['ROLE_USER'])));

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $hashedPassword): self
    {
        $this->password = $hashedPassword;

        return $this;
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    public function archive(DateTimeImmutable $at): self
    {
        $this->archivedAt ??= $at;  // already archived: keep the original time

        return $this;
    }

    public function unarchive(): self
    {
        $this->archivedAt = null;

        return $this;
    }
}
