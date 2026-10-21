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

            // the ip may come from a forwarded header: only public addresses are ever put into a request url.
            // private and reserved addresses are marked without a request, so they never leave the server
            $invalid = $all->reject(fn (string $ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false);
            $invalid->each(fn (string $ip) => $this->markInvalid($ip));

            $ips = $all->diff($invalid)->values();

            if ($ips->isEmpty()) {
                $this->info(__('activity-log::messages.ip_up_to_date'));

                return self::SUCCESS;
            }

            $this->info(__('activity-log::messages.ip_found', ['count' => $ips->count()]) . "\n");

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

        // bad IP: store a marker so it is not requested again
        if (! ($data['success'] ?? true)) {
            $this->markInvalid($ip);
            $this->warn(__('activity-log::messages.ip_invalid', ['ip' => $ip]));

            return;
        }

        // Update the logs of this IP that have no details yet
        ActivityLog::where('ip', $ip)->whereNull('ip_details')->update(['ip_details' => json_encode($data)]);

        $this->info(__('activity-log::messages.ip_updated', ['ip' => $ip]) . "\n");
    }

    // a marker stops the same bad IP from being requested again
    private function markInvalid(string $ip): void
    {
        ActivityLog::where('ip', $ip)->whereNull('ip_details')->update(['ip_details' => json_encode(['success' => false])]);
    }

    private function exactIgnored(): array
    {
        return array_values(array_unique(array_merge(['127.0.0.1', 'localhost'], config('activity-log.ip_ignore.exact', []))));
    }
}
