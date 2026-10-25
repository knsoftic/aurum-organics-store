<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoQuery extends Model
{
    protected $fillable = ['query', 'clicks', 'impressions', 'ctr', 'position', 'period_start', 'period_end'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'position' => 'float', 'ctr' => 'float'];
    }
}
