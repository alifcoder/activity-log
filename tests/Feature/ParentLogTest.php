<?php

namespace Alif\ActivityLog\Tests\Feature;

use Alif\ActivityLog\DTO\ActivityLogCreateDTO;
use Alif\ActivityLog\Facades\ActivityLogger;
use Alif\ActivityLog\Models\ActivityLog;
use Alif\ActivityLog\Tests\TestCase;

class ParentLogTest extends TestCase
{
    public function test_parent_follows_event_time_not_write_order(): void
    {
        $earlier = now()->subMinute()->format('Y-m-d H:i:s.u');
        $later = now()->format('Y-m-d H:i:s.u');

        // queued jobs may run out of order: the later event is written first
        ActivityLogger::log(new ActivityLogCreateDTO(additional_id: 'later', model_id: '7', model_type: 'Order', created_at: $later));
        ActivityLogger::log(new ActivityLogCreateDTO(additional_id: 'earlier', model_id: '7', model_type: 'Order', created_at: $earlier));

        $earlierLog = ActivityLog::where('additional_id', 'earlier')->first();
        $laterLog = ActivityLog::where('additional_id', 'later')->first();

        $this->assertNull($earlierLog->parent_id);
        $this->assertSame($earlierLog->id, $laterLog->parent_id);
    }

    public function test_late_log_is_inserted_between_its_neighbours(): void
    {
        $first = now()->subMinutes(2)->format('Y-m-d H:i:s.u');
        $middle = now()->subMinute()->format('Y-m-d H:i:s.u');
        $last = now()->format('Y-m-d H:i:s.u');

        ActivityLogger::log(new ActivityLogCreateDTO(additional_id: 'first', model_id: '7', model_type: 'Order', created_at: $first));
        ActivityLogger::log(new ActivityLogCreateDTO(additional_id: 'last', model_id: '7', model_type: 'Order', created_at: $last));
        ActivityLogger::log(new ActivityLogCreateDTO(additional_id: 'middle', model_id: '7', model_type: 'Order', created_at: $middle));

        $firstLog = ActivityLog::where('additional_id', 'first')->first();
        $middleLog = ActivityLog::where('additional_id', 'middle')->first();
        $lastLog = ActivityLog::where('additional_id', 'last')->first();

        $this->assertNull($firstLog->parent_id);
        $this->assertSame($firstLog->id, $middleLog->parent_id);
        $this->assertSame($middleLog->id, $lastLog->parent_id);
    }

    public function test_log_for_same_model_points_to_previous_log(): void
    {
        ActivityLogger::log(new ActivityLogCreateDTO(additional_id: 'first', model_id: '7', model_type: 'Order'));
        ActivityLogger::log(new ActivityLogCreateDTO(additional_id: 'second', model_id: '7', model_type: 'Order'));

        $first = ActivityLog::where('additional_id', 'first')->first();
        $second = ActivityLog::where('additional_id', 'second')->first();

        $this->assertNull($first->parent_id);
        $this->assertSame($first->id, $second->parent_id);
    }

    public function test_log_for_other_model_has_no_parent(): void
    {
        ActivityLogger::log(new ActivityLogCreateDTO(additional_id: 'order', model_id: '7', model_type: 'Order'));
        ActivityLogger::log(new ActivityLogCreateDTO(additional_id: 'invoice', model_id: '7', model_type: 'Invoice'));

        $this->assertNull(ActivityLog::where('additional_id', 'invoice')->first()->parent_id);
    }

    public function test_log_without_model_has_no_parent(): void
    {
        ActivityLogger::log(new ActivityLogCreateDTO(additional_id: 'plain'));

        $this->assertNull(ActivityLog::where('additional_id', 'plain')->first()->parent_id);
    }

    public function test_log_never_points_to_itself_and_keeps_parent_on_response_update(): void
    {
        ActivityLogger::log(new ActivityLogCreateDTO(additional_id: 'first', model_id: '7', model_type: 'Order'));
        ActivityLogger::log(new ActivityLogCreateDTO(additional_id: 'second', model_id: '7', model_type: 'Order'));

        // second write for the same request, without model data (like the HTTP response update)
        ActivityLogger::log(new ActivityLogCreateDTO(additional_id: 'second', status_code: '200'));

        $first = ActivityLog::where('additional_id', 'first')->first();
        $second = ActivityLog::where('additional_id', 'second')->first();

        $this->assertNotSame($second->id, $second->parent_id);
        $this->assertSame($first->id, $second->parent_id);
        $this->assertSame('200', $second->status_code);
        $this->assertSame(2, ActivityLog::count());
    }
}
