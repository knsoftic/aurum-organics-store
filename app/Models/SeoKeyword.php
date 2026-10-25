<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SeoKeyword extends Model
{
    public const PRIORITIES = [1 => 'High', 2 => 'Medium', 3 => 'Low'];

    protected $fillable = ['keyword', 'target_path', 'group', 'priority', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function stats(): HasMany
    {
        return $this->hasMany(SeoKeywordStat::class)->orderBy('date');
    }

    public function latestStat(): HasOne
    {
        return $this->hasOne(SeoKeywordStat::class)->ofMany(['date' => 'max', 'id' => 'max']);
    }

    /** Google search link for checking the position by hand (Pakistan results). */
    public function getGoogleUrlAttribute(): string
    {
        return 'https://www.google.com.pk/search?'.http_build_query(['q' => $this->keyword, 'gl' => 'pk', 'hl' => 'en']);
    }
}
