<?php

namespace Alif\ActivityLog\Console;

use Alif\ActivityLog\Facades\FileStorage;
use Alif\ActivityLog\Models\ActivityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Merges rows that share an additional_id (written by concurrent workers before the unique index existed).
 * Keeps the oldest row, fills its empty attributes from the others, and deletes the others with their files.
 * Run this before migrating the unique index on additional_id.
 */
class MergeDuplicateActivityLogsCommand extends Command
{
    private const MERGEABLE = [
        'parent_id', 'log_type', 'user_id', 'module', 'route', 'url', 'model_id', 'model_type',
        'user_agent', 'app_type', 'app_version', 'ip', 'method', 'status_code', 'request_body',
        'response_body', 'curl', 'description', 'request_duration', 'peak_memory_usage', 'ip_details',
    ];

    private const FILE_COLUMNS = ['request_body', 'response_body', 'curl'];

    protected $signature = 'activity-log:merge-duplicates {--dry-run : Only count duplicate groups}';

    protected $description = 'Merge activity log rows that share the same additional_id';

    public function handle(): int
    {
        $ids = ActivityLog::query()
            ->whereNotNull('additional_id')
            ->groupBy('additional_id')
            ->havingRaw('count(*) > 1')
            ->pluck('additional_id');

        if ($this->option('dry-run')) {
            $this->info(__('activity-log::messages.duplicates_found', ['count' => $ids->count()]));

            return self::SUCCESS;
        }

        $merged = 0;

        foreach ($ids as $additionalId) {
            DB::connection(config('activity-log.db_connection'))->transaction(function () use ($additionalId, &$merged) {
                $rows = ActivityLog::query()->where('additional_id', $additionalId)->orderBy('created_at')->orderBy('id')->get();
                $keeper = $rows->shift();

                $attributes = [];
                foreach (self::MERGEABLE as $column) {
                    if ($keeper->{$column} === null) {
                        $value = $rows->pluck($column)->first(fn ($v) => $v !== null);
                        if ($value !== null) {
                            $attributes[$column] = $value;
                        }
                    }
                }

                foreach ($rows as $row) {
                    foreach (self::FILE_COLUMNS as $column) {
                        // the other row's file is only needed if the keeper has no file for that column
                        if ($row->{$column} && $row->{$column} !== ($attributes[$column] ?? $keeper->{$column})) {
                            FileStorage::deleteEncrypted($row->{$column});
                        }
                    }
                }

                if ($attributes !== []) {
                    $keeper->forceFill($attributes)->save();
                }

                ActivityLog::query()->whereKey($rows->modelKeys())->delete();
                $merged++;
            });
        }

        $this->info(__('activity-log::messages.duplicates_merged', ['count' => $merged]));

        return self::SUCCESS;
    }
}
