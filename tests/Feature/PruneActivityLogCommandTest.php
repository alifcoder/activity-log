<?php

namespace Alif\ActivityLog\Tests\Feature;

use Alif\ActivityLog\DTO\ActivityLogCreateDTO;
use Alif\ActivityLog\Facades\ActivityLogger;
use Alif\ActivityLog\Models\ActivityLog;
use Alif\ActivityLog\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PruneActivityLogCommandTest extends TestCase
{
    public function test_prunes_old_rows_files_and_detaches_newer_children(): void
    {
        config(['activity-log.log_keep_days' => 30]);

        ActivityLogger::log(new ActivityLogCreateDTO(additional_id: 'old', request_body: ['a' => 1], response_body: ['b' => 2]));
        $old = ActivityLog::first();
        DB::connection('sqlite')->table('activity_logs')->where('id', $old->id)->update(['created_at' => now()->subDays(100)]);

        ActivityLog::create(['additional_id' => 'new', 'parent_id' => $old->id]);

        $this->artisan('activity-log:prune')->assertSuccessful();

        $this->assertNull(ActivityLog::find($old->id));
        $this->assertNull(ActivityLog::where('additional_id', 'new')->first()->parent_id);
        $this->assertSame([], Storage::disk('local')->allFiles('activity-log/' . now()->subDays(100)->format('Y-m-d')));
    }
}
