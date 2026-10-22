<?php

namespace Alif\ActivityLog\Tests;

use Alif\ActivityLog\ActivityLogServiceProvider;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    protected function getPackageProviders($app): array
    {
        return [ActivityLogServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');

        $app['config']->set('activity-log.db_connection', 'sqlite');
        $app['config']->set('activity-log.use_queue', false);
        $app['config']->set('activity-log.encrypt_key', 'base64:' . base64_encode(str_repeat('k', 32)));
        $app['config']->set('activity-log.file_storage_disk', 'local');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
