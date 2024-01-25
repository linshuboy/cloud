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
        Schema::create('ali_disk_share_files', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('');
            $table->integer('size')->default(0);
            $table->string('drive_id')->default('');
            $table->string('domain_id')->default('');
            $table->string('file_id')->default('');
            $table->string('share_id')->default('');
            $table->string('type')->default('');
            $table->string('parent_file_id')->default('');
            $table->string('temp_file_id')->default('');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ali_disk_share_files');
    }
};
