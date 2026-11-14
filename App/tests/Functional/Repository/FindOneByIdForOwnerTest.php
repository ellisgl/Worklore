<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Identity\User;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\CategoryRepository;
use App\Taxonomy\TagEntity;
use App\Tests\Functional\FindsEntities;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The ORM repositories' by-id lookups (findOneByIdForOwner(), findTagByIdForOwner()):
 * a user sees their own records and nothing else.
 */
final class FindOneByIdForOwnerTest extends KernelTestCase
{
    use FindsEntities;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @return iterable<string, array{class-string, string, string}>
     */
    public static function repositoryProvider(): iterable
    {
        yield 'category' => [CategoryRepository::class, 'findOneByIdForOwner', 'category'];
        yield 'tag' => [CategoryRepository::class, 'findTagByIdForOwner', 'tag'];
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[DataProvider('repositoryProvider')]
    public function testOwnerGetsTheRecord(string $repository, string $method, string $key): void
    {
        $mine = $this->graphFor('me@example.com');
        $this->em->flush();
        $this->em->clear();

        $found = $this->repo($repository)->{$method}($mine[$key]->id, $this->reload($mine['user']));

        // Repository methods are looked up by name here, so the result is untyped.
        self::assertInstanceOf($mine[$key]::class, $found);
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
     * @return array{user: User, category: CategoryEntity, tag: TagEntity}
     */
    private function graphFor(string $email): array
    {
        $user = new User($email);
        $category = new CategoryEntity($user, 'Area');
        $graph = [
            'user' => $user,
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

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    private function reload(User $user): User
    {
        return self::findOrFail($this->em, User::class, $user->id);
    }
}
