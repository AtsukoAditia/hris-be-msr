<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 50)->nullable()->index(); // technical, soft_skill, compliance, leadership
            $table->string('trainer')->nullable(); // external trainer name
            $table->string('location')->nullable();
            $table->enum('mode', ['online', 'offline', 'hybrid'])->default('offline');
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('max_participants')->default(30);
            $table->unsignedSmallInteger('cost')->default(0); // 0 = free
            $table->enum('status', ['draft', 'open', 'closed', 'ongoing', 'completed', 'cancelled'])->default('draft');
            $table->text('requirements')->nullable();
            $table->string('certificate_template')->nullable(); // file path
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'start_date']);
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainings');
    }
};
