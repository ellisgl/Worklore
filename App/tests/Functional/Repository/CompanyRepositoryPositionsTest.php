<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Company\CompanyEntity;
use App\Company\CompanyRepository;
use App\Company\PositionEntity;
use App\Identity\User;
use App\Tests\Functional\FindsEntities;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CompanyRepositoryPositionsTest extends KernelTestCase
{
    use FindsEntities;

    private EntityManagerInterface $em;
    private CompanyRepository $positions;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->positions = self::getContainer()->get(CompanyRepository::class);
    }

    /**
     * @throws DateMalformedStringException
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testReturnsPositionsOldestFirst(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $acme = $this->persist(new CompanyEntity($user, 'Acme'));
        // Inserted out of order on purpose
        $this->position($acme, 'Senior Developer', '2022-06-01');
        $this->position($acme, 'Intern', '2018-06-01');
        $this->position($acme, 'Developer', '2019-03-01');
        $this->em->flush();
        $this->em->clear();

        $result = $this->positions->getAllPositionsByCompany(
            self::findOrFail($this->em, User::class, $user->id),
            self::findOrFail($this->em, CompanyEntity::class, $acme->id),
        );

        self::assertSame(['Senior Developer', 'Developer', 'Intern'], $this->titles($result));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testExcludesPositionsAtOtherCompanies(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $acme = $this->persist(new CompanyEntity($user, 'Acme'));
        $globex = $this->persist(new CompanyEntity($user, 'Globex'));
        $this->position($acme, 'Developer');
        $this->position($globex, 'Manager');
        $this->em->flush();

        self::assertSame(['Developer'], $this->titles($this->positions->getAllPositionsByCompany($user, $acme)));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testAnotherUserGetsNothingForMyCompany(): void
    {
        $me = $this->persist(new User('me@example.com'));
        $other = $this->persist(new User('other@example.com'));
        $acme = $this->persist(new CompanyEntity($me, 'Acme'));
        $this->position($acme, 'Developer');
        $this->em->flush();

        self::assertSame([], $this->positions->getAllPositionsByCompany($other, $acme));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testArchivedPositionsAreExcludedByDefault(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $acme = $this->persist(new CompanyEntity($user, 'Acme'));
        $this->position($acme, 'Developer', '2019-03-01');
        $this->position($acme, 'Archived', '2020-01-01')->archive(new DateTimeImmutable());
        $this->em->flush();

        self::assertSame(['Developer'], $this->titles($this->positions->getAllPositionsByCompany($user, $acme)));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testIncludeArchivedReturnsEverything(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $acme = $this->persist(new CompanyEntity($user, 'Acme'));
        $this->position($acme, 'Developer', '2019-03-01');
        $this->position($acme, 'Archived', '2020-01-01')->archive(new DateTimeImmutable());
        $this->em->flush();

        self::assertSame(
            ['Archived', 'Developer'],
            $this->titles($this->positions->getAllPositionsByCompany($user, $acme, includeArchived: true)),
        );
    }

    public function testCompanyWithoutPositionsGetsEmptyList(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $acme = $this->persist(new CompanyEntity($user, 'Acme'));
        $this->em->flush();

        self::assertSame([], $this->positions->getAllPositionsByCompany($user, $acme));
    }

    /**
     * @throws DateMalformedStringException
     */
    private function position(CompanyEntity $company, string $title, string $start = '2024-01-15'): PositionEntity
    {
        return $this->persist($company->addPosition($title, new DateTimeImmutable($start)));
    }

    /**
     * @param list<PositionEntity> $positions
     *
     * @return list<string>
     */
    private function titles(array $positions): array
    {
        return array_map(static fn (PositionEntity $p) => $p->title->value, $positions);
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    private function persist(object $entity): object
    {
        $this->em->persist($entity);

        return $entity;
    }
}
