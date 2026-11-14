<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * Whoever owns a record (a company, a category, a journal entry, ...).
 *
 * The Company, Taxonomy and Journal modules depend on this, not on the
 * security user, so they don't see passwords or roles, and Identity can
 * change how accounts work without touching them. Controllers receive the
 * signed-in user as an Owner (#[CurrentUser] Owner $owner) and pass its
 * ownerId() to commands and queries; aggregates hold only the UserId.
 */
interface Owner
{
    public function ownerId(): UserId;
}
