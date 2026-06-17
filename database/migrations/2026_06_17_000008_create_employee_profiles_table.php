<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('personal_email')->nullable()->unique();
            $table->string('alternate_phone', 30)->nullable();
            $table->string('place_of_birth', 100)->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->string('blood_type', 5)->nullable();
            $table->string('religion', 50)->nullable();
            $table->string('nationality', 100)->nullable();
            $table->text('identity_address')->nullable();
            $table->text('domicile_address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('tax_number', 50)->nullable()->unique();
            $table->string('social_security_number', 50)->nullable()->unique();
            $table->string('health_insurance_number', 50)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
