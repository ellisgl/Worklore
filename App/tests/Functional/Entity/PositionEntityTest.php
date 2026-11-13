<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Company\CompanyEntity;
use App\Company\PositionEntity;
use App\Identity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use InvalidArgumentException;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PositionEntityTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testUserCanHoldSeveralPositionsAtOneCompany(): void
    {
        $user = new User('a@example.com');
        $company = new CompanyEntity($user, 'Acme');
        ($company->addPosition('Developer', new DateTimeImmutable('2019-03-01')))
            ->end(new DateTimeImmutable('2022-05-31'), 'Promoted');
        $company->addPosition('Senior Developer', new DateTimeImmutable('2022-06-01'));
        $company->addPosition('Intern', new DateTimeImmutable('2018-06-01'));

        self::assertCount(3, $company->getPositions(), 'Constructor adds the position to the company');

        $this->em->persist($user);
        $this->em->persist($company);
        foreach ($company->getPositions() as $position) {
            $this->em->persist($position);
        }
        $this->em->flush();
        $this->em->clear();

        $company = $this->em->find(CompanyEntity::class, $company->id);
        $titles = array_map(static fn (PositionEntity $p) => $p->title->value, $company->getPositions()->toArray());
        self::assertSame(['Senior Developer', 'Developer', 'Intern'], $titles, 'Newest start date first');

        $developer = $company->getPositions()[1];
        self::assertFalse($developer->isCurrent());
        self::assertSame('2022-05-31', $developer->period->end->format('Y-m-d'));
        self::assertSame('Promoted', $developer->reasonLeft);
        self::assertTrue($company->getPositions()[0]->isCurrent());
    }

    public function testDatesDropTheTime(): void
    {
        $position = $this->position(new DateTimeImmutable('2024-01-15 17:45:00'));
        $position->end(new DateTimeImmutable('2024-02-01 09:30:00'));

        self::assertSame('2024-01-15 00:00:00', $position->period->start->format('Y-m-d H:i:s'));
        self::assertSame('2024-02-01 00:00:00', $position->period->end->format('Y-m-d H:i:s'));
    }

    public function testCanStartAndEndOnTheSameDay(): void
    {
        $position = $this->position(new DateTimeImmutable('2024-01-15 09:00'));

        $position->end(new DateTimeImmutable('2024-01-15 08:00'));

        self::assertSame('2024-01-15', $position->period->end->format('Y-m-d'));
    }

    public function testCannotEndBeforeStart(): void
    {
        $position = $this->position(new DateTimeImmutable('2024-01-15'));

        $this->expectException(InvalidArgumentException::class);
        $position->end(new DateTimeImmutable('2024-01-14'));
    }

    public function testCannotMoveStartAfterEnd(): void
    {
        $position = $this->position(new DateTimeImmutable('2024-01-15'))->end(new DateTimeImmutable('2024-06-30'));

        $this->expectException(InvalidArgumentException::class);
        $position->setStartDate(new DateTimeImmutable('2024-07-01'));
    }

    public function testReopenMakesPositionCurrentAndClearsReason(): void
    {
        $position = $this->position()->end(new DateTimeImmutable('2024-06-30'), 'Contract ended');

        $position->reopen();

        self::assertTrue($position->isCurrent());
        self::assertNull($position->reasonLeft);
    }

    public function testCurrentPositionHasNoReasonLeft(): void
    {
        $this->expectException(LogicException::class);
        $this->position()->setReasonLeft('Moved away');
    }

    public function testBlankTitleIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->position(title: '   ');
    }

    public function testDeletingCompanyDeletesItsPositions(): void
    {
        $user = new User('a@example.com');
        $company = new CompanyEntity($user, 'Acme');
        $position = $company->addPosition('Developer', new DateTimeImmutable('2024-01-15'));
        foreach ([$user, $company, $position] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();

        $this->em->remove($company);
        $this->em->flush();
        $this->em->clear();

        self::assertSame(0, $this->em->getRepository(PositionEntity::class)->count());
    }

    private function position(?DateTimeImmutable $start = null, string $title = 'Developer'): PositionEntity
    {
        $user = new User('a@example.com');

        return new CompanyEntity($user, 'Acme')->addPosition($title, $start ?? new DateTimeImmutable('2024-01-15'));
    }
}
