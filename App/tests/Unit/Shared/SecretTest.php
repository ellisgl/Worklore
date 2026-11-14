<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Secret;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

use function print_r;
use function var_export;

final class SecretTest extends TestCase
{
    public function testRevealsItsValueOnlyWhenAsked(): void
    {
        $secret = new Secret('correct-horse-battery');

        self::assertSame('correct-horse-battery', $secret->reveal());
        self::assertStringNotContainsString('correct-horse', print_r($secret, true));
        self::assertStringNotContainsString('correct-horse', (string) new CliDumper()->dump(new VarCloner()->cloneVar($secret), true), 'Hidden from VarDumper, so from the profiler too');
        self::assertStringContainsString('***', print_r($secret, true));
        self::assertStringNotContainsString('correct-horse', var_export($secret->__debugInfo(), true));
    }
}
