<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Identity\Application\ChangePassword;
use App\Identity\Application\DeleteUser;
use App\Identity\Application\RegisterUser;
use App\Identity\Application\RevokeSessions;
use App\Identity\Application\UserNotFound;
use App\Identity\IncorrectPassword;
use App\Identity\Security\TokenManager;
use App\Identity\UserAlreadyExistsException;
use App\Identity\UserRepository;
use App\Shared\CommandBus;
use App\Shared\Secret;
use App\Shared\UserId;
use App\Tests\Functional\CreatesRecords;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Throwable;

/**
 * The Identity commands through the real command bus.
 */
final class IdentityCommandsTest extends KernelTestCase
{
    use CreatesRecords;

    private CommandBus $commands;
    private UserRepository $users;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->commands = self::getContainer()->get(CommandBus::class);
        $this->users = self::getContainer()->get(UserRepository::class);
    }

    /**
     * @throws Throwable
     */
    public function testRegisterUsesTheGivenIdAndRefusesATakenEmail(): void
    {
        $id = UserId::generate();

        $this->commands->dispatch(new RegisterUser($id, ' New@Example.com ', new Secret('correct-horse-battery')));

        self::assertSame('new@example.com', $this->users->find($id)?->email);
        $this->expectException(UserAlreadyExistsException::class);
        $this->commands->dispatch(new RegisterUser(UserId::generate(), 'NEW@example.com', new Secret('correct-horse-battery')));
    }

    /**
     * @throws Throwable
     */
    public function testChangingThePasswordChecksTheCurrentOneOnlyWhenGiven(): void
    {
        $id = UserId::generate();
        $this->commands->dispatch(new RegisterUser($id, 'me@example.com', new Secret('correct-horse-battery')));
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        try {
            $this->commands->dispatch(new ChangePassword($id, new Secret('staple-battery-horse'), new Secret('wrong-password')));
            self::fail('Expected IncorrectPassword');
        } catch (IncorrectPassword) {
        }

        $this->commands->dispatch(new ChangePassword($id, new Secret('staple-battery-horse')));   // an administrator: no check
        $user = $this->users->find($id);
        self::assertNotNull($user);
        self::assertTrue($hasher->isPasswordValid($user, 'staple-battery-horse'));
    }

    /**
     * @throws Throwable
     */
    public function testRevokingSessionsReturnsHowManyEnded(): void
    {
        $user = $this->owner();
        $tokens = self::getContainer()->get(TokenManager::class);
        $tokens->issue($user);
        $tokens->issue($user);

        self::assertSame(2, $this->commands->dispatch(new RevokeSessions($user->id)));
    }

    /**
     * @throws Throwable
     */
    public function testDeletingAUserDeletesTheirHistoryAndProjections(): void
    {
        $me = $this->owner();
        $this->project($this->product($this->company($me)), 'Launch', [$this->tag($this->category($me))->id()]);
        $db = self::getContainer()->get(Connection::class);
        self::assertGreaterThan(0, $db->fetchOne('SELECT count(*) FROM event_store'));

        $this->commands->dispatch(new DeleteUser($me->id));

        foreach (['event_store', 'company', 'product', 'project', 'project_tag', 'category', 'tag'] as $table) {
            self::assertEquals(0, $db->fetchOne("SELECT count(*) FROM $table"), $table);
        }
    }

    /**
     * @throws Throwable
     */
    public function testAnUnknownUserIsNotFound(): void
    {
        $this->expectException(UserNotFound::class);
        $this->commands->dispatch(new RevokeSessions(UserId::generate()));
    }
}
