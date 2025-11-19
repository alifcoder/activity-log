<?php
/**
 * Created by Sardorbek Abduqodirov on 17.11.2025
 * Contact: https://t.me/blackprince1605
 * Time: 15:23
 */

namespace Alif\ActivityLog\Services;

use Alif\ActivityLog\Services\Interface\EncryptionServiceInterface;

class EncryptionService implements EncryptionServiceInterface
{
    private ?string $key;

    public function __construct()
    {
        $raw = config('activity-log.encrypt_key');

        if (!$raw) {
            throw new \RuntimeException(
                "Activity Log encryption key is missing. Please run: php artisan activity-log:generate-key"
            );
        }

        if (str_starts_with($raw, 'base64:')) {
            $raw = substr($raw, 7);
        }

        // base64 -> binary
        $decoded = base64_decode($raw, true);

        if ($decoded === false) {
            throw new \Exception("Invalid encryption key format. Expected base64-encoded 32 bytes.");
        }

        if (strlen($decoded) !== 32) {
            throw new \RuntimeException("Invalid encryption key length. Expected 32 bytes.");
        }

        $this->key = $decoded;
    }

    public function encrypt(string $data): string
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->key, 0, $iv);

        return base64_encode($iv . $encrypted);
    }

    public function decrypt(string $encrypted): string
    {
        $decoded = base64_decode($encrypted);
        $iv = substr($decoded, 0, 16);
        $cipher = substr($decoded, 16);

        return openssl_decrypt($cipher, 'AES-256-CBC', $this->key, 0, $iv);
    }
}
