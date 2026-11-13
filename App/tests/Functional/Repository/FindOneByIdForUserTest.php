<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Company\CompanyEntity;
use App\Company\CompanyRepository;
use App\Company\PositionEntity;
use App\Company\PositionRepository;
use App\Company\ProductEntity;
use App\Company\ProductRepository;
use App\Company\ProjectEntity;
use App\Company\ProjectRepository;
use App\Identity\User;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\CategoryRepository;
use App\Taxonomy\TagEntity;
use App\Taxonomy\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Every repository's findOneByIdForUser(): a user sees their own records and
 * nothing else.
 */
final class FindOneByIdForUserTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function repositoryProvider(): iterable
    {
        yield 'company' => [CompanyRepository::class, 'company'];
        yield 'product' => [ProductRepository::class, 'product'];
        yield 'project' => [ProjectRepository::class, 'project'];
        yield 'position' => [PositionRepository::class, 'position'];
        yield 'category' => [CategoryRepository::class, 'category'];
        yield 'tag' => [TagRepository::class, 'tag'];
    }

    #[DataProvider('repositoryProvider')]
    public function testOwnerGetsTheRecord(string $repository, string $key): void
    {
        $mine = $this->graphFor('me@example.com');
        $this->em->flush();
        $this->em->clear();

        $found = $this->repo($repository)->findOneByIdForUser($mine[$key]->id, $this->reload($mine['user']));

        self::assertNotNull($found);
        self::assertTrue($mine[$key]->id->equals($found->id));
    }

    #[DataProvider('repositoryProvider')]
    public function testOtherUserGetsNull(string $repository, string $key): void
    {
        $mine = $this->graphFor('me@example.com');
        $theirs = $this->graphFor('other@example.com');
        $this->em->flush();

        self::assertNull($this->repo($repository)->findOneByIdForUser($mine[$key]->id, $theirs['user']));
    }

    #[DataProvider('repositoryProvider')]
    public function testUnknownIdGetsNull(string $repository, string $key): void
    {
        $mine = $this->graphFor('me@example.com');
        $this->em->flush();

        self::assertNull($this->repo($repository)->findOneByIdForUser($mine[$key]->id::generate(), $mine['user']));
    }

    #[DataProvider('repositoryProvider')]
    public function testArchivedRecordIsStillFound(string $repository, string $key): void
    {
        $mine = $this->graphFor('me@example.com');
        $mine[$key]->archive(new \DateTimeImmutable());
        $this->em->flush();

        self::assertNotNull($this->repo($repository)->findOneByIdForUser($mine[$key]->id, $mine['user']));
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
        $product = new ProductEntity($company, $user, 'Widget');
        $category = new CategoryEntity($user, 'Area');
        $graph = [
            'user' => $user,
            'company' => $company,
            'product' => $product,
            'project' => new ProjectEntity($product, $user, 'Launch'),
            'position' => new PositionEntity($company, $user, 'Developer', new \DateTimeImmutable('2024-01-15')),
            'category' => $category,
            'tag' => new TagEntity($category, $user, 'backend'),
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
