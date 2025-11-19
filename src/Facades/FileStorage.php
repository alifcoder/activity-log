<?php
/**
 * Created by Sardorbek Abduqodirov on 17.11.2025
 * Contact: https://t.me/blackprince1605
 * Time: 15:39
 */

namespace Alif\ActivityLog\Facades;

use Alif\ActivityLog\Services\Interface\FileStorageServiceInterface;
use Illuminate\Support\Facades\Facade;

class FileStorage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FileStorageServiceInterface::class;
    }
}
