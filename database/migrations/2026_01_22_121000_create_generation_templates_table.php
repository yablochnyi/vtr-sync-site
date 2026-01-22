<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generation_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('query_group_id')->constrained('query_groups')->cascadeOnDelete();

            $table->foreignId('article_promt_id')->constrained('promts')->cascadeOnDelete();
            $table->foreignId('slug_promt_id')->nullable()->constrained('promts')->nullOnDelete();
            $table->foreignId('category_promt_id')->nullable()->constrained('promts')->nullOnDelete();
            $table->foreignId('rewrite_promt_id')->nullable()->constrained('promts')->nullOnDelete();

            $table->unsignedInteger('articles_per_run')->default(1);
            $table->unsignedInteger('max_categories_per_article')->default(2);
            $table->unsignedInteger('internal_links_count')->default(3);
            $table->unsignedInteger('internal_links_last_n')->default(30);
            $table->unsignedInteger('permalinks_count')->default(1);
            $table->unsignedInteger('uniqueness_min_percent')->default(70);

            $table->string('author_mode')->default('auto');
            $table->foreignId('author_id')->nullable()->constrained('site_authors')->nullOnDelete();

            $table->string('wp_status')->default('draft');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_templates');
    }
};

