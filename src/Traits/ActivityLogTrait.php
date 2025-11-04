<?php
/**
 * Created by Shukhratjon Yuldashev on 2025-05-01
 * Contact: https://t.me/alif_coder
 * Time: 3:46 PM
 */

namespace Alif\ActivityLog\Traits;

use Alif\ActivityLog\DTO\ActivityLogCreateDTO;
use Alif\ActivityLog\Facades\ActivityLogger;

trait ActivityLogTrait
{
    protected static function bootActivityLogTrait(): void
    {
        // log model creation
        static::created(function ($model) {
            $request = request();
            // check request is valid
            if (!$request->attributes->has('activity_log_id')) {
                return;
            }

            // get activity_log_id attribute from request and update log
            ActivityLogger::log(new ActivityLogCreateDTO(
                                        additional_id: $request->attributes->get('activity_log_id'),
                                        model_id:      $model->id,
                                        model_type:    get_class($model)
                                ));
        });

        // log model update
        static::saving(function ($model) {
            $request = request();
            // check request is valid
            if (!$request->attributes->has('activity_log_id')) {
                return;
            }

            // get activity_log_id attribute from request and update log
            ActivityLogger::log(new ActivityLogCreateDTO(
                                        additional_id: $request->attributes->get('activity_log_id'),
                                        model_id:      $model->id,
                                        model_type:    get_class($model)
                                ));
        });

        static::deleting(function ($model) {
            $request = request();

            if (!$request->attributes->has('activity_log_id')) {
                return;
            }

            ActivityLogger::log(new ActivityLogCreateDTO(
                                        additional_id: $request->attributes->get('activity_log_id'),
                                        model_id:      $model->id,
                                        model_type:    get_class($model)
                                ));
        });
    }
}