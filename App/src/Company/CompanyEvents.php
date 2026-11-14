<?php

declare(strict_types=1);

namespace App\Company;

use App\Company\Event\CompanyArchived;
use App\Company\Event\CompanyCreated;
use App\Company\Event\CompanyOverviewChanged;
use App\Company\Event\CompanyRenamed;
use App\Company\Event\CompanyUnarchived;
use App\Company\Event\PositionArchived;
use App\Company\Event\PositionCreated;
use App\Company\Event\PositionEnded;
use App\Company\Event\PositionOverviewChanged;
use App\Company\Event\PositionReopened;
use App\Company\Event\PositionRetitled;
use App\Company\Event\PositionStartDateChanged;
use App\Company\Event\PositionUnarchived;
use App\Company\Event\ProductArchived;
use App\Company\Event\ProductCreated;
use App\Company\Event\ProductOverviewChanged;
use App\Company\Event\ProductRenamed;
use App\Company\Event\ProductUnarchived;
use App\Company\Event\ProjectArchived;
use App\Company\Event\ProjectCreated;
use App\Company\Event\ProjectOverviewChanged;
use App\Company\Event\ProjectRenamed;
use App\Company\Event\ProjectRetagged;
use App\Company\Event\ProjectUnarchived;
use App\Shared\EventSourcing\EventCatalog;

/**
 * The Company module's events and their stored names, one stream type per
 * aggregate. Never rename these.
 */
final class CompanyEvents implements EventCatalog
{
    public const string COMPANY = 'company';
    public const string PRODUCT = 'product';
    public const string PROJECT = 'project';
    public const string POSITION = 'position';

    public function events(): array
    {
        return [
            'company.created' => CompanyCreated::class,
            'company.renamed' => CompanyRenamed::class,
            'company.overview_changed' => CompanyOverviewChanged::class,
            'company.archived' => CompanyArchived::class,
            'company.unarchived' => CompanyUnarchived::class,
            'product.created' => ProductCreated::class,
            'product.renamed' => ProductRenamed::class,
            'product.overview_changed' => ProductOverviewChanged::class,
            'product.archived' => ProductArchived::class,
            'product.unarchived' => ProductUnarchived::class,
            'project.created' => ProjectCreated::class,
            'project.renamed' => ProjectRenamed::class,
            'project.overview_changed' => ProjectOverviewChanged::class,
            'project.retagged' => ProjectRetagged::class,
            'project.archived' => ProjectArchived::class,
            'project.unarchived' => ProjectUnarchived::class,
            'position.created' => PositionCreated::class,
            'position.retitled' => PositionRetitled::class,
            'position.overview_changed' => PositionOverviewChanged::class,
            'position.start_date_changed' => PositionStartDateChanged::class,
            'position.ended' => PositionEnded::class,
            'position.reopened' => PositionReopened::class,
            'position.archived' => PositionArchived::class,
            'position.unarchived' => PositionUnarchived::class,
        ];
    }
}
