<?php

/**
 * Created by Sardorbek Abduqodirov on 17.11.2025
 * Contact: https://t.me/blackprince1605
 * Time: 15:27
 */

namespace Alif\ActivityLog\Services;

use Alif\ActivityLog\Facades\Encryption;
use Alif\ActivityLog\Services\Interface\FileStorageServiceInterface;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Storage;

class FileStorageService implements FileStorageServiceInterface
{
    public function storeEncrypted(string $content, string $fileName, ?string $date = null): string
    {
        $date ??= now()->format('Y-m-d');
        $path = "activity-log/{$date}/{$fileName}.log";

        Storage::disk(config('activity-log.file_storage_disk'))->put($path, Encryption::encrypt($content));

        return $path;
    }

    public function readEncrypted(string $path): ?string
    {
        try {
            // one read: a missing file is reported by the filesystem, no separate exists() round-trip
            return Encryption::decrypt(Storage::disk(config('activity-log.file_storage_disk'))->get($path));
        } catch (FileNotFoundException) {
            return null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function deleteEncrypted(string $path): bool
    {
        return Storage::disk(config('activity-log.file_storage_disk'))->delete($path);
    }
}
