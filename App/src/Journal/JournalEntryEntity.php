<?php

declare(strict_types=1);

namespace App\Journal;

use App\Company\ProjectEntity;
use App\Shared\OwnershipMismatchException;
use App\Identity\User;
use App\Taxonomy\TagEntity;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use function Symfony\Component\Clock\now;
use Symfony\Component\Uid\Uuid;

/**
 * Doctrine mapping: src/Journal/Config/JournalEntryEntity.orm.xml.
 */
final class JournalEntryEntity
{
    public const int MAX_TITLE_LENGTH = 255;

    private(set) Uuid $id;
    private(set) string $title;
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
        private(set) readonly User $user,
        string $title,
        string $entry,
        ?DateTimeImmutable $createdAt = null,
    ) {
        OwnershipMismatchException::assertSameUser($project->user, $user, 'project');

        $this->id = Uuid::v7();
        $this->createdAt = $createdAt ?? now()->setTimezone(new DateTimeZone('UTC'));
        $this->title = self::normalizeTitle($title);
        $this->entry = self::normalizeEntry($entry);
        $this->tags = new ArrayCollection();
        $project->addJournalEntry($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
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
        OwnershipMismatchException::assertSameUser($tag->user, $this->user, 'tag');

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
        $this->title = self::normalizeTitle($title);

        return $this;
    }

    public function unarchive(): self
    {
        $this->archivedAt = null;

        return $this;
    }

    private static function normalizeTitle(string $title): string
    {
        $title = trim($title);
        if ('' === $title) {
            throw new InvalidArgumentException('Journal entry title cannot be blank');
        }
        if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            throw new InvalidArgumentException(\sprintf('Journal entry title cannot be longer than %d characters', self::MAX_TITLE_LENGTH));
        }

        return $title;
    }

    /**
     * Entries are required: blank or whitespace-only text is rejected.
     */
    private static function normalizeEntry(string $entry): string
    {
        $entry = trim($entry);
        if ('' === $entry) {
            throw new InvalidArgumentException('Journal entry cannot be blank');
        }

        return $entry;
    }
}
