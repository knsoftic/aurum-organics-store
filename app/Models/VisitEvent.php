<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitEvent extends Model
{
    public const UPDATED_AT = null;

    /** Event name => label shown in the admin. */
    public const NAMES = [
        'add_to_cart' => 'Added to bag',
        'begin_checkout' => 'Started checkout',
        'purchase' => 'Placed order',
        'whatsapp' => 'WhatsApp click',
        'contact' => 'Contact form',
    ];

    protected $fillable = ['visit_id', 'name', 'value', 'label', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'value' => 'decimal:2'];
    }
}
