<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdRender extends Model
{
    protected $fillable = ['ad_project_id', 'format', 'path', 'width', 'height', 'duration', 'size', 'has_voice'];

    protected function casts(): array
    {
        return ['has_voice' => 'boolean', 'duration' => 'decimal:2'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(AdProject::class, 'ad_project_id');
    }

    public function getUrlAttribute(): string
    {
        return media_url($this->path);
    }

    public function getFormatLabelAttribute(): string
    {
        return AdProject::FORMATS[$this->format]['label'] ?? $this->format;
    }

    public function getDownloadNameAttribute(): string
    {
        $project = str($this->project?->title ?? 'ad')->slug();
        $ratio = str_replace(':', 'x', AdProject::FORMATS[$this->format]['ratio'] ?? '');

        return "{$project}-{$this->format}-{$ratio}-{$this->id}.mp4";
    }
}
