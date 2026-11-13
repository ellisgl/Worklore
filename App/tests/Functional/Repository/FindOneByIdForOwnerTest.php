<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Company\CompanyEntity;
use App\Company\CompanyRepository;
use App\Company\PositionEntity;
use App\Company\ProductEntity;
use App\Company\ProjectEntity;
use App\Identity\User;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\CategoryRepository;
use App\Taxonomy\TagEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Every by-id lookup (findOneByIdForOwner(), findProductByIdForOwner(), ...): a user sees their own records and
 * nothing else.
 */
final class FindOneByIdForOwnerTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
    }

    /**
     * @return iterable<string, array{class-string, string, string}>
     */
    public static function repositoryProvider(): iterable
    {
        yield 'company' => [CompanyRepository::class, 'findOneByIdForOwner', 'company'];
        yield 'product' => [CompanyRepository::class, 'findProductByIdForOwner', 'product'];
        yield 'project' => [CompanyRepository::class, 'findProjectByIdForOwner', 'project'];
        yield 'position' => [CompanyRepository::class, 'findPositionByIdForOwner', 'position'];
        yield 'category' => [CategoryRepository::class, 'findOneByIdForOwner', 'category'];
        yield 'tag' => [CategoryRepository::class, 'findTagByIdForOwner', 'tag'];
    }

    #[DataProvider('repositoryProvider')]
    public function testOwnerGetsTheRecord(string $repository, string $method, string $key): void
    {
        $mine = $this->graphFor('me@example.com');
        $this->em->flush();
        $this->em->clear();

        $found = $this->repo($repository)->{$method}($mine[$key]->id, $this->reload($mine['user']));

        self::assertNotNull($found);
        self::assertTrue($mine[$key]->id->equals($found->id));
    }

    #[DataProvider('repositoryProvider')]
    public function testOtherUserGetsNull(string $repository, string $method, string $key): void
    {
        $mine = $this->graphFor('me@example.com');
        $theirs = $this->graphFor('other@example.com');
        $this->em->flush();

        self::assertNull($this->repo($repository)->{$method}($mine[$key]->id, $theirs['user']));
    }

    #[DataProvider('repositoryProvider')]
    public function testUnknownIdGetsNull(string $repository, string $method, string $key): void
    {
        $mine = $this->graphFor('me@example.com');
        $this->em->flush();

        self::assertNull($this->repo($repository)->{$method}($mine[$key]->id::generate(), $mine['user']));
    }

    #[DataProvider('repositoryProvider')]
    public function testArchivedRecordIsStillFound(string $repository, string $method, string $key): void
    {
        $mine = $this->graphFor('me@example.com');
        $mine[$key]->archive(new DateTimeImmutable());
        $this->em->flush();

        self::assertNotNull($this->repo($repository)->{$method}($mine[$key]->id, $mine['user']));
    }

    /**
     * One of every entity, all owned by a new user.
     *
     * @return array{user: User, company: CompanyEntity, product: ProductEntity, project: ProjectEntity, position: PositionEntity, category: CategoryEntity, tag: TagEntity}
     */
    private function graphFor(string $email): array
    {
        $user = new User($email);
        $company = new CompanyEntity($user, 'Acme');
        $product = $company->addProduct('Widget');
        $category = new CategoryEntity($user, 'Area');
        $graph = [
            'user' => $user,
            'company' => $company,
            'product' => $product,
            'project' => $product->addProject('Launch'),
            'position' => $company->addPosition('Developer', new DateTimeImmutable('2024-01-15')),
            'category' => $category,
            'tag' => $category->addTag('backend'),
        ];
        foreach ($graph as $entity) {
            $this->em->persist($entity);
        }

        return $graph;
    }

    private function repo(string $class): object
    {
        return self::getContainer()->get($class);
    }

    private function reload(User $user): User
    {
        return $this->em->find(User::class, $user->id);
    }
}
