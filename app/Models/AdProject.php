<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdProject extends Model
{
    /** Output formats the studio can render. */
    public const FORMATS = [
        'motion' => ['label' => 'Reel — Motion graphics', 'ratio' => '9:16', 'w' => 1080, 'h' => 1920],
        'footage' => ['label' => 'Reel — Real footage', 'ratio' => '9:16', 'w' => 1080, 'h' => 1920],
        'landscape' => ['label' => 'Landscape — Feed / YouTube', 'ratio' => '16:9', 'w' => 1920, 'h' => 1080],
    ];

    /** Voice segments, one per scene type (landscape reuses the motion ones). */
    public const VOICE_KEYS = [
        'hook' => 'Hook', 'reveal' => 'Product intro', 'benefits' => 'Benefits', 'ingredients' => 'Ingredients',
        'meet' => 'Footage opener', 'inside' => 'What\'s inside', 'nothing' => 'Free from', 'howto' => 'How to use',
        'offer' => 'Price & offer',
    ];

    public const VOICES = [
        'ur-PK-UzmaNeural' => 'Uzma — Urdu (Pakistan), female',
        'ur-PK-AsadNeural' => 'Asad — Urdu (Pakistan), male',
        'ur-IN-GulNeural' => 'Gul — Urdu (India), female',
        'ur-IN-SalmanNeural' => 'Salman — Urdu (India), male',
    ];

    protected $fillable = ['title', 'product_id', 'config'];

    protected function casts(): array
    {
        return ['config' => 'array'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function renders(): HasMany
    {
        return $this->hasMany(AdRender::class)->latest();
    }
}
