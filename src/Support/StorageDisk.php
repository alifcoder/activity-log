<?php

namespace Alif\ActivityLog\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class StorageDisk
{
    /**
     * The configured file storage disk. Empty or undefined names fall back to 'local':
     * Storage::disk() would otherwise use filesystems.default, which may be S3 in production.
     */
    public static function resolve(): Filesystem
    {
        $name = config('activity-log.file_storage_disk') ?: 'local';

        return Storage::disk(config("filesystems.disks.{$name}") !== null ? $name : 'local');
    }
}
