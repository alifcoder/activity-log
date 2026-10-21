<?php

/**
 * Created by Shukhratjon Yuldashev on 2025-04-30
 * Contact: https://t.me/alif_coder
 * Time: 11:48 AM
 */

namespace Alif\ActivityLog\Facades;

use Alif\ActivityLog\Services\Interface\ActivityLogServiceInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void log(\Alif\ActivityLog\DTO\ActivityLogCreateDTO $dto)
 * @method static void updateOrCreate(\Alif\ActivityLog\DTO\ActivityLogCreateDTO $dto)
 * @method static string|null curlOfRequest(mixed $request)
 * @method static array getPayloadOfRequest(\Illuminate\Http\Request $request)
 */
class ActivityLogger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ActivityLogServiceInterface::class;
    }
}
