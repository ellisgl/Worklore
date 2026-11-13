<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\Assert;

/**
 * For tests that reload an entity they just saved: fails the test if it's
 * missing, and returns it typed (find() itself can return null).
 */
trait FindsEntities
{
    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    protected static function findOrFail(EntityManagerInterface $em, string $class, mixed $id): object
    {
        $entity = $em->find($class, $id);
        Assert::assertNotNull($entity, "Expected a saved $class");

        return $entity;
    }
}
