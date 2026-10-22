<?php

namespace Alif\ActivityLog\Support;

class LogBody
{
    /**
     * Decodes a JSON object or array body. Scalar JSON or plain text gives null,
     * because only arrays fit the log DTO.
     */
    public static function decodeJson(string $content): ?array
    {
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }
}
