<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdMailGroup extends Model
{
    protected $table = 'ad_mail_groups';

    protected $fillable = [
        'object_guid',
        'cn',
        'display_name',
        'mail',
        'sam_account_name',
        'dn',
        'group_type',
        'is_active',
        'imported_at',
    ];

    protected $casts = [
        'group_type' => 'integer',
        'is_active' => 'boolean',
        'imported_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $query->whereRaw('1 = 0');
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('mail', 'like', $like)
                ->orWhere('display_name', 'like', $like)
                ->orWhere('cn', 'like', $like)
                ->orWhere('sam_account_name', 'like', $like);
        });
    }

    public function label(): string
    {
        $name = trim((string) ($this->display_name ?: $this->cn ?: ''));

        return $name !== '' ? $name : (string) $this->mail;
    }
}
