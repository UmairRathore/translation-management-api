<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TranslationKey;
use App\Models\TranslationValue;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_endpoint_responds_within_threshold(): void
    {
        TranslationKey::factory()
            ->count(500)
            ->has(
                TranslationValue::factory()
                    ->count(2)
                    ->state(new Sequence(['locale' => 'en'], ['locale' => 'fr'])),
                'values',
            )
            ->create();

        $start = microtime(true);
        $response = $this->getJson('/api/translations/export');
        $elapsedMs = (microtime(true) - $start) * 1000;

        $response->assertOk();
        $this->assertLessThan(1000, $elapsedMs, "Export took {$elapsedMs}ms");
    }
}
