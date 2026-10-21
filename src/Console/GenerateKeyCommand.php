<?php

/**
 * Created by Sardorbek Abduqodirov on 17.11.2025
 * Contact: https://t.me/blackprince1605
 * Time: 15:49
 */

namespace Alif\ActivityLog\Console;

use Illuminate\Console\Command;

class GenerateKeyCommand extends Command
{
    protected $signature = 'activity-log:generate-key {--force : Replace an existing key}';

    protected $description = 'Generate a new encryption key for Activity Log';

    public function handle(): int
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            $this->error(__('activity-log::messages.env_file_not_found'));

            return self::FAILURE;
        }

        // replacing a key makes every log encrypted with the old key unreadable
        if (filled(config('activity-log.encrypt_key')) && ! $this->option('force')) {
            $this->warn(__('activity-log::messages.key_already_set'));

            return self::FAILURE;
        }

        $key = 'base64:' . base64_encode(random_bytes(32));

        // New env value
        $newLine = "ACTIVITY_LOG_ENCRYPT_KEY={$key}";

        $env = file_get_contents($envPath);

        if (str_contains($env, 'ACTIVITY_LOG_ENCRYPT_KEY=')) {
            // Change to old
            $env = preg_replace(
                '/ACTIVITY_LOG_ENCRYPT_KEY=.*/',
                $newLine,
                $env,
            );
        } else {
            // Add new raw
            $env .= "\n" . $newLine . "\n";
        }

        file_put_contents($envPath, $env);

        // the key itself is written to .env only, never printed to console or CI logs
        $this->info(__('activity-log::messages.key_generated'));

        return self::SUCCESS;
    }
}
