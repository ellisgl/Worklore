<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Overrides KernelTrait::getAllowedEnvs(), which Symfony calls to reject
     * unknown APP_ENV values; PhpStorm doesn't see the trait's call.
     *
     * @return list<string> An array of allowed values for APP_ENV
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
