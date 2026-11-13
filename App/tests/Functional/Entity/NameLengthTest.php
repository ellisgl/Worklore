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
 * Names and titles are VARCHAR(255). The entities reject longer text with a
 * clear message instead of letting PostgreSQL fail the insert.
 */
final class NameLengthTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
    }

    /**
     * Each closure builds the record with the given name/title and returns
     * every entity that needs persisting, the named record last.
     *
     * @return iterable<string, array{\Closure(User $u, string $name): list<object>}>
     */
    public static function namedProvider(): iterable
    {
        yield 'company' => [static fn (User $u, string $n) => [new CompanyEntity($u, $n)]];
        yield 'product' => [static function (User $u, string $n) {
            $c = new CompanyEntity($u, 'Acme');

            return [$c, $c->addProduct($n)];
        }];
        yield 'project' => [static function (User $u, string $n) {
            $c = new CompanyEntity($u, 'Acme');
            $p = $c->addProduct('Widget');

            return [$c, $p, $p->addProject($n)];
        }];
        yield 'position title' => [static function (User $u, string $n) {
            $c = new CompanyEntity($u, 'Acme');

            return [$c, $c->addPosition($n, new \DateTimeImmutable('2024-01-01'))];
        }];
        yield 'journal entry title' => [static function (User $u, string $n) {
            $c = new CompanyEntity($u, 'Acme');
            $p = $c->addProduct('Widget');
            $pj = $p->addProject('Launch');

            return [$c, $p, $pj, new JournalEntryEntity($pj, $u, $n, 'Body')];
        }];
        yield 'category' => [static fn (User $u, string $n) => [new CategoryEntity($u, $n)]];
        yield 'tag' => [static function (User $u, string $n) {
            $c = new CategoryEntity($u, 'Area');

            return [$c, $c->addTag($n)];
        }];
    }

    #[DataProvider('namedProvider')]
    public function testMaxLengthIsAcceptedAndSaved(\Closure $build): void
    {
        // Multibyte on purpose: the limit counts characters, like VARCHAR(255).
        $name = str_repeat('é', 255);
        $user = new User('a@example.com');

        $entities = $build($user, $name);
        $this->em->persist($user);
        foreach ($entities as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();

        $record = end($entities);
        $this->em->clear();
        $loaded = $this->em->find($record::class, $record->id);
        self::assertSame($name, ($loaded->name ?? $loaded->title)->value);
    }

    #[DataProvider('namedProvider')]
    public function testOneOverMaxLengthIsRejected(\Closure $build): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot be longer than 255 characters/');
        $build(new User('a@example.com'), str_repeat('x', 256));
    }

    public function testSurroundingWhitespaceDoesNotCountTowardsTheLimit(): void
    {
        $company = new CompanyEntity(new User('a@example.com'), '  '.str_repeat('x', 255).'  ');

        self::assertSame(255, mb_strlen($company->name->value));
    }

    public function testRenameOverMaxLengthIsRejectedAndKeepsOldName(): void
    {
        $company = new CompanyEntity(new User('a@example.com'), 'Acme');

        try {
            $company->setName(str_repeat('x', 256));
            self::fail('Expected the rename to be rejected');
        } catch (\InvalidArgumentException) {
        }

        self::assertSame('Acme', $company->name->value);
    }

    public function testReasonLeftOverMaxLengthIsRejected(): void
    {
        $user = new User('a@example.com');
        $position = (new CompanyEntity($user, 'Acme'))->addPosition('Dev', new \DateTimeImmutable('2024-01-01'));

        $this->expectException(\InvalidArgumentException::class);
        $position->end(new \DateTimeImmutable('2024-06-30'), str_repeat('x', 256));
    }

    public function testRejectedReasonLeftDoesNotEndThePosition(): void
    {
        $user = new User('a@example.com');
        $position = (new CompanyEntity($user, 'Acme'))->addPosition('Dev', new \DateTimeImmutable('2024-01-01'));

        try {
            $position->end(new \DateTimeImmutable('2024-06-30'), str_repeat('x', 256));
        } catch (\InvalidArgumentException) {
        }

        self::assertTrue($position->isCurrent());
    }
}
