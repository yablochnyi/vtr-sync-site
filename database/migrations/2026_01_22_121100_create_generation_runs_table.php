<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generation_template_id')->constrained('generation_templates')->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('queued');
            $table->unsignedInteger('requested')->default(0);
            $table->unsignedInteger('generated')->default(0);
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_runs');
    }
};

