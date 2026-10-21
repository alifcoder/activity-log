<?php

namespace Alif\ActivityLog\Tests\Feature;

use Alif\ActivityLog\DTO\ActivityLogCreateDTO;
use Alif\ActivityLog\Facades\ActivityLogger;
use Alif\ActivityLog\Models\ActivityLog;
use Alif\ActivityLog\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class StorageAndQueueFallbackTest extends TestCase
{
    public function test_log_is_stored_synchronously_when_the_queue_is_unavailable(): void
    {
        $this->app['config']->set('activity-log.use_queue', true);
        $this->app['config']->set('queue.default', 'missing-connection');

        ActivityLogger::log(new ActivityLogCreateDTO(additional_id: 'req-queue', log_type: 'custom'));

        $this->assertSame(1, ActivityLog::count());
        $this->assertSame('custom', ActivityLog::first()->log_type);
    }

    public function test_files_are_written_to_local_when_the_configured_disk_is_missing(): void
    {
        $this->app['config']->set('activity-log.file_storage_disk', 'not-defined');

        ActivityLogger::log(new ActivityLogCreateDTO(
            additional_id: 'req-disk',
            request_body: ['email' => 'a@b.c'],
        ));

        $path = ActivityLog::first()->request_body;

        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_files_are_written_to_local_when_the_disk_setting_is_empty(): void
    {
        $this->app['config']->set('activity-log.file_storage_disk', '');

        ActivityLogger::log(new ActivityLogCreateDTO(
            additional_id: 'req-empty',
            response_body: ['ok' => true],
        ));

        Storage::disk('local')->assertExists(ActivityLog::first()->response_body);
    }
}
