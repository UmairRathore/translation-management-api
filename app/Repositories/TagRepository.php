<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tag;
use Illuminate\Support\Collection;

class TagRepository
{
    public function resolveByNames(array $names): Collection
    {
        $names = array_values(array_unique(array_filter(array_map('trim', $names))));

        if ($names === []) {
            return new Collection();
        }

        $existing = Tag::query()->whereIn('name', $names)->get();
        $missing = array_diff($names, $existing->pluck('name')->all());

        if ($missing !== []) {
            Tag::query()->insert(array_map(
                static fn (string $name): array => [
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                $missing,
            ));

            $existing = Tag::query()->whereIn('name', $names)->get();
        }

        return $existing;
    }
}
