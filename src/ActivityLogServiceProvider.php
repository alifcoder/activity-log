<?php
/**
 * Created by Shukhratjon Yuldashev on 2025-04-30
 * Contact: https://t.me/alif_coder
 * Time: 11:47AM
 */

namespace Alif\ActivityLog;

use Alif\ActivityLog\Console\Commands\UninstallActivityLogCommand;
use Alif\ActivityLog\Macros\HttpMacro;
use Alif\ActivityLog\Middleware\LogActivity;
use Alif\ActivityLog\Services\ActivityLogService;
use Alif\ActivityLog\Services\Interface\ActivityLogServiceInterface;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Support\ServiceProvider;

class ActivityLogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register the middleware
        $router = $this->app['router'];
        $router->aliasMiddleware('log.activity', LogActivity::class);

        // Merge the configuration file
        $this->mergeConfigFrom(__DIR__ . '/../config/activity-log.php', 'activity-log');

        // Register the console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                                    UninstallActivityLogCommand::class,
                            ]);
        }

        // Register the service
        $this->app->bind(ActivityLogServiceInterface::class, ActivityLogService::class);
    }

    public function boot(Kernel $kernel): void
    {
        // Register the macro
        HttpMacro::register();

        // Allow publishing
        $this->publishes(
                [
                        (__DIR__ . '/../config/activity-log.php') => config_path('activity-log.php'),
                                                               (__DIR__ . '/../database/migrations/2025_04_30_000000_create_activity_logs_table.php') => database_path('migrations/' . date('Y_m_d_His', time()) . '_create_activity_logs_table.php'),
                ], 'activity-log');

        // Register global middleware
        $kernel->pushMiddleware(LogActivity::class);
    }
}