<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_attachment')->default(false);
            $table->unsignedSmallInteger('max_consecutive_days')->nullable();
            $table->unsignedSmallInteger('min_service_months')->default(0);
            $table->enum('gender_restriction', ['male', 'female', 'all'])->default('all');
            $table->boolean('carry_forward_enabled')->default(false);
            $table->unsignedSmallInteger('max_carry_forward_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
