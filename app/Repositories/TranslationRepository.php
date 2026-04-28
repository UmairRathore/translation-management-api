<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\TranslationKey;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class TranslationRepository
{
    public function findByKey(string $key): ?TranslationKey
    {
        return TranslationKey::query()
            ->with(['values', 'tags'])
            ->where('key', $key)
            ->first();
    }

    public function createKey(string $key): TranslationKey
    {
        return TranslationKey::query()->create(['key' => $key]);
    }

    public function upsertValues(int $translationKeyId, array $valuesByLocale): void
    {
        if ($valuesByLocale === []) {
            return;
        }

        $now = Carbon::now();
        $rows = [];

        foreach ($valuesByLocale as $locale => $content) {
            $rows[] = [
                'translation_key_id' => $translationKeyId,
                'locale' => $locale,
                'content' => $content,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('translation_values')->upsert(
            $rows,
            ['translation_key_id', 'locale'],
            ['content', 'updated_at'],
        );
    }

    public function syncTags(TranslationKey $translationKey, array $tagIds): void
    {
        $translationKey->tags()->sync($tagIds);
    }

    public function search(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        $query = TranslationKey::query()->with(['values', 'tags']);

        if (! empty($filters['key'])) {
            $query->where('key', 'like', '%'.$filters['key'].'%');
        }

        $content = $filters['content'] ?? null;
        $locale = $filters['locale'] ?? null;

        if ($content || $locale) {
            $query->whereHas('values', static function ($q) use ($content, $locale): void {
                if ($content) {
                    $q->where('content', 'like', '%'.$content.'%');
                }
                if ($locale) {
                    $q->where('locale', $locale);
                }
            });
        }

        if (! empty($filters['tags'])) {
            $tags = array_values(array_filter($filters['tags']));
            $query->whereHas('tags', static function ($q) use ($tags): void {
                $q->whereIn('name', $tags);
            });
        }

        return $query->orderBy('id')->paginate($perPage);
    }

    public function streamForExport(?string $locale = null): LazyCollection
    {
        $query = DB::table('translation_values as tv')
            ->join('translation_keys as tk', 'tk.id', '=', 'tv.translation_key_id')
            ->select('tv.id', 'tk.key', 'tv.locale', 'tv.content');

        if ($locale) {
            $query->where('tv.locale', $locale);
        }

        return $query->orderBy('tv.id')->cursor();
    }

    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }
}
