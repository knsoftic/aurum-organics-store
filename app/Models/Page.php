<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = ['title', 'slug', 'content', 'meta_title', 'meta_description', 'focus_keyword', 'show_in_footer', 'is_active'];

    protected function casts(): array
    {
        return ['show_in_footer' => 'boolean', 'is_active' => 'boolean'];
    }
}
