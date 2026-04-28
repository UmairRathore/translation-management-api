<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use mysql_xdevapi\Table;

class TranslationValue extends Model
{
    use HasFactory;

    protected $table = 'translation_values';
    protected $fillable =
        [
            'translation_key_id',
            'locale',
            'content'
        ];

    public function translationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class);
    }
}
