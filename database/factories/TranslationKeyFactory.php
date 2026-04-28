<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class TranslationKeyFactory extends Factory
{
    protected $model = TranslationKey::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(3, false),
        ];
    }
}
