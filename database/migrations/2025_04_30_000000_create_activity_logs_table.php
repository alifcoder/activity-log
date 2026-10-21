<?php

/**
 * Created by Shukhratjon Yuldashev on 2025-04-30
 * Contact: https://t.me/alif_coder
 * Time: 2:59 PM
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('activity-log.db_connection'))->create('activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable()->index();
            // one row per request: upsert by additional_id relies on this unique index (NULLs are allowed)
            $table->string('additional_id')->nullable()->unique();
            $table->string('log_type')->nullable()->index();
            $table->string('user_id')->nullable()->index();
            $table->string('route', 500)->nullable();
            $table->text('url')->nullable();
            $table->string('model_id')->nullable();
            $table->string('model_type')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('app_type')->nullable();
            $table->string('app_version')->nullable();
            $table->string('ip')->nullable()->index();
            $table->string('method')->nullable();
            $table->string('status_code')->nullable();
            $table->string('request_body')->nullable();
            $table->string('response_body')->nullable();
            $table->string('curl')->nullable();
            $table->text('description')->nullable();
            $table->decimal('request_duration', 10, 3)->nullable();
            $table->bigInteger('peak_memory_usage')->nullable();
            $table->json('ip_details')->nullable();
            $table->timestamps();

            // history of one model (ActivityLogService parent lookup, ERP document logs); also serves model_id lookups
            $table->index(['model_id', 'created_at']);
            // retention pruning
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection(config('activity-log.db_connection'))->dropIfExists('activity_logs');
    }
};
