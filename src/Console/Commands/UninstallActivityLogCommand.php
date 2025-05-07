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

        $this->info('✅  Activity Log package uninstalled successfully.');
    }

    public function rollbackConfig(): void
    {
        // Delete published config
        $configPath = config_path('activity-log.php');
        if (file_exists($configPath)) {
            unlink($configPath);
            $this->info('⚠️ Removed config/activity-log.php');
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
                    $this->info("🔧 Rolled back anonymous migration in: {$migrationPath}");
                } catch (\Throwable $e) {
                    $this->error("❌ Failed to rollback anonymous migration: {$e->getMessage()}");
                }
            }

            // Delete the file after rollback
            unlink($migrationPath);
            $this->info("⚠️ Deleted migration: {$migrationPath}");
        }
    }

}
