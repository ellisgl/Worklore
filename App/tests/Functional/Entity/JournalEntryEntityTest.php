<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Company\CompanyEntity;
use App\Company\ProjectEntity;
use App\Identity\User;
use App\Journal\JournalEntryEntity;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\TagEntity;
use App\Tests\Functional\FindsEntities;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class JournalEntryEntityTest extends KernelTestCase
{
    use FindsEntities;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testEntryRoundTripsWithProjectAndTags(): void
    {
        $user = new User('a@example.com');
        $project = $this->project($user);
        $tag = new CategoryEntity($user, 'Area')->addTag('backend');
        $entry = new JournalEntryEntity($project, $user, '  Kickoff  ', '  Agreed on the scope.  ')->addTag($tag);

        self::assertCount(1, $project->getJournalEntries(), 'Constructor adds the entry to the project');

        $this->persistAll($user, $project->product->company, $project->product, $project, $tag->category, $tag, $entry);
        $this->em->clear();

        $project = self::findOrFail($this->em, ProjectEntity::class, $project->id);
        self::assertCount(1, $project->getJournalEntries(), 'The project loads its entries from the database');

        $loaded = $project->getJournalEntries()->first();
        self::assertInstanceOf(JournalEntryEntity::class, $loaded);
        self::assertSame('Kickoff', $loaded->title->value, 'Title is trimmed');
        self::assertSame('Agreed on the scope.', $loaded->entry, 'Entry is trimmed');
        self::assertInstanceOf(User::class, $loaded->owner);
        self::assertSame('a@example.com', $loaded->owner->email);
        self::assertSame(['backend'], array_map(static fn (TagEntity $t) => $t->name->value, $loaded->getTags()->toArray()));
    }

    public function testBlankTitleIsRejected(): void
    {
        $user = new User('a@example.com');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Journal entry title cannot be blank');
        new JournalEntryEntity($this->project($user), $user, '   ', 'Body');
    }

    public function testBlankEntryIsRejected(): void
    {
        $user = new User('a@example.com');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Journal entry cannot be blank');
        new JournalEntryEntity($this->project($user), $user, 'Kickoff', '   ');
    }

    public function testSetEntryRejectsBlankAndKeepsOldText(): void
    {
        $user = new User('a@example.com');
        $entry = new JournalEntryEntity($this->project($user), $user, 'Kickoff', 'Original');

        try {
            $entry->setEntry('  ');
            self::fail('Blank entry should be rejected');
        } catch (InvalidArgumentException) {
        }

        self::assertSame('Original', $entry->entry);
    }

    public function testDeletingProjectDeletesItsEntries(): void
    {
        $user = new User('a@example.com');
        $project = $this->project($user);
        $entry = new JournalEntryEntity($project, $user, 'Kickoff', 'Body');
        $this->persistAll($user, $project->product->company, $project->product, $project, $entry);

        $this->em->remove($project);
        $this->em->flush();
        $this->em->clear();

        self::assertSame(0, $this->em->getRepository(JournalEntryEntity::class)->count());
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testDeletingTagRemovesItFromEntries(): void
    {
        $user = new User('a@example.com');
        $project = $this->project($user);
        $tag = new CategoryEntity($user, 'Area')->addTag('backend');
        $entry = new JournalEntryEntity($project, $user, 'Kickoff', 'Body')->addTag($tag);
        $this->persistAll($user, $project->product->company, $project->product, $project, $tag->category, $tag, $entry);

        $this->em->remove($tag);
        $this->em->flush();
        $this->em->clear();

        $entry = self::findOrFail($this->em, JournalEntryEntity::class, $entry->id); // the entry itself survives
        self::assertCount(0, $entry->getTags());
    }

    private function project(User $user): ProjectEntity
    {
        return new CompanyEntity($user, 'Acme')->addProduct('Widget')->addProject('Launch');
    }

    private function persistAll(object ...$entities): void
    {
        foreach ($entities as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
    }
}
