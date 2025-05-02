<?php
/**
 * Created by Shukhratjon Yuldashev on 2025-05-01
 * Contact: https://t.me/alif_coder
 * Time: 9:15 AM
 */

namespace Alif\ActivityLog\Services\Interface;

use Alif\ActivityLog\DTO\ActivityLogCreateDTO;
use Illuminate\Http\Request;

interface ActivityLogServiceInterface
{
    public function log(ActivityLogCreateDTO $dto): void;

    public function updateOrCreate(ActivityLogCreateDTO $dto): void;

    public function curlOfRequest($request): ?string;

    public function getPayloadOfRequest(Request $request): array;
}