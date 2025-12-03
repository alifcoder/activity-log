<?php
/**
 * Created by Sardorbek Abduqodirov on 14.11.2025
 * Contact: https://t.me/blackprince1605
 * Time: 11:02
 */

namespace Alif\ActivityLog\Console;

use Alif\ActivityLog\Models\ActivityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncIpDetailsCommand extends Command
{
    protected $signature = 'activity-log:sync-ip-details';
    protected $description = 'Fetch missing IP details and store them in activity_logs table';

    public function handle(): int
    {
        try {
            $this->info('🔍 Fetching unique IPs without details...');

            $ips = ActivityLog::whereNull('ip_details')
                ->whereNotIn('ip', ['127.0.0.1', 'localhost'])
                ->select('ip')
                ->distinct()
                ->pluck('ip');

            if ($ips->isEmpty()) {
                $this->info("✨ All IPs already up-to-date. Nothing to sync.");
                return self::SUCCESS;
            }

            $this->info("Found {$ips->count()} IP(s) to update.\n");

            foreach ($ips as $ip) {
                if ($this->shouldIgnoreIp($ip)) {
                    $this->warn("⏭️ Ignored IP (config): {$ip}");
                    continue;
                }

                $this->info("🌐 Updating IP: {$ip}");

                // API Request with timeout + retry (safer)
                $response = Http::timeout(10)
                    ->retry(3, 250) // 3 retries with 250ms delay
                    ->get("https://ipwho.is/{$ip}");

                // If HTTP request failed
                if ($response->failed()) {
                    $this->error("❌ Failed to fetch details for IP: {$ip}");
                    continue;
                }

                $data = $response->json();

                // Sanity check: bad IP returns success=false
                if (!($data['success'] ?? true)) {
                    $this->warn("⚠️ Invalid or local IP: {$ip}");
                    continue;
                }

                // Update all logs with this IP
                ActivityLog::where('ip', $ip)
                    ->update(['ip_details' => json_encode($data)]);

                $this->info("✅ Updated IP: {$ip}\n");
            }

            $this->info("🎉 Completed syncing all IP details.");
            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("💥 Error: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function shouldIgnoreIp(string $ip): bool
    {
        $exact = config('activity-log.ip_ignore.exact', []);
        $prefixes = config('activity-log.ip_ignore.prefix', []);

        if (in_array($ip, $exact, true)) {
            return true;
        }

        foreach ($prefixes as $prefix) {
            if (str_starts_with($ip, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
