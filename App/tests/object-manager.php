<?php

declare(strict_types=1);

/*
 * Gives PHPStan's Doctrine extension the entity manager, so it can read the
 * mappings and check DQL. Referenced from phpstan.dist.neon.
 */

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__).'/.env');

$env = $_SERVER['APP_ENV'] ?? 'dev';
$kernel = new Kernel(is_string($env) ? $env : 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
