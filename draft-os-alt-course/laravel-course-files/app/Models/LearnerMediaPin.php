<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LearnerMediaPin extends Model
{
    public const SOURCE_UPLOAD = 'upload';

    public const SOURCE_COURSE_IMPORT = 'course_import';

    protected $fillable = [
        'learner_id',
        'media_asset_id',
        'source',
    ];

    protected $casts = [
        'learner_id' => 'int',
        'media_asset_id' => 'int',
    ];

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }
}
