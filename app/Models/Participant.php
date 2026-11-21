<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Participant extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'role', 'user_id', 'guest_identity_id', 'removed_at', 'access_changed_at'];

    protected $hidden = ['user_id', 'guest_identity_id', 'removed_at', 'access_changed_at'];

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('removed_at');
    }

    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class, 'list_id');
    }
}
