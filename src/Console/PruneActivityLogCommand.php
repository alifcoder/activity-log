<?php

namespace Alif\ActivityLog\Console;

use Alif\ActivityLog\Models\ActivityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Bulk prune: one DB delete and one file delete per chunk instead of per row.
 * Prefer this over `model:prune` for large tables.
 */
class PruneActivityLogCommand extends Command
{
    protected $signature = 'activity-log:prune {--chunk=1000 : Rows processed per batch}';

    protected $description = 'Delete activity logs older than log_keep_days together with their encrypted files';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('activity-log.log_keep_days', 30));
        $disk = Storage::disk(config('activity-log.file_storage_disk'));
        $total = 0;

        ActivityLog::query()
            ->where('created_at', '<', $cutoff)
            ->chunkById((int) $this->option('chunk'), function ($logs) use ($disk, &$total) {
                $paths = $logs
                    ->flatMap(fn (ActivityLog $log) => [$log->request_body, $log->response_body, $log->curl])
                    ->filter()
                    ->values()
                    ->all();

                if ($paths !== []) {
                    $disk->delete($paths);
                }

                ActivityLog::query()->whereKey($logs->modelKeys())->delete();

                // newer logs may still point at a parent that was just deleted: detach them instead of leaving dangling ids
                ActivityLog::query()->whereIn('parent_id', $logs->modelKeys())->update(['parent_id' => null]);

                $total += $logs->count();
            });

        $this->info(__('activity-log::messages.logs_pruned', ['count' => $total]));
        $this->info(__('activity-log::messages.files_removed', ['count' => $this->removeExpiredFolders($disk, $cutoff)]));

        return self::SUCCESS;
    }

    /**
     * Files live in activity-log/YYYY-MM-DD/. A folder older than the cutoff day (minus one day of margin)
     * cannot hold files of any retained log, so it is removed without querying the database.
     */
    private function removeExpiredFolders($disk, $cutoff): int
    {
        $oldestKept = $cutoff->copy()->startOfDay()->subDay()->format('Y-m-d');
        $removed = 0;

        foreach ($disk->directories('activity-log') as $folder) {
            // YYYY-MM-DD names sort lexicographically in date order
            if (basename($folder) >= $oldestKept) {
                continue;
            }

            $files = $disk->allFiles($folder);

            if ($files !== []) {
                $disk->delete($files);
                $removed += count($files);
            }
        }

        return $removed;
    }
}
