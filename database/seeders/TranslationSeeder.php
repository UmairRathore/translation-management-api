<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TranslationSeeder extends Seeder
{
    private const TOTAL_KEYS = 100_000;
    private const BATCH_SIZE = 1_000;
    private const TAG_POOL_SIZE = 10;
    private const LOCALES = ['en', 'fr'];

    public function run(): void
    {
        $tagIds = Tag::factory()->count(self::TAG_POOL_SIZE)->create()->pluck('id')->all();
        $now = Carbon::now();

        for ($batch = 0; $batch < self::TOTAL_KEYS; $batch += self::BATCH_SIZE) {
            $keys = [];
            $keyRows = [];

            for ($i = 0; $i < self::BATCH_SIZE; $i++) {
                $key = 'translation.key.'.($batch + $i);
                $keys[] = $key;
                $keyRows[] = [
                    'key' => $key,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('translation_keys')->insert($keyRows);

            $keyIds = DB::table('translation_keys')
                ->whereIn('key', $keys)
                ->pluck('id', 'key');

            $valueRows = [];
            $pivotRows = [];

            foreach ($keyIds as $keyName => $keyId) {
                foreach (self::LOCALES as $locale) {
                    $valueRows[] = [
                        'translation_key_id' => $keyId,
                        'locale' => $locale,
                        'content' => "Sample {$locale} content for {$keyName}",
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                shuffle($tagIds);
                foreach (array_slice($tagIds, 0, random_int(1, 3)) as $tagId) {
                    $pivotRows[] = [
                        'translation_key_id' => $keyId,
                        'tag_id' => $tagId,
                    ];
                }
            }

            DB::table('translation_values')->insert($valueRows);
            DB::table('translation_tag')->insert($pivotRows);

            $this->command->info(sprintf('Seeded %d / %d keys', $batch + self::BATCH_SIZE, self::TOTAL_KEYS));
        }
    }
}
