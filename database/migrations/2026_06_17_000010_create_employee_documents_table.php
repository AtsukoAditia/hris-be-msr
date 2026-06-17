<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 50)->index();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->json('labels')->nullable();
            $table->string('disk', 50)->default('employee_documents');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('mime_type', 100);
            $table->string('extension', 20);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->unsignedInteger('version')->default(1);
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->boolean('is_confidential')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'category']);
            $table->index(['employee_id', 'expiry_date']);
            $table->index(['employee_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
