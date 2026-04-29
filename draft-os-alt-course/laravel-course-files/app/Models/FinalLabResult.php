<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinalLabResult extends Model
{
    protected $fillable = [
        'learner_id',
        'attempts',
        'passed',
        'best_score',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'passed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
