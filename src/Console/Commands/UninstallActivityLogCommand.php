<?php

/**
 * Created by Shukhratjon Yuldashev on 2025-05-05
 * Contact: https://t.me/alif_coder
 * Time: 2:46 PM
 */

namespace Alif\ActivityLog\Console\Commands;

use Illuminate\Console\Command;

class UninstallActivityLogCommand extends Command
{
    protected $signature = 'activity-log:uninstall {--force : Skip the confirmation}';

    protected $description = 'Remove config, migrations, and data related to Activity Log package';

    /** The package's own published migration only. Host migrations that mention activity_logs are never touched. */
    private const MIGRATION_FILE = '2025_04_30_000000_create_activity_logs_table.php';

    public function handle(): int
    {
        // rolling back drops the activity_logs table: destructive, so it needs an explicit yes
        if (! $this->option('force') && ! $this->confirm(__('activity-log::messages.uninstall_confirm'), false)) {
            $this->warn(__('activity-log::messages.uninstall_cancelled'));

            return self::FAILURE;
        }

        // Delete published config
        $this->rollbackConfig();

        // Delete published migrations
        $this->rollbackMigrations();

        $this->info(__('activity-log::messages.uninstalled'));

        return self::SUCCESS;
    }

    public function rollbackConfig(): void
    {
        // Delete published config
        $configPath = config_path('activity-log.php');
        if (file_exists($configPath)) {
            unlink($configPath);
            $this->info(__('activity-log::messages.config_removed'));
        }
    }

    public function rollbackMigrations(): void
    {
        $migrationPath = database_path('migrations/' . self::MIGRATION_FILE);

        if (! file_exists($migrationPath)) {
            return;
        }

        $migrationInstance = require $migrationPath;

        try {
            $migrationInstance->down();
            $this->info(__('activity-log::messages.migration_rolled_back', ['path' => $migrationPath]));
        } catch (\Throwable $e) {
            // a failed rollback keeps the file: it is the only record of what still has to be undone
            report($e);
            $this->error(__('activity-log::messages.migration_rollback_failed', ['message' => $e->getMessage()]));

            return;
        }

        unlink($migrationPath);
        $this->info(__('activity-log::messages.migration_deleted', ['path' => $migrationPath]));
    }
}
