<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_posts', function (Blueprint $table) {
            $table->foreignId('generation_run_id')->nullable()->constrained('generation_runs')->nullOnDelete();
            $table->foreignId('generation_template_id')->nullable()->constrained('generation_templates')->nullOnDelete();
            $table->foreignId('query_topic_id')->nullable()->constrained('query_topics')->nullOnDelete();

            $table->boolean('is_generated')->default(false)->index();
            $table->string('link')->nullable();
            $table->text('summary')->nullable();
            $table->json('ai_meta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('generation_run_id');
            $table->dropConstrainedForeignId('generation_template_id');
            $table->dropConstrainedForeignId('query_topic_id');
            $table->dropColumn(['is_generated', 'link', 'summary', 'ai_meta']);
        });
    }
};

