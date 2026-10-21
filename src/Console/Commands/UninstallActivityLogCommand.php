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
    protected $signature = 'activity-log:uninstall';

    protected $description = 'Remove config, migrations, and data related to Activity Log package';

    public function handle(): void
    {
        // Delete published config
        $this->rollbackConfig();

        // Delete published migrations
        $this->rollbackMigrations();

        $this->info(__('activity-log::messages.uninstalled'));
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
        $migrations = collect(glob(database_path('migrations/*activity_log*.php')))
            ->sortDesc(); // Sort by newest first

        foreach ($migrations as $migrationPath) {
            $migrationInstance = require $migrationPath;

            if (method_exists($migrationInstance, 'down')) {
                try {
                    $migrationInstance->down();
                    $this->info(__('activity-log::messages.migration_rolled_back', ['path' => $migrationPath]));
                } catch (\Throwable $e) {
                    report($e);
                    $this->error(__('activity-log::messages.migration_rollback_failed', ['message' => $e->getMessage()]));
                }
            }

            // Delete the file after rollback
            unlink($migrationPath);
            $this->info(__('activity-log::messages.migration_deleted', ['path' => $migrationPath]));
        }
    }
}
