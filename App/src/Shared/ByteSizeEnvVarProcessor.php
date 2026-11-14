<?php

declare(strict_types=1);

namespace App\Shared;

use Closure;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

use function is_string;
use function sprintf;

/**
 * %env(bytes:NAME)%: a size such as 512K, 25M or 1G (see ByteSize), as an
 * int number of bytes.
 */
final class ByteSizeEnvVarProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, Closure $getEnv): int
    {
        $value = $getEnv($name);
        try {
            return ByteSize::parse(is_string($value) ? $value : '');
        } catch (InvalidValueException $e) {
            throw new RuntimeException(sprintf('Env var "%s": %s', $name, $e->getMessage()), previous: $e);
        }
    }

    public static function getProvidedTypes(): array
    {
        return ['bytes' => 'int'];
    }
}
