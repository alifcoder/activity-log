<?php

return [
    'encryption_key_missing' => 'Activity Log shifrlash kaliti topilmadi. Iltimos, ishga tushiring: php artisan activity-log:generate-key',
    'encryption_key_invalid_format' => 'Shifrlash kaliti formati noto\'g\'ri. Base64 kodlangan 32 bayt kutiladi.',
    'encryption_key_invalid_length' => 'Shifrlash kaliti uzunligi noto\'g\'ri. 32 bayt kutiladi.',
    'encryption_failed' => 'Activity Log shifrlash amalga oshmadi.',
    'decryption_failed' => 'Activity Log ma\'lumotini ochib bo\'lmadi. Shifrlash kaliti o\'zgargan bo\'lishi mumkin.',

    'env_file_not_found' => '.env fayli topilmadi!',
    'key_already_set' => 'Activity Log shifrlash kaliti allaqachon o\'rnatilgan. Hech narsa o\'zgarmadi. Almashtirish uchun --force dan foydalaning (mavjud shifrlangan loglar o\'qilmaydigan bo\'ladi).',
    'key_generated' => 'Yangi Activity Log shifrlash kaliti yaratildi va .env fayliga yozildi.',

    'ip_fetching' => '🔍 Tafsilotlari yo\'q noyob IP\'lar qidirilmoqda...',
    'ip_up_to_date' => '✨ Barcha IP\'lar yangilangan. Sinxronlash kerak emas.',
    'ip_found' => ':count ta IP yangilanishi kerak.',
    'ip_sync_completed' => '🎉 Barcha IP tafsilotlari sinxronlandi.',
    'ip_sync_error' => '💥 Xato: :message',
    'ip_request_failed' => '❌ IP tafsilotlarini olib bo\'lmadi: :ip',
    'ip_invalid' => '⚠️ Noto\'g\'ri yoki lokal IP: :ip',
    'ip_updated' => '✅ IP yangilandi: :ip',

    'logs_pruned' => ':count ta activity log o\'chirildi.',
    'files_removed' => 'Muddati o\'tgan sana papkalaridan :count ta fayl o\'chirildi.',

    'duplicates_found' => ':count ta takrorlangan additional_id guruhi.',
    'duplicates_merged' => ':count ta takrorlangan additional_id guruhi birlashtirildi.',

    'uninstalled' => '✅  Activity Log paketi muvaffaqiyatli o\'chirildi.',
    'config_removed' => '⚠️ config/activity-log.php o\'chirildi',
    'migration_rolled_back' => '🔧 Anonim migratsiya orqaga qaytarildi: :path',
    'migration_rollback_failed' => '❌ Anonim migratsiyani orqaga qaytarib bo\'lmadi: :message',
    'migration_deleted' => '⚠️ Migratsiya o\'chirildi: :path',
];
