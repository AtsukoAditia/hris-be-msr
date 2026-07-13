<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_conflict_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->date('schedule_date');
            $table->string('conflict_type')->comment('rest_hour, max_hours, overlap, coverage');
            $table->text('conflict_message');
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'schedule_date']);
            $table->index(['conflict_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_conflict_logs');
    }
};
