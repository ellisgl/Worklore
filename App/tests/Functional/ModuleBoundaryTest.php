<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Company\CompanyEntity;
use App\Identity\User;
use App\Shared\Owner;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The domain modules depend on App\Shared\Owner, never on App\Identity (the
 * security user, its passwords, roles and tokens).
 */
final class ModuleBoundaryTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function moduleProvider(): iterable
    {
        foreach (['Company', 'Taxonomy', 'Journal', 'Shared'] as $module) {
            yield $module => [$module];
        }
    }

    #[DataProvider('moduleProvider')]
    public function testModuleDoesNotDependOnIdentity(string $module): void
    {
        $offenders = [];
        $dir = \dirname(__DIR__, 2).'/src/'.$module;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            $path = $file->getPathname();
            if (str_ends_with($path, '.php')) {
                // Code only: comments may explain the relationship.
                foreach (token_get_all((string) file_get_contents($path)) as $token) {
                    if (\is_array($token) && \in_array($token[0], [\T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED, \T_STRING], true)
                        && preg_match('/^\\\\?App\\\\Identity\b/', $token[1])) {
                        $offenders[] = basename($path).': '.$token[1];
                    }
                }
            } elseif (str_ends_with($path, '.xml') && str_contains((string) file_get_contents($path), 'App\\Identity')) {
                $offenders[] = basename($path).': references App\\Identity';
            }
        }

        self::assertSame([], $offenders, "src/$module must use App\\Shared\\Owner, not App\\Identity");
    }

    public function testOwnerRelationsLoadTheUser(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);
        $user = new User('a@example.com');
        $company = new CompanyEntity($user, 'Acme');
        $em->persist($user);
        $em->persist($company);
        $em->flush();
        $em->clear();

        $owner = $em->find(CompanyEntity::class, $company->id)->user;

        self::assertInstanceOf(Owner::class, $owner);
        self::assertInstanceOf(User::class, $owner, 'Doctrine resolves Owner to User');
        self::assertTrue($user->id->equals($owner->ownerId()));
    }
}
