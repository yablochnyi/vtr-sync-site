<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permalinks', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->text('theme')->nullable();
            $table->boolean('open_in_new_tab')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('permalinks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_id');
            $table->dropColumn(['theme', 'open_in_new_tab']);
        });
    }
};

