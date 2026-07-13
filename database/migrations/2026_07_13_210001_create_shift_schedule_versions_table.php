<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shift_schedule_versions')) {
            Schema::create('shift_schedule_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shift_schedule_id')->constrained()->onDelete('cascade');
                $table->integer('version')->default(1);
                $table->json('changes')->nullable();
                $table->foreignId('changed_by')->nullable()->constrained('users')->onDelete('set null');
                $table->string('action')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('shift_schedule_id');
                $table->index('version');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_schedule_versions');
    }
};
