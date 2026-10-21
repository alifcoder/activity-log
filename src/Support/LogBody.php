<?php

namespace Alif\ActivityLog\Support;

/**
 * Size rule for logged bodies: a body above `activity-log.max_body_bytes` is stored as a size marker only.
 */
class LogBody
{
    public static function maxBytes(): int
    {
        return (int) config('activity-log.max_body_bytes', 262144);
    }

    public static function isOversized(string $content): bool
    {
        return strlen($content) > self::maxBytes();
    }

    /**
     * Decodes a JSON object or array body. An oversized body becomes a size marker; scalar JSON or plain text gives null,
     * because only arrays fit the log DTO.
     */
    public static function decodeJson(string $content): ?array
    {
        if (self::isOversized($content)) {
            return ['_truncated' => strlen($content)];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }
}
