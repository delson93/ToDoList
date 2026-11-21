<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShoppingList extends Model
{
    use HasUuids;

    protected $table = 'lists';

    protected $fillable = ['name', 'category', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];

    public function items(): HasMany
    {
        return $this->hasMany(ListItem::class, 'list_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class, 'list_id');
    }
}
