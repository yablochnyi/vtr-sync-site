<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('site_posts', function (Blueprint $table) {
            $table->foreignId('author_id')->nullable()->constrained('site_authors')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_posts', function (Blueprint $table) {
            $table->dropForeign('site_posts_author_id_foreign');
            $table->dropColumn('author_id');
        });
    }
};
