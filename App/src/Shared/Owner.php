<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * Whoever owns a record (a company, a category, a journal entry, ...).
 *
 * The Company, Taxonomy and Journal modules depend on this, not on the
 * security user, so they don't see passwords or roles, and Identity can
 * change how accounts work without touching them. Doctrine maps the
 * interface to App\Identity\User (resolve_target_entities in doctrine.yaml),
 * so the relations and foreign keys are unchanged.
 */
interface Owner
{
    public function ownerId(): UserId;
}
