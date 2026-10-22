<?php

/**
 * Created by Sardorbek Abduqodirov on 17.11.2025
 * Contact: https://t.me/blackprince1605
 * Time: 15:23
 */

namespace Alif\ActivityLog\Services;

use Alif\ActivityLog\Services\Interface\EncryptionServiceInterface;

/**
 * Format v2 (written): "v2:" . base64(iv[12] . tag[16] . ciphertext), AES-256-GCM (authenticated).
 * Format v1 (legacy, read only): base64(iv[16] . base64(AES-256-CBC ciphertext)).
 */
class EncryptionService implements EncryptionServiceInterface
{
    private const PREFIX = 'v2:';

    private const GCM_IV_BYTES = 12;

    private const GCM_TAG_BYTES = 16;

    private string $key;

    public function __construct()
    {
        $raw = config('activity-log.encrypt_key');

        if (! $raw) {
            throw new \RuntimeException(
                __('activity-log::messages.encryption_key_missing'),
            );
        }

        if (str_starts_with($raw, 'base64:')) {
            $raw = substr($raw, 7);
        }

        // base64 -> binary
        $decoded = base64_decode($raw, true);

        if ($decoded === false) {
            throw new \RuntimeException(__('activity-log::messages.encryption_key_invalid_format'));
        }

        if (strlen($decoded) !== 32) {
            throw new \RuntimeException(__('activity-log::messages.encryption_key_invalid_length'));
        }

        $this->key = $decoded;
    }

    public function encrypt(string $data): string
    {
        $iv = random_bytes(self::GCM_IV_BYTES);
        $tag = '';

        $cipher = openssl_encrypt($data, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag, '', self::GCM_TAG_BYTES);

        // a failed encryption must not be stored as an empty or partial body
        if ($cipher === false) {
            throw new \RuntimeException(__('activity-log::messages.encryption_failed'));
        }

        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    public function decrypt(string $encrypted): string
    {
        $plain = str_starts_with($encrypted, self::PREFIX)
                ? $this->decryptGcm(substr($encrypted, strlen(self::PREFIX)))
                : $this->decryptLegacyCbc($encrypted);

        if ($plain === false) {
            // wrong key, tampered or corrupted data: never return an empty string silently
            throw new \RuntimeException(__('activity-log::messages.decryption_failed'));
        }

        return $plain;
    }

    private function decryptGcm(string $payload): string|false
    {
        $raw = base64_decode($payload, true);

        if ($raw === false || strlen($raw) < self::GCM_IV_BYTES + self::GCM_TAG_BYTES) {
            return false;
        }

        $iv = substr($raw, 0, self::GCM_IV_BYTES);
        $tag = substr($raw, self::GCM_IV_BYTES, self::GCM_TAG_BYTES);
        $cipher = substr($raw, self::GCM_IV_BYTES + self::GCM_TAG_BYTES);

        return openssl_decrypt($cipher, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
    }

    private function decryptLegacyCbc(string $encrypted): string|false
    {
        $decoded = base64_decode($encrypted);
        $iv = substr($decoded, 0, 16);
        $cipher = substr($decoded, 16);

        return openssl_decrypt($cipher, 'AES-256-CBC', $this->key, 0, $iv);
    }
}
