<?php

declare(strict_types=1);

namespace App\Shared;

use Closure;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

use function is_string;
use function preg_match;
use function sprintf;
use function strtoupper;

/**
 * %env(bytes:NAME)%: a size such as 512K, 25M or 1G (powers of 1024), or a
 * plain number of bytes, as an int.
 */
final class ByteSizeEnvVarProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, Closure $getEnv): int
    {
        $value = $getEnv($name);
        if (!is_string($value) || 1 !== preg_match('/^\s*(\d+)\s*([KMG]?)B?\s*$/i', $value, $match)) {
            throw new RuntimeException(sprintf('Env var "%s" must be a size such as 25M or 1G.', $name));
        }

        return (int) $match[1] * match (strtoupper($match[2])) {
            'K' => 1024,
            'M' => 1024 ** 2,
            'G' => 1024 ** 3,
            default => 1,
        };
    }

    public static function getProvidedTypes(): array
    {
        return ['bytes' => 'int'];
    }
}
