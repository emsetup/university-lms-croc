<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class FinalLabResult extends Model
{
    protected $fillable = [
        'learner_id',
        'attempts',
        'passed',
        'best_score',
        'completed_at',
        'certificate_full_name',
        'certificate_serial',
        'certificate_issued_at',
    ];

    protected function casts(): array
    {
        return [
            'passed' => 'boolean',
            'completed_at' => 'datetime',
            'certificate_issued_at' => 'datetime',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    /**
     * Дата «выдачи» для бланка: из БД либо из фрагмента даты в номере CROC-ALT-YYYYMMDD-…
     */
    public function certificateDisplayIssueDate(): Carbon
    {
        if ($this->certificate_issued_at) {
            return Carbon::parse($this->certificate_issued_at)
                ->timezone((string) config('app.timezone'));
        }

        $serial = (string) ($this->certificate_serial ?? '');
        if (preg_match('/CROC-ALT-(\d{8})-/', $serial, $m) === 1) {
            try {
                return Carbon::createFromFormat('Ymd', $m[1], (string) config('app.timezone'))
                    ->startOfDay();
            } catch (\Throwable) {
                // fall through
            }
        }

        return Carbon::now((string) config('app.timezone'));
    }
}
