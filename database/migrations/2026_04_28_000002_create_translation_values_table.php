<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_values', function (Blueprint $table): void {
            $table->bigIncrements('id');

            $table->foreignId('translation_key_id')
                ->constrained('translation_keys')
                ->cascadeOnDelete();

            $table->string('locale', 10);
            $table->text('content');
            $table->timestamps();

            // Composite index serves the export join (key_id -> values)
            // and the (key_id, locale) point lookup. Also enforces one
            // value per (key, locale) pair at the DB level.
            $table->unique(['translation_key_id', 'locale'], 'translation_values_key_locale_unique');

            // Search by locale alone (e.g. filter export to a single locale).
            $table->index('locale', 'translation_values_locale_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_values');
    }
};
