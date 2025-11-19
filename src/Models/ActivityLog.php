<?php
/**
 * Created by Shukhratjon Yuldashev on 2025-04-30
 * Contact: https://t.me/alif_coder
 * Time: 11:47 AM
 */

namespace Alif\ActivityLog\Models;

use Alif\ActivityLog\Facades\FileStorage;
use Alif\ActivityLog\Traits\UUIDTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ActivityLog extends Model
{
    use UUIDTrait, Prunable;

    protected $table = 'activity_logs';

    protected $guarded = false;

    protected $casts = [
        'request_body'  => 'string',
        'response_body' => 'string',
        'curl'          => 'string',
    ];

    public function getConnectionName()
    {
        return config('activity-log.db_connection');
    }

    public function prunable()
    {
        $days = config('activity-log.log_keep_days', 30);

        return static::where('created_at', '<', now()->subDays($days));
    }

    public function pruning()
    {
        // request file
        if ($this->request_body) {
            FileStorage::deleteEncrypted($this->request_body);
        }

        // response file
        if ($this->response_body) {
            FileStorage::deleteEncrypted($this->response_body);
        }

        // curl file
        if ($this->curl) {
            FileStorage::deleteEncrypted($this->curl);
        }
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
