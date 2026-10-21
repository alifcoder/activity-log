<?php

/**
 * Created by Shukhratjon Yuldashev on 2025-05-01
 * Contact: https://t.me/alif_coder
 * Time: 3:46 PM
 */

namespace Alif\ActivityLog\Traits;

use Alif\ActivityLog\DTO\ActivityLogCreateDTO;
use Alif\ActivityLog\Facades\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

trait ActivityLogTrait
{
    protected static function bootActivityLogTrait(): void
    {
        static::created(fn (Model $model) => self::logModelChange($model));
        static::deleting(fn (Model $model) => self::logModelChange($model));

        // only real changes are logged; a save without dirty attributes writes nothing
        static::saving(function (Model $model) {
            if ($model->id && $model->isDirty()) {
                self::logModelChange($model);
            }
        });
    }

    private static function logModelChange(Model $model): void
    {
        if (! config('activity-log.enabled', true)) {
            return;
        }

        $request = request();
        $additionalId = $request->attributes->get('activity_log_id');

        // outside a logged request (console, queue) there is nothing to link to
        if (! $additionalId) {
            return;
        }

        // one model link per request: the first change wins
        $key = 'activity_logged_' . $additionalId;

        if ($request->attributes->has($key)) {
            return;
        }

        $request->attributes->set($key, true);

        ActivityLogger::log(new ActivityLogCreateDTO(
            additional_id: $additionalId,
            model_id: $model->id,
            model_type: get_class($model),
        ));
    }
}
