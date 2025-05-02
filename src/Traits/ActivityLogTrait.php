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
            // check request is valid
            if (!\Request::has('activity_log_id')) {
                return;
            }

            // get activity_log_id attribute from request and update log
            ActivityLogger::log(new ActivityLogCreateDTO(
                                        additional_id: \Request::get('activity_log_id'),
                                        model_id:      $model->id,
                                        model_type:    get_class($model)
                                ));
        });

        // log model update
        static::updated(function ($model) {
            // check request is valid
            if (!\Request::has('activity_log_id')) {
                return;
            }

            // get activity_log_id attribute from request and update log
            ActivityLogger::log(new ActivityLogCreateDTO(
                                        additional_id: \Request::get('activity_log_id'),
                                        model_id:      $model->id,
                                        model_type:    get_class($model)
                                ));
        });
    }
}