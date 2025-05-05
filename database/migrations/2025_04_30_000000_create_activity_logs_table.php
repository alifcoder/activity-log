<?php
/**
 * Created by Shukhratjon Yuldashev on 2025-04-30
 * Contact: https://t.me/alif_coder
 * Time: 2:59 PM
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection(config('activity-log.db_connection'))->create('activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable()->index();
            $table->string('additional_id')->nullable()->index();
            $table->string('log_type')->nullable()->index();
            $table->string('user_id')->nullable()->index();
            $table->string('module')->nullable()->index();
            $table->string('route', 500)->nullable()->index();
            $table->string('url')->nullable()->index();
            $table->string('model_id')->nullable()->index();
            $table->string('model_type')->nullable()->index();
            $table->string('user_agent')->nullable()->index();
            $table->string('app_type')->nullable()->index();
            $table->string('app_version')->nullable()->index();
            $table->string('ip')->nullable()->index();
            $table->string('method')->nullable()->index();
            $table->string('status_code')->nullable()->index();
            $table->json('request_body')->nullable();
            $table->json('response_body')->nullable();
            $table->text('curl')->nullable();
            $table->text('description')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection(config('activity-log.db_connection'))->dropIfExists('activity_logs');
    }
};