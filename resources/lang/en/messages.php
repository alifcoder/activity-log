<?php

return [
    'encryption_key_missing' => 'Activity Log encryption key is missing. Please run: php artisan activity-log:generate-key',
    'encryption_key_invalid_format' => 'Invalid encryption key format. Expected base64-encoded 32 bytes.',
    'encryption_key_invalid_length' => 'Invalid encryption key length. Expected 32 bytes.',
    'encryption_failed' => 'Activity Log encryption failed.',
    'decryption_failed' => 'Activity Log decryption failed. The encryption key may have changed.',

    'env_file_not_found' => '.env file not found!',
    'key_already_set' => 'Activity Log encryption key is already set. Nothing changed. Use --force to replace it (existing encrypted logs become unreadable).',
    'key_generated' => 'New Activity Log encryption key generated and written to .env.',

    'ip_fetching' => '🔍 Fetching unique IPs without details...',
    'ip_up_to_date' => '✨ All IPs already up-to-date. Nothing to sync.',
    'ip_found' => ':count IP(s) to update.',
    'ip_sync_completed' => '🎉 Completed syncing all IP details.',
    'ip_sync_error' => '💥 Error: :message',
    'ip_request_failed' => '❌ Failed to fetch details for IP: :ip',
    'ip_invalid' => '⚠️ Invalid or local IP: :ip',
    'ip_updated' => '✅ Updated IP: :ip',

    'logs_pruned' => 'Pruned :count activity log(s).',
    'files_removed' => 'Removed :count file(s) from expired date folders.',

    'uninstalled' => '✅  Activity Log package uninstalled successfully.',
    'config_removed' => '⚠️ Removed config/activity-log.php',
    'migration_rolled_back' => '🔧 Rolled back anonymous migration in: :path',
    'migration_rollback_failed' => '❌ Failed to rollback anonymous migration: :message',
    'migration_deleted' => '⚠️ Deleted migration: :path',
    'uninstall_confirm' => 'This drops the activity_logs table and deletes the package migration. Continue?',
    'uninstall_cancelled' => 'Uninstall cancelled.',
];
