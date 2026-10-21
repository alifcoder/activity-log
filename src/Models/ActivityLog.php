<?php

/**
 * Created by Shukhratjon Yuldashev on 2025-04-30
 * Contact: https://t.me/alif_coder
 * Time: 11:47 AM
 */

namespace Alif\ActivityLog\Models;

use Alif\ActivityLog\Traits\UUIDTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $parent_id
 * @property string|null $additional_id
 * @property string|null $log_type
 * @property string|null $user_id
 * @property string|null $module
 * @property string|null $route
 * @property string|null $url
 * @property string|null $model_id
 * @property string|null $model_type
 * @property string|null $user_agent
 * @property string|null $app_type
 * @property string|null $app_version
 * @property string|null $ip
 * @property string|null $method
 * @property string|null $status_code
 * @property string|null $request_body
 * @property string|null $response_body
 * @property string|null $curl
 * @property string|null $description
 * @property float|null $request_duration
 * @property int|null $peak_memory_usage
 * @property array<string, mixed>|null $ip_details
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ActivityLog extends Model
{
    use UUIDTrait;

    protected $table = 'activity_logs';

    protected $guarded = [];

    protected $casts = [
        'request_body' => 'string',
        'response_body' => 'string',
        'curl' => 'string',
    ];

    public function getConnectionName()
    {
        return config('activity-log.db_connection');
    }

    // chain of log
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ActivityLog::class, 'parent_id');
    }

    public function child(): HasOne
    {
        return $this->hasOne(ActivityLog::class, 'parent_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->setConnection(config('activity-log.main_connection'))->belongsTo(config('activity-log.models.user'), 'user_id');
    }
}
