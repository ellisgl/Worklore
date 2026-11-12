<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

final class RoleHierarchyTest extends KernelTestCase
{
    public function testAdminInheritsUserRole(): void
    {
        self::bootKernel();
        $hierarchy = static::getContainer()->get(RoleHierarchyInterface::class);

        self::assertContains('ROLE_USER', $hierarchy->getReachableRoleNames(['ROLE_ADMIN']));
        self::assertNotContains('ROLE_ADMIN', $hierarchy->getReachableRoleNames(['ROLE_USER']));
    }

    public function testEveryUserHasRoleUser(): void
    {
        $user = new User('user@example.com');

        self::assertSame(['ROLE_USER'], $user->getRoles());

        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER', 'ROLE_ADMIN']);
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles(), 'ROLE_USER is implicit and roles are de-duplicated');
    }
}
