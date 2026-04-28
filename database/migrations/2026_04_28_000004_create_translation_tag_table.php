<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_tag', function (Blueprint $table): void {
            $table->foreignId('translation_key_id')
                ->constrained('translation_keys')
                ->cascadeOnDelete();

            $table->foreignId('tag_id')
                ->constrained('tags')
                ->cascadeOnDelete();

            // Composite primary key — no surrogate id needed for a pure
            // many-to-many pivot, and it doubles as the lookup index
            // for "which tags does this key have".
            $table->primary(['translation_key_id', 'tag_id']);

            // Reverse-direction index for "which keys have this tag",
            // which the search endpoint uses when filtering by tag.
            $table->index('tag_id', 'translation_tag_tag_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_tag');
    }
};
