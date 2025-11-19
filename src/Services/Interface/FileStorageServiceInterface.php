<?php
/**
 * Created by Sardorbek Abduqodirov on 17.11.2025
 * Contact: https://t.me/blackprince1605
 * Time: 15:21
 */

namespace Alif\ActivityLog\Services\Interface;

interface FileStorageServiceInterface
{
    public function storeEncrypted(string $content, string $fileName): string;

    public function readEncrypted(string $path): ?string;

    public function deleteEncrypted(string $path): bool;
}
