<?php
/**
 * Created by Shukhratjon Yuldashev on 2025-05-01
 * Contact: https://t.me/alif_coder
 * Time: 12:20 PM
 */

namespace Alif\ActivityLog\Traits;

trait SchemaTrait
{
    // set connection from config
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (str_starts_with($this->getTable(), config('activity-log.db_connection'))) {
            return;
        }
        $this->setTable(config('activity-log.db_connection') . '.' . $this->getTable());
    }
}