<?php

declare(strict_types=1);

namespace PauloCastellano\DatabaseBackup\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use PauloCastellano\DatabaseBackup\DatabaseBackupServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            DatabaseBackupServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
    }
}
