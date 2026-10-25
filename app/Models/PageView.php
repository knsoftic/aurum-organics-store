<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageView extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['visit_id', 'path', 'title', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
