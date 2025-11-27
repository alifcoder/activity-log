<?php
/**
 * Created by Shukhratjon Yuldashev on 2025-05-01
 * Contact: https://t.me/alif_coder
 * Time: 3:46 PM
 */

namespace Alif\ActivityLog\Traits;

use Alif\ActivityLog\DTO\ActivityLogCreateDTO;
use Alif\ActivityLog\Facades\ActivityLogger;
use Alif\ActivityLog\Models\ActivityLog;
use Illuminate\Support\Facades\Session;

trait ActivityLogTrait
{
    protected static function bootActivityLogTrait(): void
    {
        // log model creation
        static::created(function ($model) {
            $request = request();
            $additionalId = $request->attributes->get('activity_log_id');
            // check request is valid
            if (!$additionalId) {
                return;
            }

            // Prevent duplicate logging
            $key = 'activity_logged_'.$additionalId;

            if ($request->attributes->has($key)) {
                return;
            }

            $request->attributes->set($key, true);

            // get activity_log_id attribute from request and update log
            ActivityLogger::log(new ActivityLogCreateDTO(
                                        additional_id: $additionalId,
                                        model_id:      $model->id,
                                        model_type:    get_class($model)
                                ));
        });

        // log model update
        static::saving(function ($model) {
            if (!$model->id){
                return;
            }

            $request = request();
            $additionalId = $request->attributes->get('activity_log_id');
            // check request is valid
            if (!$additionalId) {
                return;
            }

            // Prevent duplicate logging
            $key = 'activity_logged_'.$additionalId;

            if ($request->attributes->has($key)) {
                return;
            }

            $request->attributes->set($key, true);

            // get activity_log_id attribute from request and update log
            ActivityLogger::log(new ActivityLogCreateDTO(
                                        additional_id: $additionalId,
                                        model_id:      $model->id,
                                        model_type:    get_class($model)
                                ));
        });

        static::deleting(function ($model) {
            $request = request();
            $additionalId = $request->attributes->get('activity_log_id');
            if (!$additionalId) {
                return;
            }

            // Prevent duplicate logging
            $key = 'activity_logged_'.$additionalId;

            if ($request->attributes->has($key)) {
                return;
            }

            $request->attributes->set($key, true);

            ActivityLogger::log(new ActivityLogCreateDTO(
                                        additional_id: $additionalId,
                                        model_id:      $model->id,
                                        model_type:    get_class($model)
                                ));
        });
    }
}
