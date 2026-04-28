<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TranslationKey;
use App\Models\TranslationValue;
use Illuminate\Database\Eloquent\Factories\Factory;

class TranslationValueFactory extends Factory
{
    protected $model = TranslationValue::class;

    public function definition(): array
    {
        return [
            'translation_key_id' => TranslationKey::factory(),
            'locale' => $this->faker->randomElement(['en', 'fr', 'es', 'de']),
            'content' => $this->faker->sentence(),
        ];
    }
}
