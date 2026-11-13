<?php

declare(strict_types=1);

namespace App\Journal;

use App\Company\ProjectEntity;
use App\Shared\InvalidValueException;
use App\Shared\Name;
use App\Shared\Owner;
use App\Shared\OwnershipMismatchException;
use App\Taxonomy\TagEntity;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Clock\Clock;

use function in_array;

/**
 * Doctrine mapping: src/Journal/Config/JournalEntryEntity.orm.xml.
 */
final class JournalEntryEntity
{
    private(set) JournalEntryId $id;
    private(set) Name $title;
    private(set) string $entry;
    private(set) ?DateTimeImmutable $archivedAt = null;
    /** @var Collection<int, TagEntity> */
    private Collection $tags;
    private(set) DateTimeImmutable $createdAt;

    /**
     * @throws OwnershipMismatchException if the project belongs to another user
     */
    public function __construct(
        private(set) readonly ProjectEntity $project,
        private(set) readonly Owner $owner,
        string $title,
        string $entry,
        ?DateTimeImmutable $createdAt = null,
        ?JournalEntryId $id = null,
    ) {
        OwnershipMismatchException::assertSameOwner($project->owner, $owner, 'project');

        $this->id = $id ?? JournalEntryId::generate();
        $this->createdAt = $createdAt ?? Clock::get()->now()->setTimezone(new DateTimeZone('UTC'));
        $this->title = Name::of($title, 'Journal entry title');
        $this->entry = self::normalizeEntry($entry);
        $this->tags = new ArrayCollection();
        $project->addJournalEntry($this);
    }

    /**
     * @return ReadableCollection<int, TagEntity>
     */
    public function getTags(): ReadableCollection
    {
        return $this->tags;
    }

    public function hasTag(TagEntity $tag): bool
    {
        return $this->tags->contains($tag);
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    /**
     * @throws OwnershipMismatchException if the tag belongs to another user
     */
    public function addTag(TagEntity $tag): self
    {
        OwnershipMismatchException::assertSameOwner($tag->owner, $this->owner, 'tag');

        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function archive(DateTimeImmutable $at): self
    {
        $this->archivedAt ??= $at;  // already archived: keep the original time

        return $this;
    }

    /**
     * Replaces the entry's tags with exactly these (duplicates ignored). All
     * of them are checked before anything changes.
     *
     * @throws OwnershipMismatchException if a tag belongs to another user
     */
    public function setTags(TagEntity ...$tags): self
    {
        foreach ($tags as $tag) {
            OwnershipMismatchException::assertSameOwner($tag->owner, $this->owner, 'tag');
        }

        foreach ($this->tags->toArray() as $current) {
            if (!in_array($current, $tags, true)) {
                $this->tags->removeElement($current);
            }
        }
        foreach ($tags as $tag) {
            $this->addTag($tag);
        }

        return $this;
    }

    public function removeTag(TagEntity $tag): self
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    public function setEntry(string $entry): self
    {
        $this->entry = self::normalizeEntry($entry);

        return $this;
    }

    public function setTitle(string $title): self
    {
        $this->title = Name::of($title, 'Journal entry title');

        return $this;
    }

    public function unarchive(): self
    {
        $this->archivedAt = null;

        return $this;
    }

    /**
     * Entries are required: blank or whitespace-only text is rejected.
     */
    private static function normalizeEntry(string $entry): string
    {
        $entry = trim($entry);
        if ('' === $entry) {
            throw new InvalidValueException('Journal entry cannot be blank');
        }

        return $entry;
    }
}
