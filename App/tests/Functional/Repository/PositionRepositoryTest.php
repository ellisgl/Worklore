<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Company\CompanyEntity;
use App\Company\PositionEntity;
use App\Company\PositionRepository;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PositionRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PositionRepository $positions;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->positions = static::getContainer()->get(PositionRepository::class);
    }

    public function testReturnsPositionsOldestFirst(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $acme = $this->persist(new CompanyEntity($user, 'Acme'));
        // Inserted out of order on purpose
        $this->position($acme, $user, 'Senior Developer', '2022-06-01');
        $this->position($acme, $user, 'Intern', '2018-06-01');
        $this->position($acme, $user, 'Developer', '2019-03-01');
        $this->em->flush();
        $this->em->clear();

        $result = $this->positions->getAllPositionsByCompany(
            $this->em->find(User::class, $user->getId()),
            $this->em->find(CompanyEntity::class, $acme->getId()),
        );

        self::assertSame(['Senior Developer', 'Developer', 'Intern'], $this->titles($result));
    }

    public function testExcludesPositionsAtOtherCompanies(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $acme = $this->persist(new CompanyEntity($user, 'Acme'));
        $globex = $this->persist(new CompanyEntity($user, 'Globex'));
        $this->position($acme, $user, 'Developer');
        $this->position($globex, $user, 'Manager');
        $this->em->flush();

        self::assertSame(['Developer'], $this->titles($this->positions->getAllPositionsByCompany($user, $acme)));
    }

    public function testAnotherUserGetsNothingForMyCompany(): void
    {
        $me = $this->persist(new User('me@example.com'));
        $other = $this->persist(new User('other@example.com'));
        $acme = $this->persist(new CompanyEntity($me, 'Acme'));
        $this->position($acme, $me, 'Developer');
        $this->em->flush();

        self::assertSame([], $this->positions->getAllPositionsByCompany($other, $acme));
    }

    public function testArchivedPositionsAreExcludedByDefault(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $acme = $this->persist(new CompanyEntity($user, 'Acme'));
        $this->position($acme, $user, 'Developer', '2019-03-01');
        $this->position($acme, $user, 'Archived', '2020-01-01')->archive(new \DateTimeImmutable());
        $this->em->flush();

        self::assertSame(['Developer'], $this->titles($this->positions->getAllPositionsByCompany($user, $acme)));
    }

    public function testIncludeArchivedReturnsEverything(): void
    {
        $user = $this->persist(new User('a@example.com'));
        $acme = $this->persist(new CompanyEntity($user, 'Acme'));
        $this->position($acme, $user, 'Developer', '2019-03-01');
        $this->position($acme, $user, 'Archived', '2020-01-01')->archive(new \DateTimeImmutable());
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

    private function position(CompanyEntity $company, User $user, string $title, string $start = '2024-01-15'): PositionEntity
    {
        return $this->persist(new PositionEntity($company, $user, $title, new \DateTimeImmutable($start)));
    }

    /**
     * @param list<PositionEntity> $positions
     *
     * @return list<string>
     */
    private function titles(array $positions): array
    {
        return array_map(static fn (PositionEntity $p) => $p->title, $positions);
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
