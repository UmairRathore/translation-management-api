<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TranslationKey;
use App\Repositories\TagRepository;
use App\Repositories\TranslationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TranslationService
{
    public function __construct(
        private readonly TranslationRepository $translations,
        private readonly TagRepository $tags,
    ) {}

    public function find(string $key): TranslationKey
    {
        $model = $this->translations->findByKey($key);

        if ($model === null) {
            throw (new ModelNotFoundException())->setModel(TranslationKey::class, [$key]);
        }

        return $model;
    }

    public function create(string $key, array $translations, array $tagNames): TranslationKey
    {
        return $this->translations->transaction(function () use ($key, $translations, $tagNames): TranslationKey {
            $model = $this->translations->createKey($key);
            $this->translations->upsertValues($model->id, $translations);

            $tagIds = $this->tags->resolveByNames($tagNames)->pluck('id')->all();
            $this->translations->syncTags($model, $tagIds);

            return $model->refresh()->load(['values', 'tags']);
        });
    }

    public function update(string $key, array $translations, ?array $tagNames): TranslationKey
    {
        return $this->translations->transaction(function () use ($key, $translations, $tagNames): TranslationKey {
            $model = $this->translations->findByKey($key);

            if ($model === null) {
                throw (new ModelNotFoundException())->setModel(TranslationKey::class, [$key]);
            }

            $this->translations->upsertValues($model->id, $translations);

            if ($tagNames !== null) {
                $tagIds = $this->tags->resolveByNames($tagNames)->pluck('id')->all();
                $this->translations->syncTags($model, $tagIds);
            }

            return $model->refresh()->load(['values', 'tags']);
        });
    }

    public function search(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->translations->search($filters, $perPage);
    }

    public function export(?string $locale = null): array
    {
        $map = [];

        foreach ($this->translations->streamForExport($locale) as $row) {
            $map[$row->locale][$row->key] = $row->content;
        }

        return $map;
    }
}
