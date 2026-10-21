<?php

return [
    'encryption_key_missing' => 'Ключ шифрования Activity Log не задан. Выполните: php artisan activity-log:generate-key',
    'encryption_key_invalid_format' => 'Неверный формат ключа шифрования. Ожидается base64 от 32 байт.',
    'encryption_key_invalid_length' => 'Неверная длина ключа шифрования. Ожидается 32 байта.',
    'encryption_failed' => 'Не удалось зашифровать запись Activity Log.',
    'decryption_failed' => 'Не удалось расшифровать запись Activity Log. Возможно, ключ шифрования изменился.',

    'env_file_not_found' => 'Файл .env не найден!',
    'key_already_set' => 'Ключ шифрования Activity Log уже задан. Ничего не изменено. Используйте --force для замены (существующие зашифрованные логи станут нечитаемыми).',
    'key_generated' => 'Новый ключ шифрования Activity Log создан и записан в .env.',

    'ip_fetching' => '🔍 Поиск уникальных IP без данных...',
    'ip_up_to_date' => '✨ Все IP уже актуальны. Синхронизация не требуется.',
    'ip_found' => 'IP для обновления: :count.',
    'ip_sync_completed' => '🎉 Синхронизация данных IP завершена.',
    'ip_sync_error' => '💥 Ошибка: :message',
    'ip_request_failed' => '❌ Не удалось получить данные для IP: :ip',
    'ip_invalid' => '⚠️ Некорректный или локальный IP: :ip',
    'ip_updated' => '✅ IP обновлён: :ip',

    'logs_pruned' => 'Удалено записей Activity Log: :count.',
    'files_removed' => 'Удалено файлов из устаревших папок по дате: :count.',

    'uninstalled' => '✅  Пакет Activity Log успешно удалён.',
    'config_removed' => '⚠️ Удалён config/activity-log.php',
    'migration_rolled_back' => '🔧 Анонимная миграция откачена: :path',
    'migration_rollback_failed' => '❌ Не удалось откатить анонимную миграцию: :message',
    'migration_deleted' => '⚠️ Удалена миграция: :path',
];
