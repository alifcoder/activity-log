<?php
/**
 * Created by Sardorbek Abduqodirov on 17.11.2025
 * Contact: https://t.me/blackprince1605
 * Time: 15:38
 */

namespace Alif\ActivityLog\Facades;

use Alif\ActivityLog\Services\Interface\EncryptionServiceInterface;
use Illuminate\Support\Facades\Facade;

class Encryption extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return EncryptionServiceInterface::class;
    }
}
