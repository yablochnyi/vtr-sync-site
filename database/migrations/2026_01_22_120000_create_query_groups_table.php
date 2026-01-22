<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('query_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('query_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('query_group_id')->constrained()->cascadeOnDelete();
            $table->text('topic');
            $table->unsignedInteger('position')->default(0);
            $table->string('status')->default('pending'); // pending|reserved|used|failed
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['query_group_id', 'status', 'position']);
        });

        // One-time migration from legacy `queries` table (title + multiline queries).
        if (Schema::hasTable('queries')) {
            $legacyGroups = DB::table('queries')->select(['id', 'title', 'queries'])->orderBy('id')->get();

            foreach ($legacyGroups as $legacy) {
                $groupId = DB::table('query_groups')->insertGetId([
                    'site_id' => null,
                    'title' => $legacy->title,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $lines = preg_split("/\r\n|\r|\n/", (string) $legacy->queries);
                $lines = array_values(array_filter(array_map(static fn ($l) => trim((string) $l), $lines)));

                foreach ($lines as $idx => $line) {
                    DB::table('query_topics')->insert([
                        'query_group_id' => $groupId,
                        'topic' => $line,
                        'position' => $idx,
                        'status' => 'pending',
                        'reserved_at' => null,
                        'used_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('query_topics');
        Schema::dropIfExists('query_groups');
    }
};

