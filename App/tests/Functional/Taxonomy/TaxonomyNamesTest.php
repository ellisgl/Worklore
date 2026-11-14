<?php

declare(strict_types=1);

namespace App\Tests\Functional\Taxonomy;

use App\Identity\User;
use App\Taxonomy\Query\TaxonomyNames;
use App\Tests\Functional\CreatesRecords;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The name checks commands run before recording a category or tag name.
 */
final class TaxonomyNamesTest extends KernelTestCase
{
    use CreatesRecords;

    private TaxonomyNames $names;
    private User $me;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->names = self::getContainer()->get(TaxonomyNames::class);
        $this->me = $this->owner();
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testCategoryNames(): void
    {
        $skill = $this->category($this->me, 'Skill');
        $this->category($this->me, 'Old', archived: true);

        self::assertTrue($this->names->categoryNameTaken($this->me->id, ' SKILL '), 'Trimmed, any case');
        self::assertTrue($this->names->categoryNameTaken($this->me->id, 'old'), 'Archived ones hold their name');
        self::assertFalse($this->names->categoryNameTaken($this->owner('other@example.com')->id, 'Skill'), 'Free for another user');
        self::assertFalse($this->names->categoryNameTaken($this->me->id, 'skill', except: $skill->id()), 'Its own name');
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testTagNames(): void
    {
        [$area, $skill] = [$this->category($this->me, 'Area'), $this->category($this->me, 'Skill')];
        $backend = $this->tag($area, 'backend');
        $this->tag($area, 'legacy', archived: true);

        self::assertTrue($this->names->tagNameTaken($area->id(), ' BACKEND '));
        self::assertTrue($this->names->tagNameTaken($area->id(), 'legacy'));
        self::assertFalse($this->names->tagNameTaken($skill->id(), 'backend'), 'Free in another category');
        self::assertFalse($this->names->tagNameTaken($area->id(), 'backend', except: $backend->id()));
        self::assertTrue($this->names->tagNameTaken($area->id(), 'legacy', except: $backend->id()));
    }
}
