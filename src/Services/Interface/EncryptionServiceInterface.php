<?php
/**
 * Created by Sardorbek Abduqodirov on 17.11.2025
 * Contact: https://t.me/blackprince1605
 * Time: 15:19
 */

namespace Alif\ActivityLog\Services\Interface;

interface EncryptionServiceInterface
{
    public function encrypt(string $data): string;

    public function decrypt(string $encrypted): string;
}
