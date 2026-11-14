<?php

declare(strict_types=1);

namespace App\Shared;

use SensitiveParameter;
use WeakMap;

/**
 * A value that must not show up in dumps, logs or the profiler, such as a
 * password carried by a command. It has no instance properties for a dumper
 * to read: the value sits in a private map keyed by the object, freed with
 * it, and only reveal() returns it.
 */
final class Secret
{
    /** @var WeakMap<self, string>|null */
    private static ?WeakMap $values = null;

    public function __construct(#[SensitiveParameter] string $value)
    {
        self::$values ??= new WeakMap();
        self::$values[$this] = $value;
    }

    public function reveal(): string
    {
        return self::$values[$this] ?? '';
    }

    /**
     * @return array{value: string}
     */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }
}
