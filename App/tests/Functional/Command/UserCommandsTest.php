<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Identity\ApiTokenRepository;
use App\Identity\Security\TokenManager;
use App\Identity\User;
use App\Identity\UserManager;
use App\Identity\UserRepository;
use App\Shared\UserId;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\UuidV7;

final class UserCommandsTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private const string PASSWORD = 'correct-horse-battery';
    private const string NEW_PASSWORD = 'new-password-4567';

    private UserRepository $users;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->users = self::getContainer()->get(UserRepository::class);
    }

    // ---- app:user:create --------------------------------------------------

    public function testCreateUser(): void
    {
        $tester = $this->command('app:user:create');
        $tester->setInputs([self::PASSWORD, self::PASSWORD]);

        $tester->execute(['email' => 'New.User@Example.com']);

        $tester->assertCommandIsSuccessful();
        $user = $this->users->findOneByEmail('new.user@example.com');
        self::assertNotNull($user);
        self::assertInstanceOf(UserId::class, $user->id);
        self::assertInstanceOf(UuidV7::class, $user->id->uuid, 'IDs are UUIDv7');
        self::assertStringContainsString($user->id->toRfc4122(), $tester->getDisplay());
        self::assertSame('new.user@example.com', $user->email, 'Email is stored lowercased');
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertNotSame(self::PASSWORD, $user->getPassword(), 'Password must be hashed');
        self::assertTrue($this->passwordMatches($user, self::PASSWORD));
    }

    public function testCreateAdmin(): void
    {
        $tester = $this->command('app:user:create');
        $tester->setInputs([self::PASSWORD, self::PASSWORD]);

        $tester->execute(['email' => 'admin@example.com', '--admin' => true]);

        $tester->assertCommandIsSuccessful();
        self::assertEqualsCanonicalizing(['ROLE_ADMIN', 'ROLE_USER'], $this->users->findOneByEmail('admin@example.com')->getRoles());
    }

    public function testCreateRejectsDuplicateEmailCaseInsensitively(): void
    {
        $this->createUser('user@example.com');
        $tester = $this->command('app:user:create');
        $tester->setInputs([self::PASSWORD, self::PASSWORD]);

        $tester->execute(['email' => 'USER@example.com']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('already exists', $tester->getDisplay());
    }

    public function testCreateRejectsShortPassword(): void
    {
        $tester = $this->command('app:user:create');
        $tester->setInputs(['short', 'short']);

        $tester->execute(['email' => 'user@example.com']);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertStringContainsString('too short', $tester->getDisplay());
        self::assertNull($this->users->findOneByEmail('user@example.com'));
    }

    public function testCreateRejectsInvalidEmail(): void
    {
        $tester = $this->command('app:user:create');
        $tester->setInputs([self::PASSWORD, self::PASSWORD]);

        $tester->execute(['email' => 'not-an-email']);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertStringContainsString('not a valid email', $tester->getDisplay());
    }

    public function testCreateRejectsMismatchedPasswords(): void
    {
        $tester = $this->command('app:user:create');
        $tester->setInputs([self::PASSWORD, 'something-else-123']);

        $tester->execute(['email' => 'user@example.com']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('do not match', $tester->getDisplay());
        self::assertNull($this->users->findOneByEmail('user@example.com'));
    }

    public function testCreateRefusesToRunNonInteractively(): void
    {
        $tester = $this->command('app:user:create');

        $tester->execute(['email' => 'user@example.com'], ['interactive' => false]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertNull($this->users->findOneByEmail('user@example.com'));
    }

    // ---- app:user:change-password -----------------------------------------

    public function testChangePasswordUpdatesHashAndRevokesSessions(): void
    {
        $user = $this->createUser('user@example.com');
        $tokenManager = self::getContainer()->get(TokenManager::class);
        $session1 = $tokenManager->issue($user);
        $session2 = $tokenManager->issue($user);

        $tester = $this->command('app:user:change-password');
        $tester->setInputs([self::NEW_PASSWORD, self::NEW_PASSWORD]);
        $tester->execute(['email' => 'user@example.com']);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('2 session(s) revoked', $tester->getDisplay());

        $user = $this->reload($user);
        self::assertTrue($this->passwordMatches($user, self::NEW_PASSWORD));
        self::assertFalse($this->passwordMatches($user, self::PASSWORD));
        self::assertNull($tokenManager->findValidByAccessToken($session1->accessToken));
        self::assertNull($tokenManager->findValidByAccessToken($session2->accessToken));
    }

    public function testChangePasswordRejectsShortPasswordAndKeepsOldOne(): void
    {
        $user = $this->createUser('user@example.com');
        $session = self::getContainer()->get(TokenManager::class)->issue($user);

        $tester = $this->command('app:user:change-password');
        $tester->setInputs(['short', 'short']);
        $tester->execute(['email' => 'user@example.com']);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertTrue($this->passwordMatches($this->reload($user), self::PASSWORD));
        self::assertNotNull(self::getContainer()->get(TokenManager::class)->findValidByAccessToken($session->accessToken), 'Sessions survive a failed change');
    }

    public function testChangePasswordForUnknownUserFails(): void
    {
        $tester = $this->command('app:user:change-password');

        $tester->execute(['email' => 'nobody@example.com']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('No user', $tester->getDisplay());
    }

    // ---- app:user:delete --------------------------------------------------

    public function testDeleteWithForceRemovesUserAndSessions(): void
    {
        $user = $this->createUser('user@example.com');
        self::getContainer()->get(TokenManager::class)->issue($user);
        $userId = $user->id;

        $tester = $this->command('app:user:delete');
        $tester->execute(['email' => 'user@example.com', '--force' => true]);

        $tester->assertCommandIsSuccessful();
        self::getContainer()->get('doctrine')->getManager()->clear();
        self::assertNull($this->users->findOneByEmail('user@example.com'));
        self::assertSame(0, self::getContainer()->get(ApiTokenRepository::class)->count(['user' => $userId]));
    }

    public function testDeleteAsksForConfirmation(): void
    {
        $this->createUser('user@example.com');

        $tester = $this->command('app:user:delete');
        $tester->setInputs(['yes']);
        $tester->execute(['email' => 'user@example.com']);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Permanently delete user@example.com?', $tester->getDisplay());
        self::assertNull($this->users->findOneByEmail('user@example.com'));
    }

    public function testDeleteAbortsWhenNotConfirmed(): void
    {
        $this->createUser('user@example.com');

        $tester = $this->command('app:user:delete');
        $tester->setInputs(['no']);
        $tester->execute(['email' => 'user@example.com']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertNotNull($this->users->findOneByEmail('user@example.com'));
    }

    public function testDeleteNonInteractiveWithoutForceDoesNothing(): void
    {
        $this->createUser('user@example.com');

        $tester = $this->command('app:user:delete');
        $tester->execute(['email' => 'user@example.com'], ['interactive' => false]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertNotNull($this->users->findOneByEmail('user@example.com'));
    }

    public function testDeleteUnknownUserFails(): void
    {
        $tester = $this->command('app:user:delete');

        $tester->execute(['email' => 'nobody@example.com', '--force' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    // ---- app:user:archive / app:user:unarchive ----------------------------

    public function testArchiveBlocksUserAndRevokesSessions(): void
    {
        $user = $this->createUser('user@example.com');
        $tokenManager = self::getContainer()->get(TokenManager::class);
        $session = $tokenManager->issue($user);
        $clock = self::mockTime('2026-11-13 12:00:00');

        $tester = $this->command('app:user:archive');
        $tester->execute(['email' => 'user@example.com']);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('revoked 1 session(s)', $tester->getDisplay());
        $user = $this->reload($user);
        self::assertTrue($user->isArchived());
        self::assertEquals($clock->now(), $user->archivedAt);
        self::assertNull($tokenManager->findValidByAccessToken($session->accessToken));
    }

    public function testArchiveTwiceKeepsOriginalTime(): void
    {
        $user = $this->createUser('user@example.com');
        $clock = self::mockTime('2026-11-13 12:00:00');
        $this->command('app:user:archive')->execute(['email' => 'user@example.com']);
        $clock->sleep(3600);

        $tester = $this->command('app:user:archive');
        $tester->execute(['email' => 'user@example.com']);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('already archived', $tester->getDisplay());
        self::assertEquals(new DateTimeImmutable('2026-11-13 12:00:00'), $this->reload($user)->archivedAt);
    }

    public function testUnarchiveRestoresUser(): void
    {
        $user = $this->createUser('user@example.com');
        $this->command('app:user:archive')->execute(['email' => 'user@example.com']);

        $tester = $this->command('app:user:unarchive');
        $tester->execute(['email' => 'user@example.com']);

        $tester->assertCommandIsSuccessful();
        self::assertFalse($this->reload($user)->isArchived());
    }

    public function testArchiveAndUnarchiveUnknownUserFail(): void
    {
        foreach (['app:user:archive', 'app:user:unarchive'] as $name) {
            $tester = $this->command($name);
            $tester->execute(['email' => 'nobody@example.com']);
            self::assertSame(Command::FAILURE, $tester->getStatusCode(), $name);
        }
    }

    // ---- app:token:purge-expired ------------------------------------------

    public function testPurgeExpiredRemovesOnlyExpiredSessions(): void
    {
        $user = $this->createUser('user@example.com');
        $tokenManager = self::getContainer()->get(TokenManager::class);
        $clock = self::mockTime();

        $tokenManager->issue($user);                // will expire
        $clock->sleep(2592000 - 10);
        $fresh = $tokenManager->issue($user);       // still valid after the jump below
        $clock->sleep(20);

        $tester = $this->command('app:token:purge-expired');
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Purged 1 expired session(s)', $tester->getDisplay());
        self::assertSame(1, self::getContainer()->get(ApiTokenRepository::class)->count([]));
        self::assertNotNull($tokenManager->findValidByAccessToken($fresh->accessToken));
    }

    // ---- helpers ----------------------------------------------------------

    private function command(string $name): CommandTester
    {
        return new CommandTester(new Application(self::$kernel)->find($name));
    }

    private function createUser(string $email): User
    {
        return self::getContainer()->get(UserManager::class)->create($email, self::PASSWORD);
    }

    private function reload(User $user): User
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();

        return $em->find(User::class, $user->id);
    }

    private function passwordMatches(User $user, string $plain): bool
    {
        return self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, $plain);
    }
}
