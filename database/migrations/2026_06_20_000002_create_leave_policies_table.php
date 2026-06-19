<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_type_id')->constrained()->onDelete('cascade');
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('default_quota');
            $table->unsignedTinyInteger('carry_forward_expiry_month')->nullable();
            $table->unsignedTinyInteger('carry_forward_expiry_day')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['leave_type_id', 'year']);
            $table->index(['year', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_policies');
    }
};
