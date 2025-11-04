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

class ActivityLog extends Model
{
    use UUIDTrait;

    protected $casts = [
            'request_body'  => 'array',
            'response_body' => 'array',
    ];

    protected $table = 'activity_logs';

    protected $guarded = false;

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