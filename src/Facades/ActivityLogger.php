<?php
/**
 * Created by Shukhratjon Yuldashev on 2025-04-30
 * Contact: https://t.me/alif_coder
 * Time: 11:48 AM
 */

namespace Alif\ActivityLog\Facades;

use Alif\ActivityLog\Services\Interface\ActivityLogServiceInterface;
use Illuminate\Support\Facades\Facade;

class ActivityLogger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ActivityLogServiceInterface::class;
    }
}