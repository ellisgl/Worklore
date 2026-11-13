<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Company\CompanyEntity;
use App\Company\ProductEntity;
use App\Company\ProjectEntity;
use App\Identity\User;
use App\Journal\JournalEntryEntity;
use App\Journal\JournalEntryRepository;
use App\Tests\Functional\FindsEntities;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The list methods: ordering, scoping to the user and parent, and archived
 * parents hiding their children.
 */
final class ListMethodsTest extends KernelTestCase
{
    use FindsEntities;

    private EntityManagerInterface $em;
    private User $user;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->user = $this->persist(new User('me@example.com'));
    }

    // ---- journal entries --------------------------------------------------

    public function testJournalEntriesNewestFirst(): void
    {
        $project = $this->project();
        $this->persist(new JournalEntryEntity($project, $this->user, 'First', 'Body', new DateTimeImmutable('2024-01-01 09:00')));
        $this->persist(new JournalEntryEntity($project, $this->user, 'Third', 'Body', new DateTimeImmutable('2024-03-01 09:00')));
        $this->persist(new JournalEntryEntity($project, $this->user, 'Second', 'Body', new DateTimeImmutable('2024-02-01 09:00')));
        $this->em->flush();

        $titles = array_map(static fn (JournalEntryEntity $j) => $j->title->value, $this->repo(JournalEntryRepository::class)->getAllEntriesByProject($this->user, $project));

        self::assertSame(['Third', 'Second', 'First'], $titles);
    }

    public function testArchivedAncestorHidesJournalEntries(): void
    {
        $project = $this->project();
        $this->persist(new JournalEntryEntity($project, $this->user, 'Kickoff', 'Body'));
        $this->em->flush();
        $repo = $this->repo(JournalEntryRepository::class);

        foreach (['project' => $project, 'product' => $project->product, 'company' => $project->product->company] as $level => $ancestor) {
            $ancestor->archive(new DateTimeImmutable());
            $this->em->flush();
            self::assertSame([], $repo->getAllEntriesByProject($this->user, $project), "Archived $level");
            self::assertCount(1, $repo->getAllEntriesByProject($this->user, $project, includeArchived: true), "Archived $level, includeArchived");
            $ancestor->unarchive();
            $this->em->flush();
        }

        self::assertCount(1, $repo->getAllEntriesByProject($this->user, $project), 'Nothing archived');
    }

    public function testJournalEntryFindOneByIdForOwner(): void
    {
        $entry = $this->persist(new JournalEntryEntity($this->project(), $this->user, 'Kickoff', 'Body'));
        $this->em->flush();
        $repo = $this->repo(JournalEntryRepository::class);

        self::assertSame($entry, $repo->findOneByIdForOwner($entry->id, $this->user));
        self::assertNull($repo->findOneByIdForOwner($entry->id, $this->persist(new User('other@example.com'))));
    }

    // ---- helpers ----------------------------------------------------------

    private function product(): ProductEntity
    {
        return $this->persist($this->persist(new CompanyEntity($this->user, 'Acme'))->addProduct('Widget'));
    }

    private function project(): ProjectEntity
    {
        return $this->persist($this->product()->addProject('Launch'));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function repo(string $class): object
    {
        $service = self::getContainer()->get($class);
        self::assertInstanceOf($class, $service);

        return $service;
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
