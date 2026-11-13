<?php

declare(strict_types=1);

namespace App\Tests\Functional\Company;

use App\Company\Application\CompanyNameTaken;
use App\Company\Application\CreateCompany;
use App\Company\CompanyEntity;
use App\Company\CompanyId;
use App\Identity\User;
use App\Shared\InvalidValueException;
use App\Tests\Functional\FindsEntities;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * CreateCompany through the real command bus, including its transaction
 * middleware.
 */
final class CreateCompanyHandlerTest extends KernelTestCase
{
    use FindsEntities;

    private EntityManagerInterface $em;
    private MessageBusInterface $bus;
    private User $owner;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->bus = self::getContainer()->get('command.bus');
        $this->owner = new User('a@example.com');
        $this->em->persist($this->owner);
        $this->em->flush();
    }

    /**
     * @throws ExceptionInterface
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testCreatesTheCompanyWithTheGivenId(): void
    {
        $id = CompanyId::generate();

        $this->bus->dispatch(new CreateCompany($id, $this->owner->id, 'Acme', 'Widgets'));

        // No flush here: the bus's doctrine_transaction middleware did it.
        $this->em->clear();
        $company = self::findOrFail($this->em, CompanyEntity::class, $id);
        self::assertSame('Acme', $company->name->value);
        self::assertTrue($this->owner->id->equals($company->owner->ownerId()));
    }

    /**
     * @throws ExceptionInterface
     */
    public function testTakenNameFailsWithCompanyNameTaken(): void
    {
        $this->bus->dispatch(new CreateCompany(CompanyId::generate(), $this->owner->id, 'Acme'));

        $e = $this->dispatchExpectingFailure(new CreateCompany(CompanyId::generate(), $this->owner->id, ' acme '));

        self::assertInstanceOf(CompanyNameTaken::class, $e);
        self::assertSame(1, $this->em->getRepository(CompanyEntity::class)->count());
    }

    /**
     * @throws ExceptionInterface
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testInvalidNameFailsAndSavesNothing(): void
    {
        $id = CompanyId::generate();

        $e = $this->dispatchExpectingFailure(new CreateCompany($id, $this->owner->id, '   '));

        self::assertInstanceOf(InvalidValueException::class, $e);
        $this->em->clear();
        self::assertNull($this->em->find(CompanyEntity::class, $id));
    }

    /**
     * Messenger wraps what the handler threw; returns that.
     *
     * @throws ExceptionInterface
     */
    private function dispatchExpectingFailure(object $command): Throwable
    {
        try {
            $this->bus->dispatch($command);
        } catch (HandlerFailedException $e) {
            $wrapped = $e->getWrappedExceptions();
            self::assertCount(1, $wrapped, 'A command has exactly one handler');

            return array_values($wrapped)[0];
        }

        self::fail('Expected the command to fail');
    }
}
