<?php

declare(strict_types=1);

namespace App\Tests\Functional\Company;

use App\Company\Application\CompanyNameTaken;
use App\Company\Application\CompanyNotFound;
use App\Company\Application\CreateCompany;
use App\Company\Application\EditCompany;
use App\Company\CompanyId;
use App\Company\CompanyRepository;
use App\Identity\User;
use App\Shared\InvalidValueException;
use App\Tests\Functional\CreatesCompanyRecords;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

use function array_values;

/**
 * The company commands through the real command bus: what they store as
 * events, and that a refused command stores nothing.
 */
final class CompanyCommandsTest extends KernelTestCase
{
    use CreatesCompanyRecords;

    private MessageBusInterface $bus;
    private CompanyRepository $companies;
    private User $me;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->bus = self::getContainer()->get('command.bus');
        $this->companies = self::getContainer()->get(CompanyRepository::class);
        $this->me = $this->owner();
    }

    /**
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function testCreatesTheCompanyWithTheGivenId(): void
    {
        $id = CompanyId::generate();

        $this->bus->dispatch(new CreateCompany($id, $this->me->id, 'Acme', 'Widgets'));

        $company = $this->companies->findForOwner($id, $this->me->id);
        self::assertNotNull($company);
        self::assertSame('Acme', $company->name->value);
        self::assertSame('Widgets', $company->overview);
        self::assertSame(['company.created'], $this->eventTypes());
    }

    /**
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function testTakenNameFailsWithCompanyNameTaken(): void
    {
        $this->bus->dispatch(new CreateCompany(CompanyId::generate(), $this->me->id, 'Acme'));

        $e = $this->dispatchExpectingFailure(new CreateCompany(CompanyId::generate(), $this->me->id, ' acme '));

        self::assertInstanceOf(CompanyNameTaken::class, $e);
        self::assertSame(['company.created'], $this->eventTypes(), 'Nothing stored for the second');
    }

    /**
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function testInvalidNameFailsAndStoresNothing(): void
    {
        $e = $this->dispatchExpectingFailure(new CreateCompany(CompanyId::generate(), $this->me->id, '   '));

        self::assertInstanceOf(InvalidValueException::class, $e);
        self::assertSame([], $this->eventTypes());
    }

    /**
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function testEditRecordsBothChanges(): void
    {
        $acme = $this->company($this->me, 'Acme', 'Widgets');

        $this->bus->dispatch(new EditCompany($acme->id(), $this->me->id, 'Acme Ltd', ''));

        $company = $this->companies->findForOwner($acme->id(), $this->me->id);
        self::assertNotNull($company);
        self::assertSame('Acme Ltd', $company->name->value);
        self::assertNull($company->overview, 'An empty overview clears it');
        self::assertSame(['company.created', 'company.renamed', 'company.overview_changed'], $this->eventTypes());
    }

    /**
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function testInvalidEditChangesNothing(): void
    {
        $acme = $this->company($this->me, 'Acme', 'Widgets');

        $e = $this->dispatchExpectingFailure(new EditCompany($acme->id(), $this->me->id, '  ', 'New overview'));

        self::assertInstanceOf(InvalidValueException::class, $e);
        self::assertSame(['company.created'], $this->eventTypes(), 'The overview change is not stored either');
    }

    /**
     * @throws ExceptionInterface
     */
    public function testAnotherOwnersOrAnUnknownCompanyIsNotFound(): void
    {
        $theirs = $this->company($this->owner('other@example.com'), 'Theirs');

        foreach ([$theirs->id(), CompanyId::generate()] as $id) {
            self::assertInstanceOf(CompanyNotFound::class, $this->dispatchExpectingFailure(new EditCompany($id, $this->me->id, 'Mine')));
        }
    }

    /**
     * @return list<mixed>
     *
     * @throws Exception
     */
    private function eventTypes(): array
    {
        return self::getContainer()->get(Connection::class)->fetchFirstColumn("SELECT event_type FROM event_store WHERE stream_type = 'company' ORDER BY sequence");
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
