<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('leave_type_id')->constrained()->onDelete('cascade');
            $table->unsignedSmallInteger('year');
            $table->integer('opening_balance')->default(0);
            $table->integer('used_days')->default(0);
            $table->integer('remaining_balance')->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year']);
            $table->index(['employee_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
