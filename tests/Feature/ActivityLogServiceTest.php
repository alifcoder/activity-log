<?php

namespace Alif\ActivityLog\Tests\Feature;

use Alif\ActivityLog\DTO\ActivityLogCreateDTO;
use Alif\ActivityLog\Facades\ActivityLogger;
use Alif\ActivityLog\Models\ActivityLog;
use Alif\ActivityLog\Tests\TestCase;
use GuzzleHttp\Psr7\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActivityLogServiceTest extends TestCase
{
    public function test_logs_with_the_same_additional_id_merge_into_one_row(): void
    {
        ActivityLogger::log(new ActivityLogCreateDTO(additional_id: 'req-1', log_type: 'middleware', url: 'https://x.test/a'));
        ActivityLogger::log(new ActivityLogCreateDTO(additional_id: 'req-1', status_code: '200'));

        $this->assertSame(1, ActivityLog::count());

        $log = ActivityLog::first();
        $this->assertSame('middleware', $log->log_type);
        $this->assertSame('200', $log->status_code);
    }

    public function test_additional_id_is_unique_in_the_schema(): void
    {
        ActivityLog::create(['additional_id' => 'req-2']);

        $this->expectException(QueryException::class);

        DB::connection('sqlite')->table('activity_logs')->insert([
            'id' => (string) Str::orderedUuid(),
            'additional_id' => 'req-2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_curl_keeps_headers_and_body_as_sent(): void
    {
        $request = new Request(
            'POST',
            'https://x.test/login',
            ['Authorization' => 'Bearer secret-token', 'Content-Type' => 'application/json'],
            '{"email":"a@b.c","password":"hunter2"}',
        );

        $curl = ActivityLogger::curlOfRequest($request);

        $this->assertStringContainsString("-H 'Authorization: Bearer secret-token'", $curl);
        $this->assertStringContainsString('{"email":"a@b.c","password":"hunter2"}', $curl);
    }
}
