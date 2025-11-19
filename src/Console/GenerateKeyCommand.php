<?php
/**
 * Created by Sardorbek Abduqodirov on 17.11.2025
 * Contact: https://t.me/blackprince1605
 * Time: 15:49
 */

namespace Alif\ActivityLog\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateKeyCommand extends Command
{
    protected $signature = 'activity-log:generate-key';
    protected $description = 'Generate a new encryption key for Activity Log';

    public function handle()
    {
        $key = "base64:".base64_encode(random_bytes(32));

        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            $this->error('.env file not found!');
            return self::FAILURE;
        }

        // New env value
        $newLine = "ACTIVITY_LOG_ENCRYPT_KEY={$key}";

        $env = file_get_contents($envPath);

        if (str_contains($env, 'ACTIVITY_LOG_ENCRYPT_KEY=')) {
            // Change to old
            $env = preg_replace(
                '/ACTIVITY_LOG_ENCRYPT_KEY=.*/',
                $newLine,
                $env
            );
        } else {
            // Add new raw
            $env .= "\n" . $newLine . "\n";
        }

        file_put_contents($envPath, $env);

        $this->info("New Activity Log Encryption Key generated successfully!");
        $this->info("KEY: {$key}");

        return self::SUCCESS;
    }
}
