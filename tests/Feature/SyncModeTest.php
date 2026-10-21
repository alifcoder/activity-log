<?php

namespace Alif\ActivityLog\Tests\Feature;

use Alif\ActivityLog\DTO\ActivityLogCreateDTO;
use Alif\ActivityLog\Facades\ActivityLogger;
use Alif\ActivityLog\Models\ActivityLog;
use Alif\ActivityLog\Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class SyncModeTest extends TestCase
{
    public function test_sync_mode_with_unset_disk_writes_row_and_local_files(): void
    {
        Queue::fake();
        $this->app['config']->set('activity-log.use_queue', false);
        $this->app['config']->set('activity-log.file_storage_disk', null);

        ActivityLogger::log(new ActivityLogCreateDTO(
            additional_id: 'req-sync',
            request_body: ['a' => 1],
            response_body: ['b' => 2],
            curl: 'curl -X GET https://x.test',
        ));

        Queue::assertNothingPushed();
        $log = ActivityLog::first();
        $this->assertNotNull($log);
        Storage::disk('local')->assertExists($log->request_body);
        Storage::disk('local')->assertExists($log->response_body);
        Storage::disk('local')->assertExists($log->curl);
    }
}
