<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Company\CompanyEntity;
use App\Company\ProjectEntity;
use App\Identity\User;
use App\Shared\OwnershipMismatchException;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\TagEntity;
use App\Tests\Functional\FindsEntities;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;
use function array_values;

final class ProjectTagsTest extends KernelTestCase
{
    use FindsEntities;

    public function testSetTagsReplacesTheTags(): void
    {
        $user = new User('a@example.com');
        $area = new CategoryEntity($user, 'Area');
        [$backend, $frontend, $api] = [$area->addTag('backend'), $area->addTag('frontend'), $area->addTag('api')];
        $project = $this->project($user)->addTag($backend)->addTag($frontend);

        $project->setTags($frontend, $api, $api);
        self::assertSame(['frontend', 'api'], self::tagNames($project));

        $project->setTags();
        self::assertSame([], self::tagNames($project));
    }

    public function testSetTagsChecksEveryTagBeforeChangingAnything(): void
    {
        $user = new User('a@example.com');
        $mine = new CategoryEntity($user, 'Area')->addTag('backend');
        $theirs = new CategoryEntity(new User('b@example.com'), 'Area')->addTag('backend');
        $project = $this->project($user)->addTag($mine);

        try {
            $project->setTags($mine, $theirs);
            self::fail('Expected OwnershipMismatchException');
        } catch (OwnershipMismatchException) {
        }

        self::assertSame(['backend'], self::tagNames($project), 'Unchanged');
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testTagsAreSavedWithTheProject(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = new User('a@example.com');
        $area = new CategoryEntity($user, 'Area');
        $tag = $area->addTag('backend');
        $project = $this->project($user)->setTags($tag);
        foreach ([$user, $area, $project->product->company] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $em->clear();

        self::assertSame(['backend'], self::tagNames(self::findOrFail($em, ProjectEntity::class, $project->id)));
    }

    private function project(User $user): ProjectEntity
    {
        return new CompanyEntity($user, 'Acme')->addProduct('Widget')->addProject('Launch');
    }

    /**
     * @return list<string>
     */
    private static function tagNames(ProjectEntity $project): array
    {
        return array_values(array_map(static fn (TagEntity $t) => $t->name->value, $project->getTags()->toArray()));
    }
}
