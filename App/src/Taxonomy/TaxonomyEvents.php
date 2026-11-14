<?php

declare(strict_types=1);

namespace App\Taxonomy;

use App\Shared\EventSourcing\EventCatalog;
use App\Taxonomy\Event\CategoryArchived;
use App\Taxonomy\Event\CategoryCreated;
use App\Taxonomy\Event\CategoryDescriptionChanged;
use App\Taxonomy\Event\CategoryRenamed;
use App\Taxonomy\Event\CategoryUnarchived;
use App\Taxonomy\Event\TagArchived;
use App\Taxonomy\Event\TagCreated;
use App\Taxonomy\Event\TagMoved;
use App\Taxonomy\Event\TagRenamed;
use App\Taxonomy\Event\TagUnarchived;

/**
 * The Taxonomy module's events and their stored names, one stream type per
 * aggregate. Never rename these.
 */
final class TaxonomyEvents implements EventCatalog
{
    public const string CATEGORY = 'category';
    public const string TAG = 'tag';

    public function events(): array
    {
        return [
            'category.created' => CategoryCreated::class,
            'category.renamed' => CategoryRenamed::class,
            'category.description_changed' => CategoryDescriptionChanged::class,
            'category.archived' => CategoryArchived::class,
            'category.unarchived' => CategoryUnarchived::class,
            'tag.created' => TagCreated::class,
            'tag.renamed' => TagRenamed::class,
            'tag.moved' => TagMoved::class,
            'tag.archived' => TagArchived::class,
            'tag.unarchived' => TagUnarchived::class,
        ];
    }
}
