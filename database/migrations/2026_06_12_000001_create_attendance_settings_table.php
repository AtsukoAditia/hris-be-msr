<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->string('office_name')->default('Main Office');
            $table->decimal('office_latitude', 11, 8)->nullable();
            $table->decimal('office_longitude', 11, 8)->nullable();
            $table->unsignedInteger('radius_meters')->default(100);
            $table->boolean('is_radius_enabled')->default(false);
            $table->boolean('is_qr_enabled')->default(true);
            $table->unsignedInteger('qr_expiry_minutes')->default(5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_settings');
    }
};
