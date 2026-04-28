<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TranslationKey extends Model
{
    use HasFactory;

    protected $table = 'translation_keys';
    protected $fillable = [
        'key'
    ];

    public function values(): HasMany
    {
        return $this->hasMany(TranslationValue::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'translation_tag');
    }
}
