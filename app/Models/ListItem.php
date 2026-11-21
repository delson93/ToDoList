<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ListItem extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['name', 'description', 'quantity', 'purchased', 'unit', 'estimated_price', 'actual_price', 'priority', 'notes', 'alternative', 'due_date', 'status'];

    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class, 'list_id');
    }
}
