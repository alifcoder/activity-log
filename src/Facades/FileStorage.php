<?php

/**
 * Created by Sardorbek Abduqodirov on 17.11.2025
 * Contact: https://t.me/blackprince1605
 * Time: 15:39
 */

namespace Alif\ActivityLog\Facades;

use Alif\ActivityLog\Services\Interface\FileStorageServiceInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string storeEncrypted(string $content, string $fileName, ?string $date = null)
 * @method static string|null readEncrypted(string $path)
 * @method static bool deleteEncrypted(string $path)
 */
class FileStorage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FileStorageServiceInterface::class;
    }
}
