<?php
/**
 * Created by Sardorbek Abduqodirov on 17.11.2025
 * Contact: https://t.me/blackprince1605
 * Time: 15:27
 */

namespace Alif\ActivityLog\Services;

use Alif\ActivityLog\Facades\Encryption;
use Alif\ActivityLog\Services\Interface\FileStorageServiceInterface;
use Illuminate\Support\Facades\Storage;

class FileStorageService implements FileStorageServiceInterface
{
    public function storeEncrypted(string $content, string $fileName): string
    {
        $path = "activity-log/{$fileName}.log";

        Storage::disk(config('activity-log.file_storage_disk'))->put($path, Encryption::encrypt($content));

        return $path;
    }

    public function readEncrypted(string $path): ?string
    {
        if (!Storage::disk(config('activity-log.file_storage_disk'))->exists($path)) {
            return null;
        }

        return Encryption::decrypt(Storage::disk(config('activity-log.file_storage_disk'))->get($path));
    }

    public function deleteEncrypted(string $path): bool
    {
        if (Storage::disk(config('activity-log.file_storage_disk'))->exists($path)) {
            return Storage::disk(config('activity-log.file_storage_disk'))->delete($path);
        }

        return false;
    }
}
