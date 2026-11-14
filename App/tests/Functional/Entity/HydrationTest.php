<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Identity\User;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\TagEntity;
use App\Tests\Functional\FindsEntities;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Guards the readonly properties on entities. Doctrine refuses to write a
 * readonly property twice with a different object (ReadonlyAccessor uses
 * !==), which happens when a lazy reference is loaded or an entity is
 * refreshed. Object-valued ids (CategoryId, ...) can't be readonly for that reason.
 */
final class HydrationTest extends KernelTestCase
{
    use FindsEntities;

    private EntityManagerInterface $em;

    /** @var array<string, CategoryEntity|TagEntity> */
    private array $graph;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $user = new User('a@example.com');
        $category = new CategoryEntity($user, 'Area');
        $this->graph = [
            'category' => $category,
            'tag' => $category->addTag('backend'),
        ];

        $this->em->persist($user);
        foreach ($this->graph as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        $this->em->clear();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function entityProvider(): iterable
    {
        foreach (['category', 'tag'] as $key) {
            yield $key => [$key];
        }
    }

    /**
     * @throws ORMException
     */
    #[DataProvider('entityProvider')]
    public function testLazyReferenceCanBeLoaded(string $key): void
    {
        $original = $this->graph[$key];

        $reference = $this->em->getReference($original::class, $original->id);
        self::assertNotNull($reference);
        // Reading a non-id property initialises the lazy object.
        self::assertSame($original->createdAt->format('Y-m-d H:i:s'), $reference->createdAt->format('Y-m-d H:i:s'));
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[DataProvider('entityProvider')]
    public function testEntityCanBeRefreshed(string $key): void
    {
        $original = $this->graph[$key];
        $entity = self::findOrFail($this->em, $original::class, $original->id);

        $this->em->refresh($entity);

        self::assertSame($original->createdAt->format('Y-m-d H:i:s'), $entity->createdAt->format('Y-m-d H:i:s'));
    }
}
