<?php

declare(strict_types=1);

namespace App\Entity;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Doctrine mapping: config/doctrine/User.orm.xml.
 */
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    private ?int $id = null;

    private string $email;

    /** @var list<string> */
    private array $roles = [];

    private ?string $password = null;

    private \DateTimeImmutable $createdAt;

    public function __construct(string $email)
    {
        $this->setEmail($email);
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = self::normalizeEmail($email);
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
    public function setRoles(array $roles): void
    {
        $this->roles = array_values(array_unique(array_diff($roles, ['ROLE_USER'])));
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
