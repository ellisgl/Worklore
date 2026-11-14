<?php

declare(strict_types=1);

namespace App\Purge;

use App\Company\CompanyEvents;
use App\Journal\JournalEvents;
use App\Taxonomy\TaxonomyEvents;

use function str_replace;

/**
 * What can be purged, by the name its API uses (/api/{name}/...), with its
 * stream type and read table.
 */
enum PurgeTarget: string
{
    case Companies = 'companies';
    case Products = 'products';
    case Projects = 'projects';
    case Positions = 'positions';
    case JournalEntries = 'journal-entries';
    case Categories = 'categories';
    case Tags = 'tags';

    public function streamType(): string
    {
        return match ($this) {
            self::Companies => CompanyEvents::COMPANY,
            self::Products => CompanyEvents::PRODUCT,
            self::Projects => CompanyEvents::PROJECT,
            self::Positions => CompanyEvents::POSITION,
            self::JournalEntries => JournalEvents::STREAM,
            self::Categories => TaxonomyEvents::CATEGORY,
            self::Tags => TaxonomyEvents::TAG,
        };
    }

    public function table(): string
    {
        return match ($this) {
            self::Positions => '"position"',
            default => $this->streamType(),
        };
    }

    /**
     * The key in a PurgeReport: journal_entries rather than journal-entries.
     */
    public function key(): string
    {
        return str_replace('-', '_', $this->value);
    }
}
