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
        Schema::create('site_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('wp_id')->index();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('avatar')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();
            $table->unique(['site_id', 'wp_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_authors');
    }
};
