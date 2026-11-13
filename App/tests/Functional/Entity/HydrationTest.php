<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Company\CompanyEntity;
use App\Company\PositionEntity;
use App\Company\ProductEntity;
use App\Company\ProjectEntity;
use App\Identity\User;
use App\Journal\JournalEntryEntity;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\TagEntity;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Guards the readonly properties on entities. Doctrine refuses to write a
 * readonly property twice with a different object (ReadonlyAccessor uses
 * !==), which happens when a lazy reference is loaded or an entity is
 * refreshed. Object-valued ids (CompanyId, ...) can't be readonly for that reason.
 */
final class HydrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    /** @var array<string, object> */
    private array $graph;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();

        $user = new User('a@example.com');
        $company = new CompanyEntity($user, 'Acme');
        $product = $company->addProduct('Widget');
        $category = new CategoryEntity($user, 'Area');
        $project = $product->addProject('Launch');
        $this->graph = [
            'company' => $company,
            'product' => $product,
            'project' => $project,
            'journal' => new JournalEntryEntity($project, $user, 'Kickoff', 'Agreed on the scope.'),
            'position' => $company->addPosition('Developer', new \DateTimeImmutable('2024-01-15')),
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
        foreach (['company', 'product', 'project', 'journal', 'position', 'category', 'tag'] as $key) {
            yield $key => [$key];
        }
    }

    #[DataProvider('entityProvider')]
    public function testLazyReferenceCanBeLoaded(string $key): void
    {
        $original = $this->graph[$key];

        $reference = $this->em->getReference($original::class, $original->id);
        // Reading a non-id property initialises the lazy object.
        self::assertSame($original->createdAt->format('Y-m-d H:i:s'), $reference->createdAt->format('Y-m-d H:i:s'));
    }

    #[DataProvider('entityProvider')]
    public function testEntityCanBeRefreshed(string $key): void
    {
        $original = $this->graph[$key];
        $entity = $this->em->find($original::class, $original->id);

        $this->em->refresh($entity);

        self::assertSame($original->createdAt->format('Y-m-d H:i:s'), $entity->createdAt->format('Y-m-d H:i:s'));
    }

    public function testRelationsLoadLazilyAcrossTheGraph(): void
    {
        $project = $this->em->find(ProjectEntity::class, $this->graph['project']->id);

        self::assertSame('Acme', $project->product->company->name->value);
        self::assertSame('a@example.com', $project->product->company->user->email);
    }
}
