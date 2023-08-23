<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenant_users', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->default('');
            $table->string('tenant_id')->default('');
            $table->string('json')->default('');
            $table->string('password')->default('');
            $table->string('drive_id')->default('');
            $table->timestamps();
        });
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->default('');
            $table->string('json')->default('');
            $table->string('sync_client_id')->default('');
            $table->string('sync_secret')->default('');
            $table->timestamp('secret_expired_at')->nullable();
            $table->string('sync_app_id')->default('');
            $table->boolean('init_onedrive')->default(false);
            $table->boolean('save_e5')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_users');
        Schema::dropIfExists('tenants');
    }
};
