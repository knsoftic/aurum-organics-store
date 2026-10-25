<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoKeywordStat extends Model
{
    protected $fillable = ['seo_keyword_id', 'date', 'source', 'position', 'clicks', 'impressions', 'ctr'];

    protected function casts(): array
    {
        return ['date' => 'date', 'position' => 'float', 'ctr' => 'float'];
    }
}
