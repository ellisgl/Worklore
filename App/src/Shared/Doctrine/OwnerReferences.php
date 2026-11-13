<?php

declare(strict_types=1);

namespace App\Shared\Doctrine;

use App\Shared\Owner;
use App\Shared\UserId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use LogicException;

/**
 * Turns an owner's id (as carried by commands) into an Owner that entities
 * and repositories can use, without querying: Doctrine returns a lazy
 * reference, resolved to the user class by resolve_target_entities.
 */
final readonly class OwnerReferences
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @throws ORMException
     */
    public function get(UserId $id): Owner
    {
        return $this->em->getReference(Owner::class, $id)
            ?? throw new LogicException('Owner is not a mapped entity; check resolve_target_entities in doctrine.yaml');
    }
}
