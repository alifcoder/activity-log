<?php
/**
 * Created by Shukhratjon Yuldashev on 2025-04-30
 * Contact: https://t.me/alif_coder
 * Time: 4:38 PM
 */

namespace Alif\ActivityLog\DTO;

class ActivityLogCreateDTO
{
    public function __construct(
            public ?string $additional_id = null,
            public ?string $log_type = null,
            public ?string $user_id = null,
            public ?string $module = null,
            public ?string $route = null,
            public ?string $url = null,
            public ?string $model_id = null,
            public ?string $model_type = null,
            public ?string $user_agent = null,
            public ?string $app_type = null,
            public ?string $app_version = null,
            public ?string $ip = null,
            public ?string $method = null,
            public ?string $status_code = null,
            public ?array $request_body = null,
            public ?array $response_body = null,
            public ?string $curl = null,
            public ?string $description = null,
    ) {
    }

    public function toArray(): array
    {
        return collect(get_object_vars($this))
                ->filter(fn($value) => !is_null($value))
                ->toArray();
    }

}