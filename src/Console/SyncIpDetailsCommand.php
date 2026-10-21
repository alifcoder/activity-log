<?php

/**
 * Created by Sardorbek Abduqodirov on 14.11.2025
 * Contact: https://t.me/blackprince1605
 * Time: 11:02
 */

namespace Alif\ActivityLog\Console;

use Alif\ActivityLog\Models\ActivityLog;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SyncIpDetailsCommand extends Command
{
    private const CHUNK_SIZE = 10;

    // pause between batches so the geo API rate limit is not hit by one long run
    private const BATCH_PAUSE_SECONDS = 1;

    protected $signature = 'activity-log:sync-ip-details';

    protected $description = 'Fetch missing IP details and store them in activity_logs table';

    public function handle(): int
    {
        try {
            $this->info(__('activity-log::messages.ip_fetching'));

            // ignored IPs (config) are filtered in SQL: they are never selected, so never requested
            $query = ActivityLog::query()
                ->whereNull('ip_details')
                ->whereNotNull('ip')
                ->whereNotIn('ip', $this->exactIgnored());

            $all = $query->select('ip')->distinct()->pluck('ip');

            // the ip may come from a forwarded header: only real addresses are ever put into a request url
            $invalid = $all->reject(fn (string $ip) => filter_var($ip, FILTER_VALIDATE_IP) !== false);
            $invalid->each(fn (string $ip) => ActivityLog::where('ip', $ip)->update(['ip_details' => json_encode(['success' => false])]));

            $ips = $all->diff($invalid)->values();

            if ($ips->isEmpty()) {
                $this->info(__('activity-log::messages.ip_up_to_date'));

                return self::SUCCESS;
            }

            $this->info(__('activity-log::messages.ip_found', ['count' => $ips->count()]) . '
');

            // requests run in parallel batches instead of one by one
            foreach ($ips->chunk(self::CHUNK_SIZE) as $index => $batch) {
                if ($index > 0) {
                    sleep(self::BATCH_PAUSE_SECONDS);
                }

                $responses = Http::pool(fn (Pool $pool) => $batch->map(
                    fn (string $ip) => $pool->as($ip)
                        ->timeout(10)
                        ->retry(3, 250)
                        ->get("https://ipwho.is/{$ip}"),
                )->all());

                foreach ($batch as $ip) {
                    $this->storeDetails($ip, $responses[$ip] ?? null);
                }
            }

            $this->info(__('activity-log::messages.ip_sync_completed'));

            return self::SUCCESS;

        } catch (\Throwable $e) {
            report($e);
            $this->error(__('activity-log::messages.ip_sync_error', ['message' => $e->getMessage()]));

            return self::FAILURE;
        }
    }

    private function storeDetails(string $ip, mixed $response): void
    {
        // connection errors come back as exceptions: retried on the next run
        if (! $response instanceof Response || $response->failed()) {
            $this->error(__('activity-log::messages.ip_request_failed', ['ip' => $ip]));

            return;
        }

        $data = $response->json();

        // bad or private IP: store a marker so it is not requested again
        if (! ($data['success'] ?? true)) {
            ActivityLog::where('ip', $ip)->update(['ip_details' => json_encode(['success' => false])]);
            $this->warn(__('activity-log::messages.ip_invalid', ['ip' => $ip]));

            return;
        }

        // Update all logs with this IP
        ActivityLog::where('ip', $ip)->update(['ip_details' => json_encode($data)]);

        $this->info(__('activity-log::messages.ip_updated', ['ip' => $ip]) . '
');
    }

    private function exactIgnored(): array
    {
        return array_values(array_unique(array_merge(['127.0.0.1', 'localhost'], config('activity-log.ip_ignore.exact', []))));
    }
}
