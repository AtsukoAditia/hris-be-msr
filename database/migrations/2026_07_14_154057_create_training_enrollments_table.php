<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['registered', 'confirmed', 'attended', 'completed', 'no_show', 'cancelled'])->default('registered');
            $table->text('notes')->nullable();
            $table->text('feedback')->nullable();
            $table->unsignedSmallInteger('score')->nullable(); // 0-100
            $table->boolean('certificate_issued')->default(false);
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['training_id', 'employee_id']);
            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_enrollments');
    }
};
