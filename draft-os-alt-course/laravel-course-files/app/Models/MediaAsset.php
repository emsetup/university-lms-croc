<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class MediaAsset extends Model
{
    protected $fillable = [
        'uuid',
        'uploaded_by_learner_id',
        'course_id',
        'storage_path',
        'thumb_path',
        'original_filename',
        'mime',
        'width',
        'height',
        'bytes',
    ];

    protected $casts = [
        'uploaded_by_learner_id' => 'int',
        'course_id' => 'int',
        'width' => 'int',
        'height' => 'int',
        'bytes' => 'int',
    ];

    public static function newUuid(): string
    {
        do {
            $uuid = (string) Str::uuid();
        } while (self::query()->where('uuid', $uuid)->exists());

        return $uuid;
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Learner::class, 'uploaded_by_learner_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function pins(): HasMany
    {
        return $this->hasMany(LearnerMediaPin::class);
    }

    public function publicUrl(): string
    {
        return route('media.show', ['uuid' => $this->uuid], false);
    }

    public function thumbUrl(): string
    {
        return route('media.thumb', ['uuid' => $this->uuid], false);
    }

    public function adminFileUrl(): string
    {
        return route('admin.media.file', ['uuid' => $this->uuid], false);
    }

    public function adminThumbUrl(): string
    {
        return route('admin.media.thumb', ['uuid' => $this->uuid], false);
    }

    public function markdownSnippet(?string $alt = null): string
    {
        $alt = $alt !== null && $alt !== '' ? $alt : pathinfo($this->original_filename, PATHINFO_FILENAME);

        return '!['.str_replace(['[', ']'], '', $alt).']('.$this->publicUrl().')';
    }

    public function isLarge(): bool
    {
        $min = (int) config('media.lightbox_min_dimension', 600);

        return $this->width >= $min || $this->height >= $min;
    }
}
