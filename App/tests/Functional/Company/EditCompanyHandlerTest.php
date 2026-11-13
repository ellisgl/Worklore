<?php

declare(strict_types=1);

namespace App\Tests\Functional\Company;

use App\Company\Application\CompanyNotFound;
use App\Company\Application\EditCompany;
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

use function array_values;

/**
 * EditCompany through the real command bus and its transaction.
 */
final class EditCompanyHandlerTest extends KernelTestCase
{
    use FindsEntities;

    private EntityManagerInterface $em;
    private MessageBusInterface $bus;
    private User $owner;
    private CompanyEntity $company;

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->bus = self::getContainer()->get('command.bus');
        $this->owner = new User('a@example.com');
        $this->company = new CompanyEntity($this->owner, 'Acme', 'Widgets');
        $this->em->persist($this->owner);
        $this->em->persist($this->company);
        $this->em->flush();
    }

    /**
     * @throws ExceptionInterface
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testAppliesBothChanges(): void
    {
        $this->bus->dispatch(new EditCompany($this->company->id, $this->owner->id, 'Acme Ltd', 'Gadgets'));

        $this->em->clear();
        $company = self::findOrFail($this->em, CompanyEntity::class, $this->company->id);
        self::assertSame('Acme Ltd', $company->name->value);
        self::assertSame('Gadgets', $company->overview);
    }

    /**
     * @throws ExceptionInterface
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testInvalidNameChangesNothing(): void
    {
        $e = $this->dispatchExpectingFailure(new EditCompany($this->company->id, $this->owner->id, '   ', 'Gadgets'));

        self::assertInstanceOf(InvalidValueException::class, $e);
        $this->em->clear();
        self::assertSame('Widgets', self::findOrFail($this->em, CompanyEntity::class, $this->company->id)->overview);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testUnknownCompanyIsNotFound(): void
    {
        $e = $this->dispatchExpectingFailure(new EditCompany(CompanyId::generate(), $this->owner->id, 'Acme Ltd'));

        self::assertInstanceOf(CompanyNotFound::class, $e);
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
